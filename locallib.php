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
 * Internal library of functions for module puhrec
 *
 * All the puhrec specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_puhrec
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');
/**
 * 標準設定の説明into表示
 * 
 * @param stdClass $puhrec
 * @param stdClass $cm
 */
function puhrec_print_intro($cm, $puhrec){
    global $OUTPUT;
    if ($puhrec->intro) { //
        echo $OUTPUT->box(format_module_intro('puhrec', $puhrec, $cm->id), 'generalbox mod_introbox', 'puhrecintro');
    }
}
/**
 * 練習用の自動読み上げテキスト
 * 
 * 使用可能ブラウザ：https://caniuse.com/#search=speechSynthesis
 * 
 * 
 * 
 * @param unknown $cm
 * @param unknown $puhrec
 */
function puhrec_print_practicetext($cm, $puhrec){
	global $OUTPUT,$DB,$USER;
	$submission = $DB->get_record('puhrec_messages', array('puhrecid'=>$puhrec->id, 'userid'=>$USER->id));
	$playcount = 0;
	if($submission){
		$playcount = $submission->playcount;
	}
	if ($puhrec->practicetext) { //
		echo $OUTPUT->box_start('generalbox', 'practiceform');
		$playtxtlabel = get_string('playtxtlabel', 'puhrec');
		$playcountlabel = get_string('playcountlabel', 'puhrec');
		$playblock = <<<EOT
		<div id='puhrec_playtxt'>$puhrec->practicetext</div>
		<div>
	    	<input type="button" id="puhrec_play" value="$playtxtlabel" />
			<span id="pcount_label">$playcountlabel</span>
			<sapn id="puhrec_pcount">$playcount</span>
		</div>
EOT;
		echo $playblock;
		echo $OUTPUT->box_end();
	}
}
/**
 * 録音用の独自フォームを表示
 * 録音した音声は、JavaScriptのBlobデータとして保持するが、それをformのinput type="file"要素に設定する
 * と、formをsubmitした際にファイルシステム上のファイルを見に行くような挙動をする。
 * 上記のブラウザの動作の回避方法が不明のため、音声のアップロードはJavaScriptで行う。
 * Moodleの提供するformオブジェクトも使えない。
 * 
 * JavaAppletがセキュリティ上の理由で、JavaApplet起点で動作しなければならないのとは別理由。
 *
 * 録音音声の自動認識機能を追加(2017.07.24)
 *  
 * https://developer.mozilla.org/ja/docs/Web/API/SpeechRecognition/SpeechRecognition
 * 使用可能ブラウザ：https://caniuse.com/#search=SpeechRecognition
 *
 * @param stdClass $cm
 * @param stdClass $puhrec
 */
