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
 * Define all the restore steps that will be used by the restore_puhrec_activity_task
 *
 * @package   mod_puhrec
 * @category  backup
 * @copyright 2015 Your Name <your@email.adress>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one puhrec activity
 *
 * @package   mod_puhrec
 * @category  backup
 * @copyright 2015 Your Name <your@email.adress>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_puhrec_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('puhrec', '/activity/puhrec');
        if ($userinfo) {
            $paths[] = new restore_path_element('puhrec_message', '/activity/puhrec/messages/message');
            $paths[] = new restore_path_element('puhrec_audio', '/activity/puhrec/audios/audio');
        }


        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_puhrec($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timeavailable = $this->apply_date_offset($data->timeavailable);
        $data->timedue = $this->apply_date_offset($data->timedue);
        if ($data->grade < 0) { // scale found, get mapping
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the puhrec record
        $newitemid = $DB->insert_record('puhrec', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_puhrec_message($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->puhrecid = $this->get_new_parentid('puhrec');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->commentedby = $this->get_mappingid('user', $data->commentedby);
        $data->timestamp = $this->apply_date_offset($data->timestamp);

        $newitemid = $DB->insert_record('puhrec_messages', $data);
        $this->set_mapping('puhrec_message', $oldid, $newitemid, true);
    }

    protected function process_puhrec_audio($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->puhrecid = $this->get_new_parentid('puhrec');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record('puhrec_audios', $data);
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add puhrec related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_puhrec', 'intro', null);
        $this->add_related_files('mod_puhrec', 'message', 'puhrec_message');
        $this->add_related_files('mod_puhrec', 'audio', 'puhrec');

    }
}
