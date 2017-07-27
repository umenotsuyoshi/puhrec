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
 * The main puhrec configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_puhrec
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_puhrec
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_puhrec_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $USER, $PAGE, $CFG;
        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('puhrecname', 'puhrec'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'puhrecname', 'puhrec');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }
        //-------------------------------------------------------------------------------
		// ここから独自の実装
        // Adding the rest of puhrec settings, spreading all them into this fieldset
        // ... or adding more fieldsets ('header' elements) if needed for better logic.
		// $mform->addElement('static', 'label1', 'puhrecsetting1', 'Your puhrec fields go here. Replace me!')
        //-------------------------------------------------------------------------------
        // 練習用テキスト
        $mform->addElement('textarea', 'practicetext', get_string('practicetext', 'puhrec'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
        	$mform->setType('practicetext', PARAM_TEXT);
        } else {
        	$mform->setType('practicetext', PARAM_CLEAN);
        }
        $mform->addRule('practicetext', null, 'required', null, 'client');
        $mform->addRule('practicetext', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('practicetext', 'puhrecname', 'puhrec');
        // 練習用テキストの言語
        $options = array("ja-JP"=>"ja-JP","en-US"=>"en-US","zh-CN"=>"zh-CN",);
        $name = get_string('lang', 'puhrec');
        $mform->addElement('select', 'lang', $name, $options);
        $mform->addHelpButton('lang', 'lang', 'puhrec');
        
        
        
        //開始日時、終了日時
        $mform->addElement('date_time_selector', 'timeavailable', get_string('availabledate', 'puhrec'), array('optional'=>true));
        $mform->setDefault('timeavailable', time());
        $mform->addElement('date_time_selector', 'timedue', get_string('duedate', 'puhrec'), array('optional'=>true));
        $mform->setDefault('timedue', time()+7*24*3600);

        // 録音最大時間
        $mform->addElement('text', 'maxduration', get_string('maxduration', 'puhrec'), array('size'=>'16'));
        $mform->setType('maxduration', PARAM_INT);
        $mform->addHelpButton('maxduration', 'maxduration', 'puhrec');
        $mform->setDefault('maxduration', get_config('puhrec' , 'maxduration'));
        
        // 音ファイルの数の上限
        $options = array();
        for ($i = 1; $i <= get_config('puhrec' , 'maxnumber'); $i++) {
        	$options[$i] = $i;
        }
        $name = get_string('maxnumber', 'puhrec');
        $mform->addElement('select', 'maxnumber', $name, $options);
        $mform->addHelpButton('maxnumber', 'maxnumber', 'puhrec');
        
        //提出日以降の送信を阻止する
        $mform->addElement('selectyesno', 'preventlate', get_string('preventlate', 'puhrec'));

//        $mform->addElement('header', 'puhrecfieldset', get_string('puhrecfieldset', 'puhrec'));
//        $mform->addElement('static', 'label2', 'puhrecsetting2', 'Your puhrec fields go here. Replace me!');

        //-------------------------------------------------------------------------------
		// 以下テンプレのまま
        //-------------------------------------------------------------------------------
        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }
    function validation($data, $files) {
    	$errors = parent::validation($data, $files);
    	$system_maxduration = 1200; // in case of admin failuer.
    	$site_maxduration = get_config('puhrec' , 'maxduration');
    	$maxduration = ($system_maxduration>$site_maxduration)?$site_maxduration:$system_maxduration;
    	if(0<= $data['maxduration'] && $data['maxduration'] <= $maxduration){
    		return $errors;
    	}else{
    		$error['maxduration'] = get_string('maxdurationerror', 'puhrec',$site_maxduration);
    		return $error;
    	}
    }
    
}