function puhrec_print_rec_form($cm, $puhrec){
    global $DB, $OUTPUT, $USER;
    
    if($err_type = check_can_recording($puhrec)){
        echo "<h4 id='$err_type'>" . get_string($err_type, 'puhrec') . "</h4>";
        return;
    }
    // 言語パックのメッセージ準備
    //$langmsgstr = get_strings(array('rectitle','usemodernbrowser','checkmikvolume','startpermitbrowserrec',
    //        'youcanuploadfromhere','inputrectitle','uploadmanualy','submissionlabel',
    //        'permitbrowserrec','changebrowser','remainingtime','remainingtimeunit'),'puhrec');
    $rectitle = get_string('rectitle', 'puhrec');
    $usemodernbrowser = get_string('usemodernbrowser', 'puhrec');
    $checkmikvolume = get_string('checkmikvolume', 'puhrec');
    $startpermitbrowserrec = get_string('startpermitbrowserrec', 'puhrec');
    $inputrectitle = get_string('inputrectitle', 'puhrec');
    $submissionlabel = get_string('submissionlabel', 'puhrec');
    $permitbrowserrec = get_string('permitbrowserrec', 'puhrec');
    $changebrowser =  get_string('changebrowser', 'puhrec');
    $remainingtime =  get_string('remainingtime', 'puhrec');
    $remainingtimeunit =  get_string('remainingtimeunit', 'puhrec');
    $checkrecording = get_string('checkrecording','puhrec');
    $reclaber= get_string('reclabel','puhrec');
    $stoplaber= get_string('stoplabel','puhrec');
    $sesskey = sesskey();
    echo $OUTPUT->box_start('generalbox', 'recform');
    
    $voice_form = <<<EOD
    <form action="./saveaudio.php" method="POST" enctype="multipart/form-data" id="voice_send">
    <div>
    <h3>$rectitle</h3>
    <input id='puhrec_rec_comment' size=40 type="text" name="title" value="$inputrectitle" class='not_changed'/>
	<span id="levenshteinDistance"></sapn>
    </div>
	<div id="taran_text">
	</div>
    <canvas id="rec_level_meter" width="10" height="29"></canvas>
    <input type="button" id="puhrec_rec" value="$reclaber" />
    <input type="button" id="puhrec_stop" value="$stoplaber"  disabled='disabled'/>
    <input type="button" id="puhrec_check" value="$checkrecording" disabled='disabled'/>
    <audio src="" id="puhrec_recording_audio" controls><p>$usemodernbrowser</p></audio>
    <input type="button" id="puhrec_upload" value="$submissionlabel" disabled='disabled'/>
    <div>
        <div id="rectimer_block"><span>$remainingtime</span><span id="rectime_timer">{$puhrec->maxduration}</span><span>$remainingtimeunit</span></div>
    </div>
    <input type="hidden" name="id" value="$cm->id"/>
    <input type="hidden" name="sesskey" value="$sesskey" />
    </form>
EOD;
    echo $voice_form;
    echo $OUTPUT->box_end();
}
/**
 * 録音可否を判定
 * 対応するエラーメッセージのランゲッジパックの添字を返却
 * 
 * 
 * @param stdClass $puhrec
 * @param stdClass $submission
 * @return String  if no error, return null
 */
function check_can_recording($puhrec, $submission=null){
    global $DB, $OUTPUT, $USER;
    // before available time. :利用可能以前
    $time = time();
    if ($time < $puhrec->timeavailable){
        return 'notavailableyet';
    }
    // 提出日以降の送信を阻止する
    if ($puhrec->timedue && $time > $puhrec->timedue && $puhrec->preventlate != 0){
        return 'pastduedate';
    }
    // 教師に提出をロックされている場合
    if($submission==null){
        $submission = $DB->get_record('puhrec_messages', array('puhrecid'=>$puhrec->id, 'userid'=>$USER->id));
    }
    if($submission && $submission->locked){
        //echo "<h4 id='submittedvoicelocked'>" . get_string('submittedvoicelocked', 'puhrec') . "</h4>";
        return 'submissionlocked';
    }
    //録音数上限のチェック
    $audiocount = $DB->count_records('puhrec_audios', array('puhrecid'=>$puhrec->id, 'userid'=>$USER->id));
    if ($puhrec->maxnumber && $audiocount >= $puhrec->maxnumber){
        return 'reachedupperlimit';
    }
    return null;   
}
/**
 * 学生数分、提出のリストを表示する。
 * 教員のcapabilityで利用
 * 
 * @param stdClass $context
 * @param stdClass $puhrec
 */
function puhrec_print_students_submit_list($context, $puhrec) {
    global $DB, $OUTPUT, $PAGE;
    $students = get_users_by_capability($context, 'mod/puhrec:submit');
    echo $OUTPUT->box_start('generalbox', 'studentlist');
    foreach ($students as $student) {
        puhrec_print_student_submissions($context, $puhrec, $student->id);
	}
	echo $OUTPUT->box_end();
}
/**
 * 学生の録音音声、評点、教員のコメントを表示 
 *
 * @param stdClass $context
 * @param stdClass $puhrec
 * @param int $studentid
 */
