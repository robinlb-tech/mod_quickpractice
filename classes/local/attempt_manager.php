<?php
/**
 * Attempt Manager — create/fetch/finish student attempts
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpractice\local;

defined('MOODLE_INTERNAL') || die();

class attempt_manager {

    private $quickpractice;
    private $user;
    private $context;

    public function __construct($quickpractice, $user, $context) {
        $this->quickpractice = $quickpractice;
        $this->user          = $user;
        $this->context       = $context;
    }

    /**
     * Check if the user can start a new attempt.
     * Returns true | 'maxattempts' | 'notopen' | 'closed'
     */
    public function can_start_new_attempt() {
        global $DB;
        $now = time();
        $qp  = $this->quickpractice;

        if ($qp->timeopen && $now < $qp->timeopen) { return 'notopen'; }
        if ($qp->timeclose && $now > $qp->timeclose) { return 'closed'; }

        if ($qp->maxattempts > 0) {
            $count = $DB->count_records('quickpractice_attempts', [
                'quickpracticeid' => $qp->id,
                'userid'          => $this->user->id,
                'state'           => 'finished',
            ]);
            if ($count >= $qp->maxattempts) { return 'maxattempts'; }
        }
        return true;
    }

    /**
     * Get all attempts by the current user.
     */
    public function get_my_attempts() {
        global $DB;
        return $DB->get_records('quickpractice_attempts', [
            'quickpracticeid' => $this->quickpractice->id,
            'userid'          => $this->user->id,
        ], 'attemptnum DESC');
    }

    /**
     * Get a specific attempt (validates ownership).
     */
    public function get_attempt($attemptid, $userid) {
        global $DB;
        return $DB->get_record('quickpractice_attempts', [
            'id'              => $attemptid,
            'userid'          => $userid,
            'quickpracticeid' => $this->quickpractice->id,
        ]) ?: null;
    }

    /**
     * Create a new inprogress attempt.
     */
    public function create_attempt() {
        global $DB;
        $qp = $this->quickpractice;

        // Count existing attempts
        $num = $DB->count_records('quickpractice_attempts', [
            'quickpracticeid' => $qp->id,
            'userid'          => $this->user->id,
        ]) + 1;

        // Determine question order
        $qids = $DB->get_fieldset_select('quickpractice_questions',
            'id', 'quickpracticeid = ?', [$qp->id], 'sortorder,id');
        if ($qp->shuffle) { shuffle($qids); }

        $attempt = (object)[
            'quickpracticeid' => $qp->id,
            'userid'          => $this->user->id,
            'attemptnum'      => $num,
            'state'           => 'inprogress',
            'score'           => 0,
            'maxscore'        => 0,
            'timecreated'     => time(),
            'questionorder'   => json_encode($qids),
            'classname'       => quickpractice_extract_classname($this->user),
            'ipaddress'       => getremoteaddr(),
        ];

        $attempt->id = $DB->insert_record('quickpractice_attempts', $attempt);

        // Pre-create blank response records
        foreach ($qids as $qid) {
            $q = $DB->get_record('quickpractice_questions', ['id' => $qid], 'id, score');
            $DB->insert_record('quickpractice_responses', (object)[
                'attemptid'    => $attempt->id,
                'questionid'   => $qid,
                'response'     => null,
                'score'        => 0,
                'maxscore'     => $q->score,
                'correct'      => null,
                'timeanswered' => null,
            ]);
        }

        // Set maxscore
        $maxscore = $DB->get_field_select('quickpractice_questions', 'SUM(score)',
            'quickpracticeid = ?', [$qp->id]);
        $DB->set_field('quickpractice_attempts', 'maxscore', (float)$maxscore, ['id' => $attempt->id]);
        $attempt->maxscore = (float)$maxscore;

        return $attempt;
    }

    /**
     * Get ordered questions for an attempt.
     */
    public function get_attempt_questions($attempt) {
        global $DB;
        $order = json_decode($attempt->questionorder ?? '[]', true);
        if (empty($order)) {
            $order = $DB->get_fieldset_select('quickpractice_questions',
                'id', 'quickpracticeid = ?', [$this->quickpractice->id], 'sortorder,id');
        }
        $questions = [];
        foreach ($order as $qid) {
            $q = $DB->get_record('quickpractice_questions', ['id' => $qid]);
            if ($q) { $questions[] = $q; }
        }
        return $questions;
    }

    /**
     * Finish an attempt: compute total, update state.
     */
    public function finish_attempt($attempt, $grader) {
        global $DB;

        // Sum up scores
        $score = (float)$DB->get_field_select('quickpractice_responses',
            'SUM(score)', 'attemptid = ?', [$attempt->id]);
        $now   = time();
        $dur   = $now - $attempt->timecreated;

        $DB->update_record('quickpractice_attempts', (object)[
            'id'           => $attempt->id,
            'state'        => 'finished',
            'score'        => $score,
            'timefinished' => $now,
            'duration'     => $dur,
        ]);

        // Push to gradebook
        $quickpractice = $this->quickpractice;
        quickpractice_update_grades($quickpractice, $attempt->userid);
    }
}
