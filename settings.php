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
 * Resource module admin settings and defaults
 *
 * @package    puhrec
 * @copyright  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

	$settings->add(new admin_setting_configtext('puhrec/maxduration',
			get_string('maxduration', 'puhrec'), get_string('maxdurationdefaultsetinfo', 'puhrec'), 1200, PARAM_INT, 6));
	
	$settings->add(new admin_setting_configtext('puhrec/maxnumber',
        get_string('maxnumber', 'puhrec'), get_string('maxnumberdefaultsetinfo', 'puhrec'), 20, PARAM_INT, 2));
}