function puhrec_print_student_submissions($context, $puhrec, $studentid) {
    global $DB, $OUTPUT;
    $submission = $DB->get_record('puhrec_messages', array('puhrecid'=>$puhrec->id, 'userid'=>$studentid));
    echo '<div class="student_submissions">';
    echo '<div class="student_name">';
    puhrec_print_student_link($studentid, $puhrec->course);
    if ($submission && $submission->locked) echo ' <img src="pix/lock.gif" style="vertical-align: middle" alt="" title="" />';
    echo '</div>';// student_name
    echo '<div class="submission_pane">';// submissions  
    puhrec_print_audiotags($context, $puhrec, $studentid);
    puhrec_print_playcount($submission);
    puhrec_print_commet_tool($context, $studentid, $submission);
    echo '</div>';// submissions    
    echo '</div>';//student_submissions
}
/**
 * 教員の付けた点数、コメントを表示する。
 * 
 * @param stdClass $context
 * @param int $studentid
 * @param stdClass $submission
 */
function puhrec_print_commet_tool($context, $studentid, $submission){
    global $PAGE;
    if(! puhrec_print_teacher_commet($context, $submission)){
        return;
    }
    // grede button 
    $url = new moodle_url($PAGE->url, array('student'=>$studentid, 'action'=>'showgradeform'));
    $edit = get_string('edit', 'puhrec');
    echo '<input type="button" value="' . $edit . '" class="puhrec_editgrade_button" action="' . $url . '" />';
    if ($submission->audio || $submission->comments || $submission->grade >= 0) {
        echo get_string('tablemodified', 'puhrec') . ' ' . userdate($submission->timestamp) ;
    }
}
/**
 * 再生回数
 * @param unknown $context
 * @param unknown $studentid
 * @param unknown $submission
 */
function puhrec_print_playcount($submission){
	global $PAGE;
	if($submission){
		echo "<div id='puhrec_playcount'><span>". get_string('playcountlabel', 'puhrec')."</span><span>$submission->playcount</sapn></div>";
	}
}

/**
 * 教員のコメントを表示
 * 提出がない場合はfalseを返却
 * 
 * @param stdClass $context
 * @param stdClass $submission
 * @return boolean False if no submission
 */
function puhrec_print_teacher_commet($context, $submission){
    if (!$submission){
        echo '<div>' . get_string('nosubmission', 'puhrec') . '</div>';
        return false;
    }
    $gradestr = get_string('grade', 'puhrec');
    $comment = get_string('comment', 'puhrec');
    $grade = ($submission->grade >= 0)?$submission->grade: '-';
    echo "<div class='teacher_comment'>";
    echo "<div class='student_grade'><h4>$gradestr</h4><p>$grade</p></div>";
    echo "<div class='comment_text'><h4>$comment</h4><p>";
    if ($submission->comments) {
        $text = file_rewrite_pluginfile_urls($submission->comments, 'pluginfile.php', $context->id, 'mod_puhrec', 'message', $submission->id);
        echo format_text($text, $submission->commentsformat);
    }
    else {
        echo '-';
    }
    echo '</p></div>';// comment_text
    echo '</div>';// teachers_comment
    return true;
}

/**
 * ユーザ名とユーザのプロファイルへのリンクを表示する
 * 
 * @param int $studentid
 * @param int $courseid
 */
function puhrec_print_student_link($studentid, $courseid) {
    global $CFG, $DB;
    $student = $DB->get_record('user', array('id'=>$studentid));
    $fullname = fullname($student);
    if ($fullname == '') $fullname = '<Unnamed student>';
    echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$studentid.'&amp;course='.$courseid.'">'.$fullname.'</a>';
}
/**
 * ユーザの録音音声をテーブル表示
 * ユーザの指定がない場合はログインユーザ
 * print a student's voice audio tag table.
 * if $userid==0, print login user voice.
 * 
 * @param stdClass $context
 * @param stdClass $puhrec
 * @param int $userid 
 */
