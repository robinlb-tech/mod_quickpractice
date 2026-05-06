<?php
/**
 * Report Generator — analyse attempt data and produce teaching assessment report
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpractice\local;

defined('MOODLE_INTERNAL') || die();

class report_generator {

    private $quickpractice;
    private $DB;

    public function __construct($quickpractice, $DB) {
        $this->quickpractice = $quickpractice;
        $this->DB            = $DB;
    }

    /**
     * Generate a full assessment report data array.
     *
     * @param string $classname  empty = all classes
     * @param string $type       class|student|question
     */
    public function generate($classname = '', $type = 'class') {
        $qp = $this->quickpractice;

        $clsWhere  = '';
        $clsParams = ['qpid' => $qp->id, 'st' => 'finished'];
        if ($classname) {
            $clsWhere  = " AND a.classname = :cls";
            $clsParams['cls'] = $classname;
        }

        // ── 1. Summary ────────────────────────────────────
        $sumSQL = "SELECT COUNT(DISTINCT a.userid) AS student_count,
                          AVG(a.score / NULLIF(a.maxscore,0) * 100) AS avg_score,
                          MAX(a.score / NULLIF(a.maxscore,0) * 100) AS max_score,
                          MIN(a.score / NULLIF(a.maxscore,0) * 100) AS min_score,
                          AVG(a.duration) AS avg_duration,
                          SUM(CASE WHEN a.score / NULLIF(a.maxscore,0) * 100 >= :pass THEN 1 ELSE 0 END) * 100.0
                              / NULLIF(COUNT(*),0) AS pass_rate
                     FROM {quickpractice_attempts} a
                    WHERE a.quickpracticeid = :qpid AND a.state = :st {$clsWhere}";
        $clsParams['pass'] = $qp->passscore;
        $sumRow = $this->DB->get_record_sql($sumSQL, $clsParams) ?: new \stdClass();

        $summary = [
            'student_count' => (int)($sumRow->student_count ?? 0),
            'avg_score'     => round((float)($sumRow->avg_score ?? 0), 2),
            'max_score'     => round((float)($sumRow->max_score ?? 0), 2),
            'min_score'     => round((float)($sumRow->min_score ?? 0), 2),
            'avg_duration'  => (int)($sumRow->avg_duration ?? 0),
            'pass_rate'     => round((float)($sumRow->pass_rate ?? 0), 2),
        ];

        // ── 2. Score Distribution ─────────────────────────
        $distSQL = "SELECT a.score / NULLIF(a.maxscore,0) * 100 AS pct
                      FROM {quickpractice_attempts} a
                     WHERE a.quickpracticeid = :qpid AND a.state = :st {$clsWhere}";
        unset($clsParams['pass']);
        if ($classname) { $clsParams['cls'] = $classname; }
        $rows = $this->DB->get_records_sql($distSQL, $clsParams);

        $dist = ['0-59' => 0, '60-69' => 0, '70-79' => 0, '80-89' => 0, '90-100' => 0];
        foreach ($rows as $r) {
            $p = (float)$r->pct;
            if ($p < 60)      $dist['0-59']++;
            elseif ($p < 70)  $dist['60-69']++;
            elseif ($p < 80)  $dist['70-79']++;
            elseif ($p < 90)  $dist['80-89']++;
            else              $dist['90-100']++;
        }

        // ── 3. Question Analysis ──────────────────────────
        $qaSQL = "SELECT q.id, q.questiontext, q.difficulty, q.category, q.qtype,
                         COUNT(r.id) AS total,
                         SUM(CASE WHEN r.correct = 1 THEN 1 ELSE 0 END) AS correct_count
                    FROM {quickpractice_questions} q
                    JOIN {quickpractice_responses} r ON r.questionid = q.id
                    JOIN {quickpractice_attempts}  a ON a.id = r.attemptid
                   WHERE q.quickpracticeid = :qpid AND a.state = :st {$clsWhere}
                   GROUP BY q.id, q.questiontext, q.difficulty, q.category, q.qtype
                   ORDER BY q.sortorder";
        $clsParams2 = ['qpid' => $qp->id, 'st' => 'finished'];
        if ($classname) { $clsParams2['cls'] = $classname; }
        $qaRows = $this->DB->get_records_sql($qaSQL, $clsParams2);

        $qanalysis = [];
        foreach ($qaRows as $qa) {
            $rate       = $qa->total > 0 ? round($qa->correct_count / $qa->total * 100, 1) : 0;
            $qanalysis[] = [
                'id'           => $qa->id,
                'label'        => mb_strimwidth(strip_tags($qa->questiontext), 0, 40, '…'),
                'correct_rate' => $rate,
                'difficulty'   => (int)($qa->difficulty ?? 2),
                'category'     => $qa->category ?? '',
                'qtype'        => $qa->qtype,
            ];
        }

        // ── 4. Weak / Strong Points by Category ──────────
        $catStats = [];
        foreach ($qanalysis as $qa) {
            $cat = $qa['category'] ?: '未分类';
            if (!isset($catStats[$cat])) {
                $catStats[$cat] = ['rates' => [], 'count' => 0];
            }
            $catStats[$cat]['rates'][] = $qa['correct_rate'];
            $catStats[$cat]['count']++;
        }

        $weakPoints   = [];
        $strongPoints = [];
        foreach ($catStats as $cat => $cs) {
            $avg = count($cs['rates']) ? array_sum($cs['rates']) / count($cs['rates']) : 0;
            $entry = ['category' => $cat, 'rate' => round($avg, 1), 'count' => $cs['count']];
            if ($avg < 60) {
                $weakPoints[] = $entry;
            } elseif ($avg >= 80) {
                $strongPoints[] = $entry;
            }
        }

        // Sort weak points ascending, strong points descending
          usort($weakPoints,   function($a, $b) { return $a['rate'] <=> $b['rate']; });
          usort($strongPoints, function($a, $b) { return $b['rate'] <=> $a['rate']; });

        // ── 5. Teaching Suggestions ───────────────────────
        $suggestions = $this->generate_suggestions($summary, $weakPoints, $qanalysis);

        // ── 6. Class Breakdown (if all classes) ──────────
        $classBreakdown = [];
        if (!$classname) {
            $cbSQL = "SELECT a.classname,
                             COUNT(DISTINCT a.userid) AS cnt,
                             AVG(a.score / NULLIF(a.maxscore,0) * 100) AS avg
                        FROM {quickpractice_attempts} a
                       WHERE a.quickpracticeid = :qpid AND a.state = 'finished'
                         AND a.classname IS NOT NULL AND a.classname <> ''
                       GROUP BY a.classname
                       ORDER BY avg DESC";
            $cbRows = $this->DB->get_records_sql($cbSQL, ['qpid' => $qp->id]);
            foreach ($cbRows as $cb) {
                $classBreakdown[] = [
                    'classname' => $cb->classname,
                    'count'     => (int)$cb->cnt,
                    'avg'       => round((float)$cb->avg, 1),
                ];
            }
        }

        return [
            'summary'          => $summary,
            'distribution'     => $dist,
            'question_analysis' => $qanalysis,
            'weak_points'      => $weakPoints,
            'strong_points'    => $strongPoints,
            'suggestions'      => $suggestions,
            'class_breakdown'  => $classBreakdown,
            'generated_at'     => time(),
        ];
    }

    /**
     * Build human-readable teaching suggestions from analytics.
     */
    private function generate_suggestions(array $summary, array $weak, array $qanalysis) {
        $s = [];

        // 1. Overall pass rate
        if ($summary['pass_rate'] < 60) {
            $s[] = '班级整体及格率偏低（' . $summary['pass_rate'] . '%），建议组织全班专项复习，重点巩固基础知识。';
        } elseif ($summary['pass_rate'] < 80) {
            $s[] = '班级及格率为 ' . $summary['pass_rate'] . '%，建议针对未及格学生开展个别辅导。';
        } else {
            $s[] = '班级整体表现良好，及格率达 ' . $summary['pass_rate'] . '%，可适当提升训练难度。';
        }

        // 2. Weak points
        if (!empty($weak)) {
            $categories = implode('、', array_column(array_slice($weak, 0, 3), 'category'));
            $s[] = "以下知识点正确率较低，建议重点讲解：{$categories}。";
        }

        // 3. Very difficult questions (< 40%)
        $hardQ = array_filter($qanalysis, function($q) { return $q['correct_rate'] < 40; });
        if (!empty($hardQ)) {
            $s[] = '共有 ' . count($hardQ) . ' 道题正确率低于 40%，建议课堂重点讲解这些题目并增加相关练习。';
        }

        // 4. Duration
        if ($summary['avg_duration'] > 3600) {
            $s[] = '学生平均用时较长，建议在后续练习中增加计时训练，提高解题效率。';
        }

        // 5. Score spread
        if (($summary['max_score'] - $summary['min_score']) > 50) {
            $s[] = '班级成绩分化较大（最高分与最低分相差 ' . round($summary['max_score'] - $summary['min_score'], 1) . '%），建议开展分层教学。';
        }

        return $s;
    }
}
