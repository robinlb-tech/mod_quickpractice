<?php
/**
 * Grader — auto-grade student responses
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpractice\local;

defined('MOODLE_INTERNAL') || die();

class grader {

    private $quickpractice;

    public function __construct($quickpractice) {
        $this->quickpractice = $quickpractice;
    }

    /**
     * Save a student's response and auto-grade it.
     *
     * @param int      $attemptid
     * @param \stdClass $question
     * @param mixed    $response  raw POST value
     */
    public function save_response($attemptid, $question, $response) {
        global $DB;

        $responseJson = is_array($response)
            ? json_encode(array_map('trim', $response), JSON_UNESCAPED_UNICODE)
            : json_encode(trim((string)($response ?? '')), JSON_UNESCAPED_UNICODE);

        list($score, $correct, $feedback) = $this->grade($question, $response);

        $existing = $DB->get_record('quickpractice_responses', [
            'attemptid'  => $attemptid,
            'questionid' => $question->id,
        ]);

        $data = (object)[
            'response'     => $responseJson,
            'score'        => $score,
            'maxscore'     => (float)$question->score,
            'correct'      => $correct,
            'feedback'     => $feedback,
            'timeanswered' => time(),
        ];

        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('quickpractice_responses', $data);
        } else {
            $data->attemptid  = $attemptid;
            $data->questionid = $question->id;
            $DB->insert_record('quickpractice_responses', $data);
        }
    }

    /**
     * Grade a single question response.
     * Returns [score, correct (int|null), feedback (string)]
     */
    public function grade($q, $response) {
        $maxscore = (float)$q->score;
        $answers  = json_decode($q->answers ?? '[]', true) ?: [];
        $feedback = $q->feedback ?? '';

        switch ($q->qtype) {
            case 'multichoice':
                return $this->grade_single($response, $answers, $maxscore, $feedback);

            case 'multianswer':
                return $this->grade_multi($response, $answers, $maxscore, $feedback);

            case 'truefalse':
                return $this->grade_single($response, $answers, $maxscore, $feedback);

            case 'shortanswer':
                return $this->grade_shortanswer($response, $answers, $maxscore, $feedback);

            case 'numerical':
                return $this->grade_numerical($response, $answers, $maxscore, $feedback);

            case 'matching':
                return $this->grade_matching($response, $answers, $maxscore, $feedback);

            case 'essay':
                // Manual grading required — score 0, correct null
                return [0, null, $feedback];

            default:
                return [0, null, $feedback];
        }
    }

    // ── 私有评分方法 ──────────────────────────────────────

    private function grade_single($response, array $answers, $max, $fb) {
        $given = trim((string)($response ?? ''));
        if (empty($given)) { return [0, 0, $fb]; }
        foreach ($answers as $ans) {
            if ($this->compare($given, $ans)) {
                return [$max, 1, $fb];
            }
        }
        return [0, 0, $fb];
    }

    private function grade_multi($response, array $answers, $max, $fb) {
        // ── 多选题评分规则 ──
        // 1. 未作答 → 0分
        // 2. 全部正确答案全选、无错误 → 满分
        // 3. 部分正确（只选了部分正确答案，无错误）→ 满分 × 选对数 / 正确总数
        // 4. 有误选（选了错误答案）→ 在部分分基础上按相同比率扣除（不低于0）

        if (!is_array($response) || count($response) === 0) {
            return [0, 0, $fb];
        }

        $given   = array_values(array_filter(array_map('trim', $response)));
        $correct = array_values(array_map('trim', $answers));
        $totalCorrect = count($correct);

        if ($totalCorrect === 0) {
            return [0, null, $fb];
        }

        // 全对判断
        $givenSorted   = $given;
        $correctSorted = $correct;
        sort($givenSorted);
        sort($correctSorted);
        if ($givenSorted === $correctSorted) {
            return [$max, 1, $fb];
        }

        // 统计命中正确答案数量（去重，防止同一选项被多次统计）
        $hitCount   = 0;
        $matchedIdx = [];
        foreach ($given as $g) {
            foreach ($correct as $ci => $c) {
                if (!isset($matchedIdx[$ci]) && $this->compare($g, $c)) {
                    $hitCount++;
                    $matchedIdx[$ci] = true;
                    break;
                }
            }
        }

        // 统计误选数（选了不在正确答案里的选项）
        $wrongCount = 0;
        foreach ($given as $g) {
            $isInCorrect = false;
            foreach ($correct as $c) {
                if ($this->compare($g, $c)) { $isInCorrect = true; break; }
            }
            if (!$isInCorrect) { $wrongCount++; }
        }

        // 每选对一个的得分单位
        $perAns = $max / $totalCorrect;

        // 得分 = 选对得分 - 误选扣分（不低于0）
        $score = $perAns * $hitCount - $perAns * $wrongCount;
        $score = max(0.0, $score);
        $score = round($score, 2);

        $isCorrect = ($score >= $max) ? 1 : 0;
        return [$score, $isCorrect, $fb];
    }

    private function grade_shortanswer($response, array $answers, $max, $fb) {
        $given = trim((string)($response ?? ''));
        if (empty($given)) { return [0, 0, $fb]; }
        foreach ($answers as $ans) {
            if ($this->compare($given, $ans)) {
                return [$max, 1, $fb];
            }
        }
        // Fuzzy match (ignore punctuation/spaces)
        $givenNorm = preg_replace('/\s+/', '', mb_strtolower($given));
        foreach ($answers as $ans) {
            $ansNorm = preg_replace('/\s+/', '', mb_strtolower(trim($ans)));
            if ($givenNorm === $ansNorm) {
                return [$max, 1, $fb];
            }
        }
        return [0, 0, $fb];
    }

    private function grade_numerical($response, array $answers, $max, $fb) {
        $given = trim((string)($response ?? ''));
        if ($given === '' || !is_numeric($given)) { return [0, 0, $fb]; }
        $givenVal = (float)$given;
        foreach ($answers as $ans) {
            // Support range: "42:5" means 42 ± 5
            if (strpos($ans, ':') !== false) {
                list($center, $tolerance) = explode(':', $ans, 2);
                if (abs($givenVal - (float)$center) <= (float)$tolerance) {
                    return [$max, 1, $fb];
                }
            } elseif (abs($givenVal - (float)$ans) < 0.0001) {
                return [$max, 1, $fb];
            }
        }
        return [0, 0, $fb];
    }

    private function grade_matching($response, array $correctPairs, $max, $fb) {
        if (!is_array($response)) { return [0, 0, $fb]; }
        $per    = $max / max(1, count($correctPairs));
        $score  = 0.0;
        foreach ($correctPairs as $pair) {
            list($left, $right) = explode('->', $pair, 2);
            $left  = trim($left);
            $key   = 'match_' . md5($left);
            $given = trim((string)($response[$key] ?? ''));
            if ($this->compare($given, trim($right))) {
                $score += $per;
            }
        }
        return [round($score, 2), ($score >= $max ? 1 : 0), $fb];
    }

    private function compare($a, $b) {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }
}