function puhrec_print_audiotags($context, $puhrec, $userid=0){
    global $DB, $CFG, $USER, $OUTPUT;
    if($userid==0){
        $userid = $USER->id;
    }
    if(!$puhrecaudios = $DB->get_records('puhrec_audios', array('puhrecid'=>$puhrec->id,'userid'=>$userid))){
        return;
    }
    echo $OUTPUT->box_start('generalbox', 'userrecedlist');
    $rectime = get_string('rectime', 'puhrec');
    $rectitle = get_string('rectitle', 'puhrec');
    $trantxt = get_string('trantxt', 'puhrec');
    $submittedvoice = get_string('submittedvoice', 'puhrec');
    $audiotagsupportneeded = get_string('audiotagsupportneeded','puhrec');
    echo "<table class='puhrec_submitted_voice'><tr><th>$rectitle</th><th>$trantxt</th><th>$rectime</th><th>$submittedvoice</th></tr>";
    foreach ($puhrecaudios as $puhrecaudio) {
        $filename = $puhrecaudio->name;
        $latesubmit='';
        if($puhrec->timedue && $puhrecaudio->timecreated > $puhrec->timedue){
            $latesubmit=' class="late_submit" ';
        }
        $time = userdate($puhrecaudio->timecreated);
        $relativepath = "/$context->id/mod_puhrec/audio/$puhrec->id/$filename";
        $url = $CFG->wwwroot . '/pluginfile.php?file=' . $relativepath;
        $submitrecord = <<<EOT
<tr>
	<td>{$puhrecaudio->title}</td>
	<td>{$puhrecaudio->transcription}</td>
	<td {$latesubmit}>$time</td>
	<td><audio src='{$url}' controls><p>{audiotagsupportneeded}</p></audio></td>
</tr>
EOT;
        echo $submitrecord;
    }
    echo "</table>";
    echo $OUTPUT->box_end();
}
/**
 * 
 * 
 */
 function puhrec_print_backto_list(){
    global $PAGE;
    $backtolist = get_string('backtolist', 'puhrec');
    echo '<input class="backto_list" type="button" onclick="location.href=\''.$PAGE->url.'\'" value="'.$backtolist.'">';
}

/**
 * print grade form
 * 
 * @param stdClass $context
 * @param stdClass $cm
 * @param stdClass $course
 * @param string $action
 * @param stdClass $puhrec
 */
