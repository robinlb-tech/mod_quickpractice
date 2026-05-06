<?php
/**
 * Question Bank management page
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quickpractice/lib.php');
require_once($CFG->dirroot . '/mod/quickpractice/classes/local/gift_importer.php');

$id     = required_param('id', PARAM_INT);
$action = optional_param('action', 'list', PARAM_ALPHA);   // list|add|edit|delete|import|quizimport|preview
$qid    = optional_param('qid', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'quickpractice');
$quickpractice = $DB->get_record('quickpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quickpractice:managequestions', $context);

$PAGE->set_url('/mod/quickpractice/questionbank.php', ['id' => $id]);
$PAGE->set_title(get_string('questionbank', 'quickpractice'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/quickpractice/styles.css');

// ─────────────────────────── ACTIONS ────────────────────

// ── 删除题目 ──────────────────────────────────────────────
if ($action === 'delete' && $qid && confirm_sesskey()) {
    $DB->delete_records('quickpractice_questions', ['id' => $qid, 'quickpracticeid' => $quickpractice->id]);
    $DB->delete_records('quickpractice_responses', ['questionid' => $qid]);
    redirect(new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id]), '已删除');
}

// ── 保存题目（新建或编辑） ────────────────────────────────
if ($action === 'save' && confirm_sesskey()) {
    $qtype    = required_param('qtype', PARAM_ALPHA);
    $qtext    = required_param('questiontext', PARAM_RAW);
    $rawOpts  = optional_param('options', '', PARAM_RAW);
    $rawAns   = optional_param('answers', '', PARAM_RAW);
    $feedback = optional_param('feedback', '', PARAM_RAW);
    $score    = optional_param('score', 1.0, PARAM_FLOAT);
    $diff     = optional_param('difficulty', 2, PARAM_INT);
    $category = optional_param('category', '', PARAM_TEXT);

    // 将多行文本转成JSON数组
    $options = array_values(array_filter(array_map('trim', explode("\n", $rawOpts))));
    $answers = array_values(array_filter(array_map('trim', explode("\n", $rawAns))));

    $record = (object)[
        'quickpracticeid' => $quickpractice->id,
        'qtype'           => $qtype,
        'questiontext'    => $qtext,
        'questionformat'  => FORMAT_HTML,
        'options'         => json_encode($options, JSON_UNESCAPED_UNICODE),
        'answers'         => json_encode($answers, JSON_UNESCAPED_UNICODE),
        'feedback'        => $feedback,
        'score'           => $score,
        'difficulty'      => $diff,
        'category'        => $category,
        'source'          => 'manual',
        'timecreated'     => time(),
    ];

    if ($qid) {
        $record->id = $qid;
        $DB->update_record('quickpractice_questions', $record);
    } else {
        $record->sortorder = $DB->count_records('quickpractice_questions', ['quickpracticeid' => $quickpractice->id]);
        $DB->insert_record('quickpractice_questions', $record);
    }
    // 更新题数缓存
    $DB->set_field('quickpractice', 'questioncount',
        $DB->count_records('quickpractice_questions', ['quickpracticeid' => $quickpractice->id]),
        ['id' => $quickpractice->id]);

    redirect(new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id]),
        $qid ? '题目已更新' : '题目已添加');
}

// ── GIFT 文件导入 ─────────────────────────────────────────
if ($action === 'doimport' && confirm_sesskey()) {
    $importer = new \mod_quickpractice\local\gift_importer($quickpractice->id);
    $gifttext = '';

    if (!empty($_FILES['giftfile']['tmp_name'])) {
        $gifttext = file_get_contents($_FILES['giftfile']['tmp_name']);
        $enc = mb_detect_encoding($gifttext, ['UTF-8', 'GBK', 'GB2312', 'BIG5'], true);
        if ($enc && $enc !== 'UTF-8') {
            $gifttext = mb_convert_encoding($gifttext, 'UTF-8', $enc);
        }
    } else {
        $gifttext = optional_param('gifttextarea', '', PARAM_RAW);
    }

    if ($gifttext) {
        $count = $importer->import($gifttext);
        $DB->set_field('quickpractice', 'questioncount',
            $DB->count_records('quickpractice_questions', ['quickpracticeid' => $quickpractice->id]),
            ['id' => $quickpractice->id]);
        redirect(new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id]),
            get_string('importcount', 'quickpractice', $count));
    }
}

// ── 从 Moodle Quiz 模块导入 ───────────────────────────────
if ($action === 'doquizimport' && confirm_sesskey()) {
    require_capability('mod/quickpractice:import', $context);
    $quizid = required_param('quizid', PARAM_INT);
    $count  = _quickpractice_import_from_quiz($quickpractice->id, $quizid, $DB);
    $DB->set_field('quickpractice', 'questioncount',
        $DB->count_records('quickpractice_questions', ['quickpracticeid' => $quickpractice->id]),
        ['id' => $quickpractice->id]);
    redirect(new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id]),
        get_string('importcount', 'quickpractice', $count));
}

// ─────────────────────────── OUTPUT ─────────────────────
echo $OUTPUT->header();
echo '<div class="qp-qbank-page">';
echo '<h2>📚 ' . get_string('questionbank', 'quickpractice') . ' — ' . format_string($quickpractice->name) . '</h2>';

// ── 编辑/新建题目表单 ─────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
    $q = $qid ? $DB->get_record('quickpractice_questions',
        ['id' => $qid, 'quickpracticeid' => $quickpractice->id], '*', MUST_EXIST) : null;

    $saveurl = new moodle_url('/mod/quickpractice/questionbank.php',
        ['id' => $id, 'action' => 'save', 'qid' => $qid, 'sesskey' => sesskey()]);
    echo '<div class="qp-edit-form card p-4">';
    echo '<h4>' . ($qid ? get_string('editquestion', 'quickpractice') : get_string('addquestion', 'quickpractice')) . '</h4>';
    echo '<form method="post" action="' . $saveurl . '">';

    $qtypes = [
        'multichoice' => get_string('qtype_multichoice', 'quickpractice'),
        'multianswer' => get_string('qtype_multianswer', 'quickpractice'),
        'truefalse'   => get_string('qtype_truefalse', 'quickpractice'),
        'shortanswer' => get_string('qtype_shortanswer', 'quickpractice'),
        'matching'    => get_string('qtype_matching', 'quickpractice'),
        'essay'       => get_string('qtype_essay', 'quickpractice'),
        'numerical'   => get_string('qtype_numerical', 'quickpractice'),
    ];
    echo '<div class="form-group"><label>题型</label><select name="qtype" class="form-control">';
    foreach ($qtypes as $k => $v) {
        $sel = ($q && $q->qtype === $k) ? ' selected' : '';
        echo '<option value="' . $k . '"' . $sel . '>' . $v . '</option>';
    }
    echo '</select></div>';

    echo '<div class="form-group"><label>' . get_string('questiontext', 'quickpractice') . '</label>'
        . '<textarea name="questiontext" class="form-control" rows="4">'
        . htmlspecialchars($q ? $q->questiontext : '') . '</textarea></div>';

    $optsText = $q ? implode("\n", json_decode($q->options ?? '[]', true) ?? []) : '';
    echo '<div class="form-group"><label>' . get_string('questionoptions', 'quickpractice') . '</label>'
        . '<textarea name="options" class="form-control qp-opts" rows="5" placeholder="每行一个选项，例如：&#10;A. 选项内容&#10;B. 选项内容">'
        . htmlspecialchars($optsText) . '</textarea></div>';

    $ansText = $q ? implode("\n", json_decode($q->answers ?? '[]', true) ?? []) : '';
    echo '<div class="form-group"><label>' . get_string('questionanswer', 'quickpractice') . '</label>'
        . '<textarea name="answers" class="form-control" rows="2" placeholder="正确答案，每行一个">'
        . htmlspecialchars($ansText) . '</textarea></div>';

    echo '<div class="form-group"><label>' . get_string('questionfeedback', 'quickpractice') . '</label>'
        . '<textarea name="feedback" class="form-control" rows="3">'
        . htmlspecialchars($q ? $q->feedback : '') . '</textarea></div>';

    echo '<div class="form-row">';
    echo '<div class="form-group col"><label>' . get_string('questionscore', 'quickpractice') . '</label>'
        . '<input type="number" name="score" step="0.5" min="0" class="form-control" value="' . ($q ? $q->score : 1) . '"></div>';

    $diffs = [1 => get_string('difficulty_easy', 'quickpractice'), 2 => get_string('difficulty_medium', 'quickpractice'), 3 => get_string('difficulty_hard', 'quickpractice')];
    echo '<div class="form-group col"><label>' . get_string('questiondifficulty', 'quickpractice') . '</label><select name="difficulty" class="form-control">';
    foreach ($diffs as $dv => $dl) {
        $sel = ($q && $q->difficulty == $dv) ? ' selected' : ($dv == 2 ? ' selected' : '');
        echo '<option value="' . $dv . '"' . $sel . '>' . $dl . '</option>';
    }
    echo '</select></div>';

    echo '<div class="form-group col"><label>' . get_string('questioncategory', 'quickpractice') . '</label>'
        . '<input type="text" name="category" class="form-control" value="' . htmlspecialchars($q ? $q->category : '') . '"></div>';
    echo '</div>';

    echo '<button type="submit" class="btn btn-primary">' . get_string('save', 'quickpractice') . '</button> ';
    echo html_writer::link(new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id]),
        get_string('cancel', 'quickpractice'), ['class' => 'btn btn-secondary']);
    echo '</form></div>';

} elseif ($action === 'import') {
    // ── GIFT 导入界面 ──────────────────────────────────────
    $importurl = new moodle_url('/mod/quickpractice/questionbank.php',
        ['id' => $id, 'action' => 'doimport', 'sesskey' => sesskey()]);
    echo '<div class="qp-import-form card p-4">';
    echo '<h4>📥 ' . get_string('importgift', 'quickpractice') . '</h4>';
    echo '<p>支持 Moodle GIFT 格式（.txt / .gift），可上传文件或直接粘贴文本。</p>';
    echo '<form method="post" action="' . $importurl . '" enctype="multipart/form-data">';
    echo '<div class="form-group"><label>上传 GIFT 文件</label><input type="file" name="giftfile" class="form-control-file" accept=".txt,.gift"></div>';
    echo '<div class="form-group"><label>或直接粘贴 GIFT 内容</label>'
        . '<textarea name="gifttextarea" class="form-control" rows="12" placeholder="::题目标题:: 题目内容 {=正确答案}"></textarea></div>';
    echo '<button type="submit" class="btn btn-primary">开始导入</button>';
    echo '</form></div>';

    // Quiz 导入
    echo '<div class="qp-import-form card p-4 mt-3">';
    echo '<h4>📥 ' . get_string('importfromquiz', 'quickpractice') . '</h4>';
    $quizzes = $DB->get_records_select('quiz', 'course = ?', [$course->id], 'name', 'id,name');
    if ($quizzes) {
        $quizurl = new moodle_url('/mod/quickpractice/questionbank.php',
            ['id' => $id, 'action' => 'doquizimport', 'sesskey' => sesskey()]);
        echo '<form method="post" action="' . $quizurl . '">';
        echo '<div class="form-group"><label>选择 Quiz</label><select name="quizid" class="form-control">';
        foreach ($quizzes as $quiz) {
            echo '<option value="' . $quiz->id . '">' . htmlspecialchars($quiz->name) . '</option>';
        }
        echo '</select></div>';
        echo '<button type="submit" class="btn btn-primary">导入</button></form>';
    } else {
        echo '<p>当前课程中没有 Quiz 活动。</p>';
    }
    echo '</div>';

} else {
    // ── 题目列表 ──────────────────────────────────────────
    $questions = $DB->get_records('quickpractice_questions',
        ['quickpracticeid' => $quickpractice->id], 'sortorder,id');

    echo '<div class="qp-qbank-actions mb-3">';
    echo html_writer::link(new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id, 'action' => 'add']),
        '➕ ' . get_string('addquestion', 'quickpractice'), ['class' => 'btn btn-primary']);
    echo ' ' . html_writer::link(new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id, 'action' => 'import']),
        '📥 ' . get_string('importgift', 'quickpractice'), ['class' => 'btn btn-secondary']);
    echo '</div>';

    if (empty($questions)) {
        echo $OUTPUT->notification(get_string('noquestions', 'quickpractice'), 'info');
    } else {
        echo '<p>' . get_string('totalquestions', 'quickpractice', count($questions)) . '</p>';
        echo '<table class="qp-table generaltable">';
        echo '<thead><tr><th>#</th><th>题型</th><th>题目</th><th>分类</th><th>难度</th><th>分值</th><th>来源</th><th>操作</th></tr></thead><tbody>';
        $i = 1;
        foreach ($questions as $q) {
            $qtypenames = ['multichoice' => '单选', 'multianswer' => '多选', 'truefalse' => '判断',
                'shortanswer' => '简答', 'matching' => '配对', 'essay' => '论述', 'numerical' => '计算'];
            $diffnames = [1 => '简单', 2 => '中等', 3 => '困难'];
            $editurl = new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id, 'action' => 'edit', 'qid' => $q->id]);
            $delurl  = new moodle_url('/mod/quickpractice/questionbank.php', ['id' => $id, 'action' => 'delete', 'qid' => $q->id, 'sesskey' => sesskey()]);
            $preview = mb_strimwidth(strip_tags($q->questiontext), 0, 60, '…');
            echo '<tr>
                <td>' . $i++ . '</td>
                <td><span class="qp-qtype-badge">' . ($qtypenames[$q->qtype] ?? $q->qtype) . '</span></td>
                <td>' . htmlspecialchars($preview) . '</td>
                <td>' . htmlspecialchars($q->category ?: '未分类') . '</td>
                <td>' . ($diffnames[$q->difficulty] ?? '-') . '</td>
                <td>' . $q->score . '</td>
                <td>' . htmlspecialchars($q->source) . '</td>
                <td>
                    ' . html_writer::link($editurl, '✏️', ['class' => 'btn btn-xs btn-link', 'title' => '编辑']) . '
                    ' . html_writer::link($delurl, '🗑', ['class' => 'btn btn-xs btn-link text-danger',
                        'onclick' => 'return confirm("' . get_string('deleteconfirm', 'quickpractice') . '")',
                        'title' => '删除']) . '
                </td>
            </tr>';
        }
        echo '</tbody></table>';
    }
}

echo '</div>';
echo $OUTPUT->footer();

// ── Quiz 导入辅助 ──────────────────────────────────────────
function _quickpractice_import_from_quiz($qpid, $quizid, $DB) {
    // 获取 Quiz 关联题目（通过 quiz_slots → question）
    $sql = "SELECT q.id, q.qtype, q.questiontext, q.generalfeedback,
                   qas.maxmark AS score
              FROM {quiz_slots} qs
              JOIN {question_references} qr ON qr.component = 'mod_quiz'
                   AND qr.questionarea = 'slot' AND qr.itemid = qs.id
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id AND qv.version = (
                  SELECT MAX(qv2.version) FROM {question_versions} qv2 WHERE qv2.questionbankentryid = qbe.id)
              JOIN {question} q ON q.id = qv.questionid
              JOIN {quiz} qz ON qz.id = qs.quizid
              LEFT JOIN {quiz_slots} qas ON qas.id = qs.id
             WHERE qz.id = :quizid";
    $rows = $DB->get_records_sql($sql, ['quizid' => $quizid]);

    $count     = 0;
    $sortorder = $DB->count_records('quickpractice_questions', ['quickpracticeid' => $qpid]);

    foreach ($rows as $row) {
        // ── 题型映射（支持全部题型）──
        $supportedTypes = ['multichoice' => 'multichoice', 'truefalse' => 'truefalse',
            'shortanswer' => 'shortanswer', 'numerical' => 'numerical',
            'essay' => 'essay', 'matching' => 'matching'];
        if (!isset($supportedTypes[$row->qtype])) { continue; }

        // 获取选项和答案
        $options = [];
        $answers = [];

        if ($row->qtype === 'multichoice') {
            // 多选题：Moodle 的 multichoice 可以是单选也可以是多选，
            // 通过统计正确答案数量来判断
            $ans = $DB->get_records('question_answers', ['question' => $row->id], 'id');
            foreach ($ans as $a) {
                $options[] = trim(strip_tags($a->answer));
                if ((float)$a->fraction > 0) {
                    $answers[] = trim(strip_tags($a->answer));
                }
            }
            // 核心修复：多个正确答案 → 映射为 multianswer（多选）
            // 单个正确答案 → 保持 multichoice（单选）
            $finalQtype = (count($answers) > 1) ? 'multianswer' : 'multichoice';
        } elseif ($row->qtype === 'truefalse') {
            $options = ['正确', '错误'];
            $ans = $DB->get_records_select('question_answers',
                'question = ? AND fraction > 0', [$row->id], '', 'answer', 0, 1);
            $first = reset($ans);
            $answers = $first ? [($first->answer === '1' || strtolower($first->answer) === 'true') ? '正确' : '错误'] : [];
            $finalQtype = 'truefalse';
        } elseif (in_array($row->qtype, ['shortanswer', 'numerical'])) {
            $ans = $DB->get_records_select('question_answers',
                'question = ? AND fraction >= 1', [$row->id], 'fraction DESC', 'answer', 0, 3);
            $answers = array_column((array)$ans, 'answer');
            $finalQtype = $supportedTypes[$row->qtype];
        } elseif ($row->qtype === 'matching') {
            // 匹配题：从 Moodle matching 子表获取配对数据
            $finalQtype = 'matching';
            try {
                $matches = $DB->get_records_sql(
                    "SELECT qa1.answer AS left_text, qa2.answer AS right_text
                       FROM {question_match_sub} qms
                       JOIN {question_answers} qa1 ON qa1.id = qms.questiontextid
                       JOIN {question_answers} qa2 ON qa2.id = qms.codeid
                      WHERE qms.question = ?
                      ORDER BY qms.id",
                    [$row->id]
                );
                $pairs = [];
                foreach ($matches as $m) {
                    $left  = trim(strip_tags($m->left_text ?? ''));
                    $right = trim(strip_tags($m->right_text ?? ''));
                    if ($left && $right) {
                        $pairs[] = $left . ' -> ' . $right;
                        $options[] = $left . ' -> ' . $right;
                        $answers[] = $left . ' -> ' . $right;
                    }
                }
            } catch (Exception $e) {
                error_log("quickpractice quiz import: match parse failed - " . $e->getMessage());
                $pairs = [];
            }
        } else {
            $finalQtype = $supportedTypes[$row->qtype];
        }

        // 从题目文本中提取 feedback（如果有的话）
        $feedbackText = '';
        if (!empty($row->generalfeedback)) {
            $feedbackText = strip_tags($row->generalfeedback);
        }

        $record = (object)[
            'quickpracticeid' => $qpid,
            'qtype'           => $finalQtype,
            'questiontext'    => $row->questiontext,
            'questionformat'  => FORMAT_HTML,
            'options'         => json_encode($options, JSON_UNESCAPED_UNICODE),
            'answers'         => json_encode($answers, JSON_UNESCAPED_UNICODE),
            'feedback'        => $feedbackText,
            'score'           => max(1, (float)($row->score ?? 1)),
            'difficulty'      => 2,
            'source'          => 'quiz',
            'sortorder'       => $sortorder++,
            'timecreated'     => time(),
        ];
        $DB->insert_record('quickpractice_questions', $record);
        $count++;
    }
    return $count;
}

