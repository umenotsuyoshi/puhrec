<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Prints a particular instance of puhrec
 *
 * JavaScriptによる録音プログラム試作
 * 
 * 
 * @package    mod_puhrec
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once('lib.php');
require_once('locallib.php');
$PAGE->requires->css('/mod/puhrec/style.css');
$action = optional_param('action', 0, PARAM_TEXT); //録音や音声削除などユーザの操作種別
$id = optional_param('id', 0, PARAM_INT); // course_modulesテーブルのID
$v  = optional_param('v', 0, PARAM_INT);  // 

// course_modules のレコードを取得する。
// $cm はmdl_course_modulesテーブルの情報が入る
// コースに追加されたモジュールのインスタンスがmdl_course_modulesの1レコード。
// 取得したcourse_modulesにコースのIDが含まれているので、コーステーブルのレコードを取得
// $courseはテーブル。
// puhrecモジュールテーブル中でインスタンスの情報取得
if ($id) {
    $cm         = get_coursemodule_from_id('puhrec', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $puhrec  = $DB->get_record('puhrec', array('id' => $cm->instance), '*', MUST_EXIST);// puhrecモジュールインスタンスの情報
} else if ($v) {
    $puhrec  = $DB->get_record('puhrec', array('id' => $v), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $puhrec->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('puhrec', $puhrec->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}
//mdl_contextテーブルからレコードを取得
$context = context_module::instance($cm->id);
require_login($course, true, $cm);
// 「コースモジュールが閲覧されました。」のログを残す。
$event = \mod_puhrec\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $puhrec);
$event->trigger();

$PAGE->set_url('/mod/puhrec/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($puhrec->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->jquery();
$PAGE->requires->js_init_call('M.mod_puhrec.init',array($puhrec->maxduration,$puhrec->lang));
$PAGE->requires->strings_for_js(
        array('cannotsumit','changeserver','changebrowser','inputrectitle','timeoutmessage','notavailable','submissionlocked','reachedupperlimit'), 
        'puhrec');// Javascriptで使用する言語パック準備

// ここまでテンプレートどおり
/*********************************************************************************************************************************/
/*
 * puhrec_messagesテーブルにメッセージは記録される。
*/
/*********************************************************************************************************************************/
$cansubmit = has_capability('mod/puhrec:submit', $context);
$isavailable = true;
if($cansubmit){
    $time = time();
    if ($puhrec->timeavailable > $time) $isavailable = false;
    if ($puhrec->timedue && $time > $puhrec->timedue && $puhrec->preventlate != 0) $isavailable = false;
}
$PAGE->requires->jquery();
/*********************************************************************************************************************************/
/* HTMLページの出力開始  */
/*********************************************************************************************************************************/
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulename', 'puhrec'));
// Recorded voice list
/*********************************************************************************************************************************/
// 教員のブロック開始
/*********************************************************************************************************************************/
if (has_capability('mod/puhrec:grade', $context)) {
    if ($action === 0) {
        puhrec_print_audiotags($context, $puhrec); // 録音済みユーザの音声一覧のHTMLタグ出力
        puhrec_print_intro($cm, $puhrec);// 説明
        
        puhrec_print_practicetext($cm, $puhrec);// 説明
        
        puhrec_print_rec_form($cm, $puhrec); // 録音フォーム
        puhrec_print_students_submit_list($context, $puhrec); // 
    // 評定用のフォームを表示する。個別の学生に対して評定を行う。
    }else if ($action === 'showgradeform') {
        puhrec_print_grade_form($context, $cm, $course, $action, $puhrec);
    }
/*********************************************************************************************************************************/
// 学生のブロック開始
/*********************************************************************************************************************************/
}else{
    puhrec_print_audiotags($context, $puhrec); // 録音済みユーザの音声一覧のHTMLタグ出力
    puhrec_print_intro($cm, $puhrec);
    
    puhrec_print_practicetext($cm, $puhrec);// 説明
    
    puhrec_print_rec_form($cm, $puhrec);
    $submission = $DB->get_record('puhrec_messages', array('puhrecid'=>$puhrec->id, 'userid'=>$USER->id));
    puhrec_print_teacher_commet($context, $submission);
}
    echo $OUTPUT->footer();
