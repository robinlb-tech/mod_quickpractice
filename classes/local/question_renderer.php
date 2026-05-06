<?php
/**
 * Question Renderer — outputs HTML for each question type during attempt
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpractice\local;

defined('MOODLE_INTERNAL') || die();

class question_renderer {

    /**
     * Render a question for the attempt page.
     */
    public function render_question($q, $num) {
        // 题型角标（多选/判断/简答等给出提示）
        $typeLabel = '';
        switch ($q->qtype) {
            case 'multianswer':
                $typeLabel = '<span class="qp-qtype-badge qp-badge-multi">【多选题】</span>';
                break;
            case 'truefalse':
                $typeLabel = '<span class="qp-qtype-badge qp-badge-tf">【判断题】</span>';
                break;
            case 'shortanswer':
                $typeLabel = '<span class="qp-qtype-badge qp-badge-sa">【简答题】</span>';
                break;
            case 'numerical':
                $typeLabel = '<span class="qp-qtype-badge qp-badge-num">【数值题】</span>';
                break;
            case 'matching':
                $typeLabel = '<span class="qp-qtype-badge qp-badge-match">【连线题】</span>';
                break;
            case 'essay':
                $typeLabel = '<span class="qp-qtype-badge qp-badge-essay">【论述题】</span>';
                break;
            // multichoice 不加标签（默认单选，学生已知）
        }
        $html  = $typeLabel . '<div class="qp-q-text">' . format_text($q->questiontext, $q->questionformat) . '</div>';
        $html .= '<div class="qp-q-input">';

        switch ($q->qtype) {
            case 'multichoice':
                $html .= $this->render_radio($q);
                break;
            case 'multianswer':
                $html .= $this->render_checkbox($q);
                break;
            case 'truefalse':
                $html .= $this->render_truefalse($q);
                break;
            case 'shortanswer':
                $html .= $this->render_shortanswer($q);
                break;
            case 'numerical':
                $html .= $this->render_numerical($q);
                break;
            case 'matching':
                $html .= $this->render_matching($q);
                break;
            case 'essay':
                $html .= $this->render_essay($q);
                break;
            default:
                $html .= '<input type="text" name="q_' . $q->id . '" class="form-control">';
        }

        $html .= '</div>';
        return $html;
    }

    private function render_radio($q) {
        $options = json_decode($q->options ?? '[]', true) ?: [];
        shuffle($options);  // 随机排序
        $html    = '<div class="qp-choices qp-radio">';
        foreach ($options as $idx => $opt) {
            $eid   = 'q_' . $q->id . '_' . $idx;
            $label = $this->option_label($idx) . '. ' . htmlspecialchars($opt);
            $html .= '<label class="qp-choice" for="' . $eid . '">'
                . '<input type="radio" id="' . $eid . '" name="q_' . $q->id . '" value="' . htmlspecialchars($opt) . '"> '
                . $label . '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    private function render_checkbox($q) {
        $options = json_decode($q->options ?? '[]', true) ?: [];
        shuffle($options);  // 随机排序
        $html    = '<div class="qp-choices qp-checkbox"><small class="qp-multi-hint">请选择所有正确答案（多选）</small>';
        foreach ($options as $idx => $opt) {
            $eid   = 'q_' . $q->id . '_' . $idx;
            $label = $this->option_label($idx) . '. ' . htmlspecialchars($opt);
            $html .= '<label class="qp-choice" for="' . $eid . '">'
                . '<input type="checkbox" id="' . $eid . '" name="q_' . $q->id . '[]" value="' . htmlspecialchars($opt) . '"> '
                . $label . '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    private function render_truefalse($q) {
        $html  = '<div class="qp-choices qp-truefalse">';
        foreach (['正确', '错误'] as $val) {
            $id    = 'q_' . $q->id . '_' . $val;
            $html .= '<label class="qp-choice" for="' . $id . '">'
                . '<input type="radio" id="' . $id . '" name="q_' . $q->id . '" value="' . $val . '"> '
                . $val . '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    private function render_shortanswer($q) {
        return '<input type="text" name="q_' . $q->id . '" class="form-control qp-text-input" placeholder="请输入答案…" autocomplete="off">';
    }

    private function render_numerical($q) {
        return '<input type="number" step="any" name="q_' . $q->id . '" class="form-control qp-num-input" placeholder="请输入数字">';
    }

    private function render_matching($q) {
        $pairs = json_decode($q->options ?? '[]', true) ?: [];
        // Collect all right-side options
          $rights = array_map(function($p) { return trim(explode('->', $p, 2)[1] ?? $p); }, $pairs);
          $lefts  = array_map(function($p) { return trim(explode('->', $p, 2)[0] ?? $p); }, $pairs);
        shuffle($rights);

        $html = '<table class="qp-matching-table">';
        $html .= '<thead><tr><th>左侧项</th><th>对应</th></tr></thead><tbody>';
        foreach ($lefts as $left) {
            $key  = 'match_' . md5($left);
            $html .= '<tr><td>' . htmlspecialchars($left) . '</td><td>'
                . '<select name="q_' . $q->id . '[' . $key . ']" class="form-control">'
                . '<option value="">— 选择 —</option>';
            foreach ($rights as $r) {
                $html .= '<option value="' . htmlspecialchars($r) . '">' . htmlspecialchars($r) . '</option>';
            }
            $html .= '</select></td></tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    private function render_essay($q) {
        return '<textarea name="q_' . $q->id . '" class="form-control qp-essay-input" rows="6" '
            . 'placeholder="请在此处作答…"></textarea>'
            . '<small class="text-muted">（此题需教师人工评分）</small>';
    }

    private function option_label($idx) {
        return chr(65 + $idx);  // A, B, C, D…
    }
}
