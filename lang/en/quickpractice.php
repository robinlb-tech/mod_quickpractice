<?php
/**
 * English language strings for mod_quickpractice
 *
 * @package    mod_quickpractice
 * @copyright  2026 刘兵（青海师范大学附属中学信息科技教师）robinpcy@126.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']           = 'Quick Practice';
$string['modulename']           = 'Quick Practice';
$string['modulenameplural']     = 'Quick Practices';
$string['pluginadministration'] = 'Quick Practice administration';
$string['modulename_help']      = 'The Quick Practice module provides integrated teaching, learning, and assessment (教-学-评一体化) for Chinese classroom contexts.';

$string['name']            = 'Name';
$string['intro']           = 'Description';
$string['timeopen']        = 'Open time';
$string['timeclose']       = 'Close time';
$string['timelimit']       = 'Time limit (seconds)';
$string['maxattempts']     = 'Maximum attempts';
$string['shuffle']         = 'Shuffle questions';
$string['showfeedback']    = 'Show feedback after attempt';
$string['showranking']     = 'Show ranking after attempt';
$string['passscore']       = 'Pass score (out of 100)';
$string['grademethod']     = 'Grade method';
$string['grademethod_highest'] = 'Highest grade';
$string['grademethod_last']    = 'Last attempt';
$string['grademethod_average'] = 'Average grade';

$string['teacherdashboard'] = 'Teacher Dashboard';
$string['questionbank']     = 'Question Bank';
$string['studentview']      = 'My Practice';
$string['reportview']       = 'Assessment Report';
$string['rankingview']      = 'Ranking';
$string['generatorview']    = 'Practice Generator';
$string['attemptview']      = 'Attempt';
$string['resultview']       = 'View Result';

$string['startattempt']    = 'Start Practice';
$string['continueattempt'] = 'Continue';
$string['reviewattempt']   = 'Review';
$string['submitattempt']   = 'Submit';
$string['yourscore']       = 'Your Score';
$string['yourrank']        = 'Your Rank';
$string['correctanswer']   = 'Correct Answer';
$string['yourfeedback']    = 'Feedback';
$string['timespent']       = 'Time Spent';
$string['passed']          = 'Passed ✓';
$string['failed']          = 'Not Passed';
$string['nomoreattempts']  = 'Maximum attempts reached';
$string['notopen']         = 'Practice not yet open';
$string['closed']          = 'Practice closed';

$string['addquestion']     = 'Add Question';
$string['editquestion']    = 'Edit';
$string['deletequestion']  = 'Delete';
$string['importgift']      = 'Import GIFT';
$string['importfromquiz']  = 'Import from Quiz';
$string['importcount']     = '{$a} questions imported successfully';
$string['importerror']     = 'Import failed: {$a}';
$string['noquestions']     = 'No questions yet. Please add or import some.';

$string['generatereport']  = 'Generate Report';
$string['reportsaved']     = 'Report saved';
$string['reportlist']      = 'Saved Reports';
$string['nodatayet']       = 'No data available';
$string['unknownclass']    = 'Unknown Class';

$string['quickpractice:view']            = 'View practice';
$string['quickpractice:attempt']         = 'Attempt practice';
$string['quickpractice:viewownresult']   = 'View own result';
$string['quickpractice:viewreport']      = 'View teacher report';
$string['quickpractice:managequestions'] = 'Manage question bank';
$string['quickpractice:import']          = 'Import questions';
$string['quickpractice:generatereport']  = 'Generate assessment report';
$string['quickpractice:grade']           = 'Grade manually';

$string['seconds']    = 'seconds';
$string['minutes']    = 'minutes';
$string['back']       = 'Back';
$string['save']       = 'Save';
$string['confirm']    = 'Confirm';
$string['cancel']     = 'Cancel';
$string['deleteconfirm'] = 'Are you sure you want to delete this? This action cannot be undone.';
$string['classname']  = 'Class';
$string['generator']  = 'Practice Generator';
$string['gen_count']  = 'Question count';
$string['gen_difficulty'] = 'Difficulty';
$string['gen_category']   = 'Category';
$string['gen_qtype']      = 'Question type';
$string['gen_title']      = 'Practice title';
$string['gen_create']     = 'Create Practice';
$string['gen_preview']    = 'Preview';
$string['gen_notenough']  = 'Not enough questions matching criteria (need {$a})';
$string['gen_success']    = 'Practice "{$a}" has been successfully generated!';

// Help strings (required by addHelpButton calls in mod_form.php).
$string['timeopen_help']    = 'If enabled, students can only start the practice from this date.';
$string['timeclose_help']   = 'If enabled, students cannot submit after this date.';
$string['timelimit_help']   = 'If enabled, each attempt is limited to the specified time.';
$string['maxattempts_help'] = 'Set to unlimited to allow unlimited re-attempts.';
$string['attemptsallowed']  = 'Attempts allowed';

// Privacy.
$string['privacy:metadata:quickpractice_attempts']          = 'Attempt records for each student.';
$string['privacy:metadata:quickpractice_attempts:userid']   = 'User ID of the student.';
$string['privacy:metadata:quickpractice_attempts:score']    = 'Score obtained.';
$string['privacy:metadata:quickpractice_attempts:classname']= 'Extracted class name.';
$string['privacy:metadata:quickpractice_responses']         = 'Individual responses within an attempt.';
$string['privacy:metadata:quickpractice_responses:response']= 'The answer submitted by the student.';

// Strings used in view/report/ranking/attempt pages.
$string['score']             = 'Score';
$string['attempts']          = 'Attempts';
$string['attemptno']         = 'Attempt #{$a}';
$string['submittime']        = 'Submitted';
$string['passmark']          = 'Pass mark: {$a}%';
$string['totalquestions']    = 'Total questions: {$a}';
$string['rank']              = 'Rank';
$string['studentname']       = 'Student';
$string['duration']          = 'Duration';
$string['allclasses']        = 'All classes';
$string['searchstudent']     = 'Search student...';
$string['exportcsv']         = 'Export CSV';
$string['totalstudents']     = 'Total students: {$a}';
$string['totalattempts']     = 'Total attempts: {$a}';
$string['passrate']          = 'Pass rate: {$a}%';
$string['avgscoreall']       = 'Average score: {$a}%';
$string['avgduration']       = 'Average duration: {$a}';

// Question bank strings.
$string['questiontext']      = 'Question text';
$string['questionoptions']   = 'Options';
$string['questionanswer']    = 'Answer';
$string['questionfeedback']  = 'Feedback';
$string['questionscore']     = 'Score';
$string['questioncategory']  = 'Category';
$string['questiondifficulty']= 'Difficulty';
$string['difficulty_easy']   = 'Easy';
$string['difficulty_medium'] = 'Medium';
$string['difficulty_hard']   = 'Hard';
$string['qtype_multichoice'] = 'Multiple choice';
$string['qtype_multianswer'] = 'Multiple answer';
$string['qtype_truefalse']   = 'True/False';
$string['qtype_shortanswer'] = 'Short answer';
$string['qtype_numerical']   = 'Numerical';
$string['qtype_essay']       = 'Essay';
$string['qtype_matching']    = 'Matching';

// Assessment report strings.
$string['questionanalysis']  = 'Question analysis';
$string['weakpoints']        = 'Weak points';
$string['suggestions']       = 'Suggestions';

// Generator help string.
$string['generator_help']    = 'Use the Practice Generator to automatically create a set of questions from the question bank by specifying count, difficulty, and category.';
