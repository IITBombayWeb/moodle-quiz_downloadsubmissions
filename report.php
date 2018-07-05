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
 * This file defines the quiz downloadsubmissions report class.
 *
 * @package   quiz_downloadsubmissions
 * @copyright 2017 IIT Bombay
 * @author	  Kashmira Nagwekar
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/downloadsubmissions/downloadsubmissions_form.php');

/**
 * Quiz report subclass for the downloadsubmissions report.
 *
 * This report allows you to download file attachments submitted
 * by students as a response to quiz essay questions.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_downloadsubmissions_report extends quiz_attempts_report {

	public function display($quiz, $cm, $course) {
        global $OUTPUT, $DB;

        $mform = new quiz_downloadsubmissions_settings_form();

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Check if the quiz contains essay type questions.
        // Method 1 : Check $questions object for existence essay type questions
        $hasessayquestions = false;
        if ($questions) {
	        foreach ($questions as $question) {
	        	if ($question->qtype == 'essay') {
	        		$hasessayquestions = true;
	        		break;
	        	}
	        }
        }
        // Method 2 : Check {quiz_slots} table
        // $hasessayquestions = $this->quiz_has_essay_questions($quiz->id);

        $hasstudents = false;
        $sql = "SELECT DISTINCT u.id
                FROM {user} 			u
                JOIN {user_enrolments} 	ej1_ue 	ON 	ej1_ue.userid 	= u.id
                JOIN {enrol} 			ej1_e 	ON 	(ej1_e.id 		= ej1_ue.enrolid
                								AND ej1_e.courseid 	= $course->id)
                WHERE
                	1 = 1 AND u.deleted = 0";
        $hasstudents = $DB->record_exists_sql($sql);

        $downloading_submissions = false;
        $ds_button_clicked = false;
        $user_attempts = false;
        $nosubmissions = false;

        // Check if downloading file submissions.
        if ($data = $mform->get_data()){
        	if ($ds_button_clicked = !empty($data->downloadsubmissions)) {
        		$user_attempts = $this->get_user_attempts($quiz, $course);
	        	$downloading_submissions = $this->downloading_submissions($ds_button_clicked, $hasessayquestions, $user_attempts);

	           	// Download file submissions for essay questions.
	        	if ($downloading_submissions) {
	        	    // If no attachments are found then it returns true;
	        	    // else returns zip folder with attachments submitted by the students.
	        	    $nosubmissions = $this->download_essay_submissions($quiz, $cm, $course, $user_attempts);
	        	}
	        }
        }

        // Start output.
        if (!$downloading_submissions | $nosubmissions) {
            // Only print headers if not asked to download data.
            $this->print_header_and_tabs($cm, $course, $quiz, 'downloadsubmissions');
        }

        if($nosubmissions) {
            echo $OUTPUT->notification(get_string('nosubmission', 'quiz_downloadsubmissions'));
        }

        $currentgroup = null;
        // Print information on the number of existing attempts.
        if (!$downloading_submissions) {
            // Do not print notices when downloading.
            if ($strattemptnum = quiz_num_attempt_summary($quiz, $cm, true, $currentgroup)) {
                echo '<div class="quizattemptcounts">' . $strattemptnum . '</div>';
            }
        }

        $hasquestions = quiz_has_questions($quiz->id);

        if (!$downloading_submissions | $nosubmissions) {
        	if ($ds_button_clicked) {
	        	if (!$hasquestions) {
	        	    echo $OUTPUT->notification(get_string('noquestions', 'quiz_downloadsubmissions'));
	            } else if (!$hasstudents) {
	                echo $OUTPUT->notification(get_string('nostudentsyet'));
// 	            } else if ($currentgroup && !$this->hasgroupstudents) {
// 	                echo $OUTPUT->notification(get_string('nostudentsingroup'));
	            } else if (!$hasessayquestions) {
	            	echo $OUTPUT->notification(get_string('noessayquestion', 'quiz_downloadsubmissions'));
	            } elseif (!$user_attempts) {
	            	echo $OUTPUT->notification(get_string('noattempts', 'quiz_downloadsubmissions'));
	            }
        	}

            // Print the form.
            $formdata = new stdClass;
            $formdata->id = optional_param('id', $quiz->id, PARAM_INT);
            $formdata->mode = optional_param('mode', 'downloadsubmissions', PARAM_ALPHA);
            $mform->set_data($formdata);
            echo '<div class="plugindescription">' . get_string('plugindescription', 'quiz_downloadsubmissions'). '</div>';
            $mform->display();
        }

        return true;
    }

    public function downloading_submissions($ds_button_clicked, $hasessayquestions, $user_attempts) {
    	global $DB;
    	if ($ds_button_clicked && $hasessayquestions && $user_attempts) {
    		return true;
    	} else {
    		return false;
    	}
    }

    /**
     * Are there any essay type questions in this quiz?
     * @param int $quizid the quiz id.
     */
    public function quiz_has_essay_questions($quizid) {
    	global $DB;

    	return $DB->record_exists_sql("
            SELECT slot.slot,
                   q.id,
                   q.qtype,
                   q.length,
                   slot.maxmark

              FROM {question} q
              JOIN {quiz_slots} slot ON slot.questionid = q.id

             WHERE q.qtype = 'essay'

          ORDER BY slot.slot", array($quiz->id));
    }

    /**
     *  Get user attempts (quiz attempt alongwith question attempts) : Method 1
     */
    public function get_user_attempts($quiz, $course){
    	global $DB;

    	$sql = "SELECT DISTINCT CONCAT(u.id, '#', COALESCE(qa.id, 0)) AS uniqueid,
        				quiza.uniqueid 		AS quizuniqueid,
        				quiza.id 			AS quizattemptid,
        				quiza.attempt 		AS userattemptnum,		/*1*/
        				u.id 				AS userid,
        				u.username,									/*2*/
        				u.idnumber, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.firstname, u.lastname,
        				qa.id 				AS questionattemptid,	/*3*/
        				qa.questionusageid 	AS qubaid,				/*4*/
        				qa.slot,									/*5*/
        				qa.questionid,								/*6*/
        				quiza.state,
        				quiza.timefinish,
        				quiza.timestart,
				        CASE WHEN quiza.timefinish = 0
				        		THEN null
				        	 WHEN quiza.timefinish > quiza.timestart
				        	 	THEN quiza.timefinish - quiza.timestart
				        	 ELSE 0
				        END AS duration

		        FROM		{user} 				u
		        LEFT JOIN 	{quiz_attempts} 	quiza	ON	quiza.userid 		= u.id
		        										AND quiza.quiz 			= $quiz->id
		        JOIN 		{question_attempts} qa 		ON	qa.questionusageid	= quiza.uniqueid		/*7*/
		       /* JOIN 		{user_enrolments} 	ej1_ue 	ON	ej1_ue.userid 		= u.id
		        JOIN 		{enrol} 			ej1_e 	ON	(ej1_e.id 			= ej1_ue.enrolid
														AND ej1_e.courseid 		= $course->id) */

		        WHERE
		        	quiza.preview = 0
		        	AND quiza.id IS NOT NULL
		        	AND 1 = 1
		        	AND u.deleted = 0";
    	$user_attempts = $DB->get_records_sql($sql);

    	return $user_attempts;
    }

    /**
     * Download a zip file containing quiz essay submissions.
     *
     * @param object $quiz
     * @param cm $cm
     * @param course $course
     * @param array $student_attempts Array of student's attempts to download essay submissions in a zip file
     * @return string - If an error occurs, this will contain the error notification.
     */
    protected function download_essay_submissions($quiz, $cm, $course, $student_attempts) {
    	global $CFG, $OUTPUT;

    	// More efficient to load this here.
    	require_once($CFG->libdir.'/filelib.php');

    	// Increase the server timeout to handle the creation and sending of large zip files.
    	core_php_time_limit::raise();

    	// Build a list of files to zip.
    	$filesforzipping = array();
    	$fs = get_file_storage();

    	// Construct the zip file name.
    	$filename = clean_filename($course->fullname . ' - ' .
    			$quiz->name . ' - ' .
    			$cm->id . '.zip');

    	// Get the file submissions of each student.
    	foreach ($student_attempts as $student) {

    		// Construct download folder name.
    		$userid = $student->userid;
    		$questionid = 'Q' . $student->questionid;

    		$prefix1 = str_replace('_', ' ', $questionid);

    		$prefix2 = '';
    		if(!empty($student->idnumber)) {
    			$prefix2 .= $student->idnumber;
    		} else {
    			$prefix2 .= $student->username;
    		}
    		$prefix2 .= ' - ' . str_replace('_', ' ', fullname($student));

    		$prefix3 = 'Attempt' . $student->userattemptnum.'_';

    		// Get question attempt and question context id
    		$dm = new question_engine_data_mapper();
    		$quba = $dm->load_questions_usage_by_activity($student->qubaid);
    		$qa = $quba->get_question_attempt($student->slot);
    		$quba_contextid = $quba->get_owning_context()->id;

    		if ($qa->get_question()->get_type_name() == 'essay') {
    			$name = 'attachments';
    			$questionname = $qa->get_question()->name;
    			$prefix1 .= ' - ' . $questionname;

    			// Check if attachments are allowed as response.
    			$has_responsefilearea_attachments = false;
    			$response_file_areas = $qa->get_question()->qtype->response_file_areas();
    			if (in_array($name, $response_file_areas)) {
    				$has_responsefilearea_attachments = true;
    			}

    			// Check if student has submitted any attachment.
    			$has_submitted_attachments = false;
    			$var_attachments = $qa->get_last_qt_var($name);
    			if (isset($var_attachments)) {
    			    $has_submitted_attachments = true;
    			}

    			// Get files
    			if ($has_responsefilearea_attachments && $has_submitted_attachments) {
    				$files = $qa->get_last_qt_files($name, $quba_contextid);
    			} else {
    				$files = array();
    			}

	    		foreach ($files as $zipfilepath => $file) {
	    			$zipfilename = $file->get_filename();
	    			$prefixedfilename = clean_filename($prefix1 . '/' . $prefix2);
	    			$pathfilename = $prefix1 . '/' . $prefix2 . $file->get_filepath() . $prefix3 . $zipfilename;
	    			$pathfilename = clean_param($pathfilename, PARAM_PATH);
	    			$filesforzipping[$pathfilename] = $file;
	    		}
    		}
    	}

    	$nofilesfound = false;
    	if (count($filesforzipping) == 0) {
    	    $nofilesfound= true;
    	} else if ($zipfile = $this->pack_files($filesforzipping)) {
    		// Send file and delete after sending.
    		send_temp_file($zipfile, $filename);
    		// We will not get here - send_temp_file calls exit.
    	}

    	return $nofilesfound;
    }

    /**
     * Generate zip file from array of given files.
     *
     * @param array $filesforzipping - array of files to pass into archive_to_pathname.
     *                                 This array is indexed by the final file name and each
     *                                 element in the array is an instance of a stored_file object.
     * @return path of temp file - note this returned file does
     *         not have a .zip extension - it is a temp file.
     */
    public function pack_files($filesforzipping) {
    	global $CFG;
    	// Create path for new zip file.
    	$tempzip = tempnam($CFG->tempdir . '/', 'quiz_essay_submissions_');

    	// Zip files.
    	$zipper = new zip_packer();
    	if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
    		return $tempzip;
    	}
    	return false;
    }
}