function puhrec_print_grade_form($context, $cm, $course, $action, $puhrec){
    global $DB, $CFG, $PAGE, $USER, $OUTPUT;
    $studentid = optional_param('student', 0, PARAM_INT);
    $submission = $DB->get_record('puhrec_messages', array('puhrecid'=>$puhrec->id, 'userid'=>$studentid));
    if (!$submission) {
        print_error('The student submission does not exist!', 'puhrec', $PAGE->url);
    }
    // Prepare the grade form
    $editoroptions = array(
            'noclean'   => false,
            'maxfiles'  => EDITOR_UNLIMITED_FILES,
            'maxbytes'  => $course->maxbytes,
            'context'   => $context
    );
    
    $data = new stdClass();
    $data->id             = $cm->id;
    $data->action         = $action;
    $data->student        = $studentid;
    $data->maxduration    = $puhrec->maxduration;
    $data->sid            = $submission->id;
    $data->grade          = ($submission->grade < 0)? '' : $submission->grade;
    $data->url            = '';
    $data->comments       = $submission->comments;
    $data->commentsformat = $submission->commentsformat;
    $data->locked         = $submission->locked;
    $data = file_prepare_standard_editor($data, 'comments', $editoroptions, $context, 'mod_puhrec', 'message', $data->sid);
    // 評定フォームの生成
    $gradeform = new mod_puhrec_grade_form(null, array($context, $course, $puhrec, $submission, $data, $editoroptions, $puhrec->grade));
    if ($gradeform->is_cancelled()) {
        puhrec_print_teacher_commet($context, $submission); 
        puhrec_print_backto_list();
    }else if ($gradeform->is_submitted() && $gradeform->is_validated($data)) {
        //In this case you process validated data. $mform->get_data() returns data posted in form.
        $data = $gradeform->get_data();
        $data = file_postupdate_standard_editor($data, 'comments', $editoroptions, $context, 'mod_puhrec', 'message', $submission->id);
        $submission->comments = $data->comments;
        $submission->commentsformat = $data->commentsformat;
        $grade = trim($data->grade);
        if ($grade && (int) $grade <= $puhrec->grade && (int) $grade >= 0) {
            $submission->grade = $data->grade;
        }
        else {
            $submission->grade = -1;
        }
        $submission->commentedby    = $USER->id;
        $submission->locked         = empty($data->locked)? 0 : 1;
        $submission->timestamp      = time();
    
        $DB->update_record('puhrec_messages', $submission);
        $event = \mod_puhrec\event\update_grade::create(array(
                'objectid' => $cm->instance,
                'context' => $context,
        ));
        $event->trigger();//        
        puhrec_update_grades($puhrec, $submission->userid);
        puhrec_print_teacher_commet($context, $submission);
        puhrec_print_backto_list();
    }else{
        // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
        // or on the first display of the form.        
        $PAGE->requires->strings_for_js(array('emptymessage', 'notavailable', 'submissionlocked', 'servererror', 'voicetitle'), 'puhrec');
        $gradeform->focus();
        $gradeform->display();
    }
}
/**
 * 学生選択後の評定画面
 * greade fome
 * 
 *
 */
class mod_puhrec_grade_form extends moodleform {
    public function definition() {
        global $PAGE;

        $mform = $this->_form;// moodleformの定形作法
        list($context, $course, $puhrec, $submission, $data, $editoroptions, $maxgrade) = $this->_customdata; //生成時のパラメータ引継ぎ
        // 評定する音声表示
        echo '<table align="center" cellspacing="0" cellpadding="0"><tr><td><b>' . get_string('gradingstudentrec', 'puhrec');
        puhrec_print_student_link($submission->userid, $course->id);
        echo '</b></td></tr></table>';
        puhrec_print_audiotags($context, $puhrec, $submission->userid);
        
        puhrec_print_playcount($submission);
        // Main content
        $gradetitle = get_string('grade', 'puhrec') . get_string('outof', 'puhrec') . $maxgrade;
        $mform->addElement('text', 'grade', $gradetitle);
        $mform->setType('grade', PARAM_INT);
        $grademsg = get_string('wronggrade', 'puhrec') . $maxgrade;
        $mform->addRule('grade', $grademsg, 'numeric', null, 'client');
        
        $mform->addElement('editor', 'comments_editor', get_string('yourmessage', 'puhrec'), null, $editoroptions);
        $mform->setType('comments_editor', PARAM_RAW);
        $mform->addElement('checkbox', 'locked', get_string('lockstudent', 'puhrec'));

        // Hidden params
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);
        $mform->addElement('hidden', 'student');
        $mform->setType('student', PARAM_INT);
        $mform->addElement('hidden', 'maxduration');
        $mform->setType('maxduration', PARAM_INT);

        // Buttons
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'puhrec_savegrade_button', get_string('savechanges'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancelbutton', get_string('cancel'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');

        $this->set_data($data);
    }
    /**
     * エラーになったフォーム部品のname属性を$errorに設定すると、form->focus()を呼び出したときに自動で
     * エラー表示される。
     * 
     * {@inheritDoc}
     * @see moodleform::validation()
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if(0<= $data['grade'] && $data['grade'] <= 100){
        	return $errors;
        }else{
            $error['grade'] = get_string('gradeerror', 'puhrec');
            return $error;
        }
    }
}

