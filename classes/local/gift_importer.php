<?php
/**
 * GIFT format importer for QuickPractice
 *
 * Supports: Multiple Choice, True/False, Short Answer, Numerical,
 *           Matching, Fill-in-the-blank (Cloze), Essay
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpractice\local;

defined('MOODLE_INTERNAL') || die();

class gift_importer {

    private $quickpracticeid;
    private $errors = [];
    private $sortorder = 0;
    private $currentCategory = '';  // Track current $CATEGORY: value

    public function __construct($quickpracticeid) {
        $this->quickpracticeid = $quickpracticeid;
        global $DB;
        $this->sortorder = $DB->count_records('quickpractice_questions', ['quickpracticeid' => $quickpracticeid]);
    }

    /**
     * Parse and import GIFT text. Returns count of imported questions.
     */
    public function import($gifttext) {
        global $DB;

        // 标准化换行
        $gifttext = str_replace(["\r\n", "\r"], "\n", $gifttext);
        // 移除 BOM
        $gifttext = ltrim($gifttext, "\xEF\xBB\xBF");

        $blocks = $this->split_into_blocks($gifttext);
        $count  = 0;

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) { continue; }

            try {
                $q = $this->parse_block($block);
                if ($q) {
                    $q->quickpracticeid = $this->quickpracticeid;
                    $q->sortorder       = $this->sortorder++;
                    $q->timecreated     = time();
                    $q->source          = 'gift';
                    $DB->insert_record('quickpractice_questions', $q);
                    $count++;
                }
            } catch (Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        return $count;
    }

    public function get_errors() {
        return $this->errors;
    }

    // ── 私有方法 ───────────────────────────────────────────

    /**
     * Split full GIFT text into individual question blocks.
     */
    private function split_into_blocks($text) {
        // 移除注释行（// 开头）
        $lines = explode("\n", $text);
        $clean = [];
        foreach ($lines as $line) {
            if (strncmp(ltrim($line), '//', 2) !== 0) {
                $clean[] = $line;
            }
        }
        $text = implode("\n", $clean);

        // 以空行分割题块
        $blocks = preg_split('/\n{2,}/', $text);
        return $blocks ?: [];
    }

    /**
     * Parse a single GIFT question block into a stdClass object.
     */
    private function parse_block($block) {
        $q = new \stdClass();
        $q->questionformat = FORMAT_HTML;
        $q->feedback       = '';
        $q->category       = $this->currentCategory;
        $q->difficulty     = 2;

        // ── 提取分类标签 $CATEGORY: ──
        if (preg_match('/^\$CATEGORY:\s*(.+)$/mi', $block, $m)) {
            $this->currentCategory = trim($m[1]);
            return null; // 跳过分类声明行本身
        }

        // ── 提取标题 :: title :: ──
        $title = '';
        if (preg_match('/^::(.+?)::/s', $block, $m)) {
            $title = trim($m[1]);
            $block = preg_replace('/^::(.+?)::/s', '', $block, 1);
        }

        // ── 提取文本格式 [html] [plain] [markdown] ──
        if (preg_match('/^\[(\w+)\]/s', ltrim($block), $m)) {
            $block = ltrim(preg_replace('/^\[(\w+)\]/', '', ltrim($block), 1));
        }

        // ── 找到 { } 块位置 ──
        $braceStart = strpos($block, '{');
        $braceEnd   = strrpos($block, '}');

        if ($braceStart === false || $braceEnd === false) {
            // 无大括号 → Essay / description
            $q->qtype        = 'essay';
            $q->questiontext = trim($block);
            $q->options      = json_encode([]);
            $q->answers      = json_encode([]);
            $q->score        = 1;
            return $q;
        }

        $questiontext = trim(substr($block, 0, $braceStart));
        $inner        = substr($block, $braceStart + 1, $braceEnd - $braceStart - 1);
        $afterBrace   = trim(substr($block, $braceEnd + 1));

        // ── 从大括号内提取题目整体 feedback（#### 语法）──
        // GIFT: {=A ~B ####这道题的整体反馈}
        if (preg_match('/####\s*(.+)$/su', $inner, $fbm)) {
            $q->feedback = trim($fbm[1]);
            $inner = preg_replace('/####.+$/su', '', $inner);
        }

        // ── 填空题：题目中含 __ ──
        if (strpos($questiontext, '___') !== false || $questiontext === '') {
            // Fill-in variant: treat as short answer
            $questiontext = str_replace('___', '____', $questiontext);
        }

        $q->questiontext = $questiontext ?: $title;

        // ── 解析大括号内内容 ──
        $inner = trim($inner);

        // True/False
        if (in_array(strtoupper($inner), ['TRUE', 'FALSE', 'T', 'F', '正确', '错误', '对', '错'])) {
            $q->qtype   = 'truefalse';
            $q->options = json_encode(['正确', '错误']);
            $trueVals   = ['TRUE', 'T', '正确', '对'];
            $q->answers = json_encode([in_array(strtoupper($inner), $trueVals) ? '正确' : '错误']);
            $q->score   = 1;
            return $q;
        }

        // Numerical: {#123} or {#100:5}
        if (preg_match('/^#(.+)$/', $inner, $nm)) {
            $q->qtype   = 'numerical';
            $q->options = json_encode([]);
            $numparts   = explode(':', trim($nm[1]), 2);
            $q->answers = json_encode([$numparts[0]]);
            $q->score   = 1;
            return $q;
        }

        // Short answer (plain, no = prefix but no choices)
        // Multiple choice or multi-answer
        if (strpos($inner, '~') !== false || strpos($inner, '=') !== false) {
            return $this->parse_choice($q, $inner);
        }

        // Matching: { A -> B \n C -> D }
        if (strpos($inner, '->') !== false) {
            return $this->parse_matching($q, $inner);
        }

        // Plain short answer
        $q->qtype   = 'shortanswer';
        $q->options = json_encode([]);
        $q->answers = json_encode([trim($inner, '= ')]);
        $q->score   = 1;
        return $q;
    }

    /**
     * Parse multiple choice / multi-answer question.
     * Choices start with = (correct) or ~ (wrong).
     */
    private function parse_choice($q, $inner) {
        // GIFT 选择题解析
        // 格式：= 表示正确选项，~ 表示错误选项
        // 百分比权重：=%50%文本（多选部分分）、~%-25%文本（多选错误惩罚）
        // per-choice feedback：选项文本#反馈

        $options         = [];
        $answers         = [];
        $correctFeedback = '';

        // ── 核心正则：匹配每个选项 ──
        // 支持：=文本、~文本、=%50%文本、~%-25%文本
        // 注意：百分比 % 符号必须在首位才认定为权重，防止选项文本中含 % 被误截
        preg_match_all(
            '/(?<=[~=]|^)([~=])((?:%-?[\d.]+%)?[^~=]*)/u',
            ' ' . $inner,   // 前置空格确保首个选项也能被匹配
            $rawMatches,
            PREG_SET_ORDER
        );

        // 备用方案：若上面正则匹配为空，用简单分割
        if (empty($rawMatches)) {
            preg_match_all('/([~=])([^~=]+)/u', $inner, $rawMatches, PREG_SET_ORDER);
        }

        foreach ($rawMatches as $m) {
            $prefix  = $m[1];
            $content = trim($m[2]);

            // ── 解析百分比权重 ──
            // 格式：%50%  或  %-25%  （百分比可以是负数，表示错误惩罚）
            $fraction = null;  // null 表示未指定权重
            if (preg_match('/^%(-?\d+(?:\.\d+)?)%\s*(.*)/su', $content, $pct)) {
                $fraction = (float)$pct[1] / 100.0;
                $content  = trim($pct[2]);
            }

            // ── 提取 per-choice feedback（# 后面的内容）──
            $choiceFeedback = '';
            // 避免把 HTML 实体 &amp; 等的 # 号误判，只处理文本型 # 分隔符
            if (strpos($content, '#') !== false) {
                $parts          = explode('#', $content, 2);
                $content        = trim($parts[0]);
                $choiceFeedback = trim($parts[1] ?? '');
            }

            if ($content === '') { continue; }

            $options[] = $content;

            // ── 判断是否为正确答案 ──
            // 规则：
            //   1. prefix='=' 且无权重 → 正确（fraction=1.0）
            //   2. prefix='=' 且权重>0  → 正确（部分分，多选常见）
            //   3. prefix='~' 且权重>0  → 正确（部分分，如 ~%50%）
            //   4. prefix='~' 且无权重或权重<=0 → 错误选项
            $isCorrect = false;
            if ($prefix === '=') {
                $isCorrect = true;
                if ($fraction === null) { $fraction = 1.0; }
            } elseif ($prefix === '~' && $fraction !== null && $fraction > 0) {
                $isCorrect = true;
            }

            if ($isCorrect) {
                $answers[] = $content;
                if ($choiceFeedback !== '' && $correctFeedback === '') {
                    $correctFeedback = $choiceFeedback;
                }
            }
        }

        // 若题目 feedback 为空，用正确选项的 per-choice feedback 补充
        if (empty($q->feedback) && $correctFeedback !== '') {
            $q->feedback = $correctFeedback;
        }

        // ── 题型判断 ──
        // 多选条件：有 2 个及以上正确答案
        $isMulti    = count($answers) > 1;
        $q->qtype   = $isMulti ? 'multianswer' : 'multichoice';
        $q->options = json_encode(array_values($options), JSON_UNESCAPED_UNICODE);
        $q->answers = json_encode(array_values($answers), JSON_UNESCAPED_UNICODE);
        $q->score   = 1.0;
        return $q;
    }

    /**
     * Parse matching question: A -> B \n C -> D
     */
    private function parse_matching($q, $inner) {
        $lines   = preg_split('/\n|\\\\n/', $inner);
        $pairs   = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) { continue; }
            if (strpos($line, '->') !== false) {
                list($left, $right) = explode('->', $line, 2);
                // 去掉前导 =
                $left  = ltrim(trim($left),  '=~');
                $right = ltrim(trim($right), '=~');
                $pairs[] = trim($left) . ' -> ' . trim($right);
            }
        }
        $q->qtype   = 'matching';
        $q->options = json_encode($pairs, JSON_UNESCAPED_UNICODE);
        $q->answers = json_encode($pairs, JSON_UNESCAPED_UNICODE);
        $q->score   = count($pairs);
        return $q;
    }
}
