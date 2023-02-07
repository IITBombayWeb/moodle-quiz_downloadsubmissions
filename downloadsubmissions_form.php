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
 * This file defines the setting form for the quiz downloadsubmissions report.
 *
 * @package   quiz_downloadsubmissions
 * @copyright 2017 IIT Bombay
 * @author    Kashmira Nagwekar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Quiz downloadsubmissions report settings form.
 *
 * @copyright 2017 IIT Bombay
 * @author    Kashmira Nagwekar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class quiz_downloadsubmissions_settings_form extends moodleform {

    /**
     * Form definition method.
     */
	public function definition() {
		global $CFG;

		$mform = $this->_form;
		$mform->addElement('hidden', 'id', '');
		$mform->setType('id', PARAM_INT);

		$mform->addElement('hidden', 'mode', '');
		$mform->setType('mode', PARAM_ALPHA);

// 		$mform->addElement('header', 'preferencespage',
// 		        get_string('reportwhattoinclude', 'quiz'));

		$mform->addElement('header', 'preferencespage',
		        get_string('setpreferences', 'quiz_downloadsubmissions'));

		$mform->addElement('select', 'folders', get_string('setfolderhierarchy', 'quiz_downloadsubmissions'), array(
		        'questionwise'    => get_string('essayquestionwise', 'quiz_downloadsubmissions'),
		        'attemptwise'     => get_string('userattemptwise', 'quiz_downloadsubmissions'),
		));

// 		$mform->addElement('selectyesno', 'textresponse',
// 		        'Include text response');

		$mform->addElement('select', 'textresponse', get_string('includetextresponsefile', 'quiz_downloadsubmissions'), array(
		        '1'   => get_string('yes'),
		        '0'   => get_string('no'),
		));

		$mform->addElement('select', 'questiontext', get_string('includequestiontextfile', 'quiz_downloadsubmissions'), array(
		        '1'   => get_string('yes'),
		        '0'   => get_string('no'),
		));

        $mform->addElement('select', 'retainfilenames', 'Retain Filenames', array(
		        '1'   => 'Yes',
		        '0'   => 'No',
		));
        $mform->setDefault('retainfilenames', 0);

// 		$mform->addElement('submit', 'downloadsubmissions', get_string('downloadsubmissions', 'quiz_downloadsubmissions'));
		$mform->addElement('submit', 'downloadsubmissions', get_string('download'));
	}
}
