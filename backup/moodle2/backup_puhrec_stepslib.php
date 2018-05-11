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
 * Define all the backup steps that will be used by the backup_puhrec_activity_task
 *
 * @package   mod_puhrec
 * @category  backup
 * @copyright 2015 Your Name <your@email.adress>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Define the complete puhrec structure for backup, with file and id annotations
 *
 * @package   mod_puhrec
 * @category  backup
 * @copyright 2015 Your Name <your@email.adress>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_puhrec_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // https://docs.moodle.org/dev/Backup_2.0_for_developers
        // Define the root element describing the puhrec instance.
        $puhrec = new backup_nested_element('puhrec', array('id'), array(
            'course', 'name', 'intro', 'introformat', 'practicetext','lang','timeavailable',
            'timedue', 'grade', 'maxduration', 'maxnumber', 'preventlate',
            'permission', 'timecreated', 'timemodified'));

        $messages = new backup_nested_element('messages');

        $message = new backup_nested_element('message', array('id'), array(
            'userid', 'message', 'supplement', 'supplementformat',
            'audio', 'comments', 'commentsformat',
            'commentedby', 'playcount','grade', 'timestamp', 'locked'));

        $audios = new backup_nested_element('audios');

        $audio = new backup_nested_element('audio', array('id'), array(
            'userid', 'type', 'title', 'name', 'timecreated'));

        // Build the tree
        $puhrec->add_child($messages);
        $messages->add_child($message);
        $puhrec->add_child($audios);
        $audios->add_child($audio);

        // Define data sources.
        $puhrec->set_source_table('puhrec', array('id' => backup::VAR_ACTIVITYID));
		if ($userinfo) {
		    $message->set_source_sql('
                SELECT *
                  FROM {puhrec_messages}
                  WHERE puhrecid = ?',
		        array(backup::VAR_PARENTID));
		    $audio->set_source_table('puhrec_audios', array('puhrecid' => backup::VAR_PARENTID));

		}
        // If we were referring to other tables, we would annotate the relation
        // with the element's annotate_ids() method.

		// Define id annotations
		$puhrec->annotate_ids('scale', 'grade');
		$message->annotate_ids('user', 'userid');
		$message->annotate_ids('user', 'commentedby');
		$audio->annotate_ids('user', 'userid');


        // Define file annotations (we do not use itemid in this example).
		$puhrec->annotate_files('mod_puhrec', 'intro', null);
		$message->annotate_files('mod_puhrec', 'message', 'id');
        $puhrec->annotate_files('mod_puhrec', 'audio', 'id');



        // Return the root element (puhrec), wrapped into standard activity structure.
        return $this->prepare_activity_structure($puhrec);
    }
}
