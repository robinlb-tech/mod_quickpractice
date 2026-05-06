<?php
/**
 * Language strings for mod_quickpractice (Simplified Chinese)
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ── 通用 ──
$string['pluginname']           = '快速练习';
$string['modulename']           = '快速练习';
$string['modulenameplural']     = '快速练习';
$string['pluginadministration'] = '快速练习管理';
$string['modulename_help']      = '快速练习（QuickPractice）是一个支持教-学-评一体化的在线练习模块，支持从题库或 GIFT 文件导入题目，提供学生作答反馈、教师看板、班级排名、自动评分和评估报告生成等功能。';

// ── 活动设置表单 ──
$string['name']                 = '练习名称';
$string['intro']                = '练习说明';
$string['timeopen']             = '开放时间';
$string['timeopen_help']        = '学生可以开始答题的时间，留空则立即开放。';
$string['timeclose']            = '截止时间';
$string['timeclose_help']       = '学生提交答题的截止时间，留空则无截止。';
$string['timelimit']            = '答题时限（秒）';
$string['timelimit_help']       = '每次作答允许的最长时间（秒），0 表示不限制。';
$string['maxattempts']          = '最多尝试次数';
$string['maxattempts_help']     = '学生可以参加的最多答题次数，0 表示无限制。';
$string['shuffle']              = '随机排列题目';
$string['showfeedback']         = '作答后显示反馈';
$string['showranking']          = '作答后显示排行榜';
$string['passscore']            = '及格分（百分制）';
$string['grademethod']          = '评分方式';
$string['grademethod_highest']  = '取最高分';
$string['grademethod_last']     = '取最后一次';
$string['grademethod_average']  = '取平均分';

// ── 导航/页面标题 ──
$string['teacherdashboard']     = '教师看板';
$string['questionbank']         = '题库管理';
$string['studentview']          = '我的练习';
$string['reportview']           = '评估报告';
$string['rankingview']          = '排行榜';
$string['generatorview']        = '练习生成器';
$string['attemptview']          = '答题';
$string['resultview']           = '查看结果';

// ── 题目类型 ──
$string['qtype_multichoice']    = '单选题';
$string['qtype_multianswer']    = '多选题';
$string['qtype_truefalse']      = '判断题';
$string['qtype_shortanswer']    = '简答题';
$string['qtype_matching']       = '配对题';
$string['qtype_essay']          = '论述题';
$string['qtype_numerical']      = '计算题';

// ── 作答状态 ──
$string['state_inprogress']     = '答题中';
$string['state_finished']       = '已完成';
$string['state_abandoned']      = '已放弃';

// ── 学生端 ──
$string['startattempt']         = '开始答题';
$string['continueattempt']      = '继续作答';
$string['reviewattempt']        = '查看结果';
$string['submitattempt']        = '提交答题';
$string['yourscore']            = '你的得分';
$string['yourrank']             = '班级排名';
$string['correctanswer']        = '正确答案';
$string['yourfeedback']         = '作答反馈';
$string['timespent']            = '用时';
$string['attemptno']            = '第 {$a} 次作答';
$string['attemptsallowed']      = '允许作答次数：{$a}';
$string['passmark']             = '及格分：{$a}';
$string['passed']               = '已通过 ✓';
$string['failed']               = '未通过';
$string['nomoreattempts']       = '已达到最大作答次数';
$string['notopen']              = '练习尚未开放';
$string['closed']               = '练习已截止';

// ── 题库管理 ──
$string['addquestion']          = '添加题目';
$string['editquestion']         = '编辑题目';
$string['deletequestion']       = '删除题目';
$string['importgift']           = '导入 GIFT 格式';
$string['importfromquiz']       = '从 Quiz 模块导入';
$string['importfile']           = '选择文件';
$string['importpreview']        = '导入预览';
$string['importcount']          = '成功导入 {$a} 道题目';
$string['importerror']          = '导入失败：{$a}';
$string['questiontext']         = '题目内容';
$string['questionoptions']      = '选项（每行一个）';
$string['questionanswer']       = '正确答案';
$string['questionfeedback']     = '题目反馈';
$string['questionscore']        = '分值';
$string['questiondifficulty']   = '难度';
$string['difficulty_easy']      = '简单';
$string['difficulty_medium']    = '中等';
$string['difficulty_hard']      = '困难';
$string['questioncategory']     = '分类';
$string['noquestions']          = '暂无题目，请先添加或导入。';
$string['totalquestions']       = '共 {$a} 道题';

// ── 教师看板 ──
$string['totalstudents']        = '参与学生';
$string['totalattempts']        = '总提交次数';
$string['avgscoreall']          = '全班平均分';
$string['passrate']             = '及格率';
$string['avgduration']          = '平均用时';
$string['classfilter']          = '按班级筛选';
$string['allclasses']           = '全部班级';
$string['searchstudent']        = '搜索学生';
$string['studentname']          = '姓名';
$string['classname']            = '班级';
$string['score']                = '得分';
$string['rank']                 = '排名';
$string['duration']             = '用时';
$string['submittime']           = '提交时间';
$string['attempts']             = '作答次数';
$string['questionanalysis']     = '题目分析';
$string['correctrate']          = '正确率';
$string['wrongcount']           = '错误人数';
$string['exportcsv']            = '导出 CSV';
$string['exportpdf']            = '导出报告';

// ── 评估报告 ──
$string['generatereport']       = '生成评估报告';
$string['reporttitle']          = '报告标题';
$string['reporttype_class']     = '班级报告';
$string['reporttype_student']   = '个人报告';
$string['reporttype_question']  = '题目报告';
$string['reportsaved']          = '报告已保存';
$string['reportlist']           = '历史报告';
$string['nodatayet']            = '暂无数据';
$string['knowledgepoints']      = '知识点掌握情况';
$string['weakpoints']           = '薄弱知识点';
$string['strongpoints']         = '优势知识点';
$string['suggestions']          = '教学建议';
$string['distributionChart']    = '成绩分布';
$string['scoreSegment']         = '分段统计';

// ── 练习生成器 ──
$string['generator']            = '练习生成器';
$string['generator_help']       = '从题库中按条件自动组卷，生成新的快速练习。';
$string['gen_count']            = '题目数量';
$string['gen_difficulty']       = '难度';
$string['gen_category']         = '分类';
$string['gen_qtype']            = '题型';
$string['gen_title']            = '练习名称';
$string['gen_create']           = '生成练习';
$string['gen_preview']          = '预览题目';
$string['gen_notenough']        = '符合条件的题目不足 {$a} 道';
$string['gen_success']          = '练习「{$a}」已成功生成！';

// ── 权限 ──
$string['quickpractice:view']            = '查看练习';
$string['quickpractice:attempt']         = '参加答题';
$string['quickpractice:viewownresult']   = '查看自己的结果';
$string['quickpractice:viewreport']      = '查看教师看板';
$string['quickpractice:managequestions'] = '管理题库';
$string['quickpractice:import']          = '导入题目';
$string['quickpractice:generatereport']  = '生成评估报告';
$string['quickpractice:grade']           = '手动评分';

// ── 杂项 ──
$string['unknownclass']         = '未知班级';
$string['seconds']              = '秒';
$string['minutes']              = '分钟';
$string['yes']                  = '是';
$string['no']                   = '否';
$string['back']                 = '返回';
$string['save']                 = '保存';
$string['confirm']              = '确认';
$string['cancel']               = '取消';
$string['deleteconfirm']        = '确定要删除吗？此操作不可撤销。';

// 帮助字符串（mod_form.php 中 addHelpButton 需要）
$string['timeopen_help']    = '设置后，学生只能在该时间之后开始练习。';
$string['timeclose_help']   = '设置后，学生在该时间之后无法提交。';
$string['timelimit_help']   = '设置后，每次作答限制在指定时间内完成。';
$string['maxattempts_help'] = '设置为"无限制"时允许学生无限次重新作答。';
$string['attemptsallowed']  = '允许作答次数';

// 隐私
$string['privacy:metadata:quickpractice_attempts']           = '学生的答题记录。';
$string['privacy:metadata:quickpractice_attempts:userid']    = '学生的用户 ID。';
$string['privacy:metadata:quickpractice_attempts:score']     = '获得的分数。';
$string['privacy:metadata:quickpractice_attempts:classname'] = '提取的班级名称。';
$string['privacy:metadata:quickpractice_responses']          = '单次作答中的各题作答记录。';
$string['privacy:metadata:quickpractice_responses:response'] = '学生提交的答案。';
