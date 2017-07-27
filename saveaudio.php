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
 * Save audio file
 *
 * @author     
 * @author     
 * @package    mod
 * @subpackage puhrec
 * @copyright  
 * @license    
 * @version    
 */
require_once('../../config.php');
require_once('lib.php');
require_once('locallib.php');

/************************************************************************************/
/************************************************************************************
require_once 'Log.php';
global $g_loggerConf;
global $programFileName;
$programFileName = str_replace ("/","_",stristr(__FILE__,"/mod/"));
$g_loggerConf = array('mode'=>0666,'lineFormat' => '%1$s %3$s %2$s [%6$s]%5$s(%7$s) - %4$s','timeFormat'=>'%d %H:%M:%S');
$logger = &Log::singleton('file', '/tmp/puhrec/' . $programFileName . '.log', 'ident', $g_loggerConf, PEAR_LOG_DEBUG);

************************************************************************************/
/************************************************************************************/

require_login();
$id = required_param('id', PARAM_INT);
$title = required_param('title', PARAM_TEXT);
if (!confirm_sesskey()){
    error('Bad Session Key');
    exit;
}

if ($id) {
    $cm = get_coursemodule_from_id('puhrec', $id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);   
    $puhrec = $DB->get_record('puhrec', array('id' => $cm->instance), '*', MUST_EXIST);
}
else {
    error('Invalid Parameters!');
}
$PAGE->set_url('/course/view.php', array('id' => $id));
$cansubmit = has_capability('mod/puhrec:submit', $context);
if (!$cansubmit) {
    print 'cannotsumit';
    die;
}
$cangrade = false;
// 開始日、終了日のチェック
// timeavailable:開始日時
// timedue:提出日
$submission = $DB->get_record('puhrec_messages', array('puhrecid'=>$puhrec->id, 'userid'=>$USER->id));
if($err_type = check_can_recording($puhrec, $submission)){
    $event = \mod_puhrec\event\voice_save_error::create_voice_save_error($cm, $context, $err_type);
    $event->trigger();
    print $err_type; // defined in lang pack. and conv by javascript and alert user. 
    die;
}
$playcount = 0;
if(isset($_POST["playcount"])){
	$playcount =$_POST["playcount"];
}
$tarantext = null;
if(isset($_POST["tarantext"])){
	$tarantext=$_POST["tarantext"];
}

$elname = "puhrec_upload_file";
// Use data/time as the file name
if (isset($_FILES[$elname]) && isset($_FILES[$elname]['name'])) {
    $oldname = $_FILES[$elname]['name'];//blobが
    $ext = preg_replace("/.*(\.[^\.]*)$/", "$1", $oldname);
    $newname = date("Ymd") . date("His") . $ext;
    $_FILES[$elname]['name'] = $newname;
}
else {
    print '[servererror]';
    die;
}
// Store the audio file
$fs = get_file_storage();
$file = array('contextid'=>$context->id, 'component'=>'mod_puhrec', 'filearea'=>'audio',
              'itemid'=>$puhrec->id, 'filepath'=>'/', 'filename'=>$_FILES[$elname]['name'],
              'timecreated'=>time(), 'timemodified'=>time(),
              'mimetype'=>'audio/ogg', 'userid'=>$USER->id, 'author'=>fullname($USER),
              'license'=>$CFG->sitedefaultlicense);
$retfs = $fs->create_file_from_pathname($file, $_FILES[$elname]['tmp_name']);
$url = $_FILES[$elname]['name'];
if (!$submission) {
    $submission = new stdClass();
    $submission->puhrecid       = $puhrec->id;
    $submission->userid           = $USER->id;
    $submission->message          = '';
    $submission->supplement       = '';
    $submission->supplementformat = FORMAT_HTML;
    $submission->audio            = '';
    $submission->comments         = '';
    $submission->commentsformat   = FORMAT_HTML;
    $submission->commentedby      = 0;
    $submission->playcount		  = $playcount;
    $submission->grade            = -1;
    $submission->timestamp        = time();
    $submission->locked           = false;
    $submission->id = $DB->insert_record("puhrec_messages", $submission);
}else{
	$submission->playcount		  = $playcount;
	$submission->timestamp        = time();
}
$DB->update_record('puhrec_messages', $submission);
// add_to_logの置き換え 
$event = \mod_puhrec\event\voice_save::create(array(
        'objectid' => $cm->instance,
        'context' => $context,
));
$event->trigger();// replace add_to_log($course->id, 'nanogong', 'update', 'view.php?n='.$nanogong->id, $nanogong->id, $cm->id);

$puhrecaudio = new stdClass();
$puhrecaudio->puhrecid   = $puhrec->id;
$puhrecaudio->userid       = $USER->id;
$puhrecaudio->type         = 1;
$puhrecaudio->title        = $title;
$puhrecaudio->transcription = $tarantext;
$puhrecaudio->name         = $url;
$puhrecaudio->timecreated  = time();
$puhrecaudio->id = $DB->insert_record("puhrec_audios", $puhrecaudio);
$DB->update_record('puhrec_audios', $puhrecaudio);
redirect(new moodle_url('view.php', array('id'=>$id, 'action'=>'savemessage')));



