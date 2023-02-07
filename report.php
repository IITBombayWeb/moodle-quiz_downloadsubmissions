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
 * Support for randomly selected essay questions included
 * as suggested by gabriosecco
 * (https://github.com/IITBombayWeb/moodle-quiz_downloadsubmissions/issues/2#issuecomment-613266125)
 *
 * @package   quiz_downloadsubmissions
 * @copyright 2017 IIT Bombay
 * @author      Kashmira Nagwekar
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

    /**
     * Display the report.
     *
     * @param stdClass $quiz this quiz.
     * @param stdClass $cm the course-module for this quiz.
     * @param stdClass $course the coures we are in.
     */
    public function display($quiz, $cm, $course) {
        global $OUTPUT, $DB;

        $mform = new quiz_downloadsubmissions_settings_form();

        // Load the required questions.
        $questions = quiz_report_get_significant_questions($quiz);

        // Check if the quiz contains essay type questions.
        // Method 1 : Check $questions object for existence essay type questions.
        $hasessayquestions = false;
        if ($questions) {
            foreach ($questions as $question) {
                if ($question->qtype == 'essay' || $question->qtype == 'random') {
                    $hasessayquestions = true;
                    break;
                }
            }
        }
        // Method 2 : Check {quiz_slots} table.

        $hasstudents = false;
        $sql = "SELECT DISTINCT u.id
                           FROM {user} u
                           JOIN {user_enrolments} ej1_ue ON ej1_ue.userid = u.id
                           JOIN {enrol} ej1_e ON (ej1_e.id = ej1_ue.enrolid
                                AND ej1_e.courseid = $course->id)
                          WHERE 1 = 1 AND u.deleted = 0";
        $hasstudents = $DB->record_exists_sql($sql);

        $downloadingsubmissions = false;
        $dsbuttonclicked = false;
        $userattempts = false;
        $hassubmissions = false;

        // Check if downloading file submissions.
        if ($data = $mform->get_data()) {
            if ($dsbuttonclicked = !empty($data->downloadsubmissions)) {
                $userattempts = $this->get_user_attempts($quiz, $course);
                $downloadingsubmissions = $this->downloading_submissions($dsbuttonclicked, $hasessayquestions, $userattempts);

                   // Download file submissions for essay questions.
                if ($downloadingsubmissions) {
                    // If no attachments are found then it returns true;
                    // else returns zip folder with attachments submitted by the students.
                    $hassubmissions = $this->download_essay_submissions($quiz, $cm, $course, $userattempts, $data);
                }
            }
        }

        // Start output.
        if (!$downloadingsubmissions | !$hassubmissions) {
            // Only print headers if not asked to download data.
            $this->print_header_and_tabs($cm, $course, $quiz, 'downloadsubmissions');
        }

        $currentgroup = null;
        // Print information on the number of existing attempts.
        if (!$downloadingsubmissions | !$hassubmissions) {
            // Do not print notices when downloading.
            if ($strattemptnum = quiz_num_attempt_summary($quiz, $cm, true, $currentgroup)) {
                echo '<div class="quizattemptcounts">' . $strattemptnum . '</div>';
            }
        }

        $hasquestions = quiz_has_questions($quiz->id);

        if (!$downloadingsubmissions | !$hassubmissions) {
            if ($dsbuttonclicked) {
                if (!$hasquestions) {
                    echo $OUTPUT->notification(get_string('noquestions', 'quiz_downloadsubmissions'));
                } else if (!$hasstudents) {
                    echo $OUTPUT->notification(get_string('nostudentsyet'));
                } else if (!$hasessayquestions) {
                    echo $OUTPUT->notification(get_string('noessayquestion', 'quiz_downloadsubmissions'));
                } else if (!$userattempts) {
                    echo $OUTPUT->notification(get_string('noattempts', 'quiz_downloadsubmissions'));
                } else if (!$hassubmissions) {
                    echo $OUTPUT->notification(get_string('nosubmission', 'quiz_downloadsubmissions'));
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

    /**
     * Whether it is downloading.
     *
     * @param bool $dsbuttonclicked
     * @param bool $hasessayquestions
     * @param bool $userattempts
     * @return bool
     */
    public function downloading_submissions($dsbuttonclicked, $hasessayquestions, $userattempts) {
        if ($dsbuttonclicked && $hasessayquestions && $userattempts) {
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
     * Get user attempts (quiz attempt alongwith question attempts) : Method 1
     *
     * @param stdClass $quiz this quiz.
     * @param course $course
     * @return array
     * @throws dml_exception
     */
    public function get_user_attempts($quiz, $course) {
        global $DB;

        $sql = "SELECT DISTINCT CONCAT(u.id, '#', COALESCE(qa.id, 0)) AS uniqueid,
                        quiza.uniqueid AS quizuniqueid,
                        quiza.id AS quizattemptid,
                        quiza.attempt AS userattemptnum,        /*1*/
                        u.id AS userid,
                        u.username,                                    /*2*/
                        u.idnumber, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.firstname, u.lastname,
                        qa.id AS questionattemptid,    /*3*/
                        qa.questionusageid AS qubaid,                /*4*/
                        qa.slot,                                    /*5*/
                        qa.questionid,                                /*6*/
                        quiza.state,
                        quiza.timefinish,
                        quiza.timestart,
                        CASE WHEN quiza.timefinish = 0
                                THEN null
                             WHEN quiza.timefinish > quiza.timestart
                                 THEN quiza.timefinish - quiza.timestart
                             ELSE 0
                        END AS duration

                  FROM {user} u
             LEFT JOIN {quiz_attempts} quiza ON quiza.userid = u.id
                       AND quiza.quiz = $quiz->id
                  JOIN {question_attempts} qa ON qa.questionusageid    = quiza.uniqueid /*7*/

                 WHERE quiza.preview = 0
                       AND quiza.id IS NOT NULL
                       AND 1 = 1
                       AND u.deleted = 0";
        $userattempts = $DB->get_records_sql($sql);

        return $userattempts;
    }

    /**
     * Download a zip file containing quiz essay submissions.
     *
     * @param object $quiz
     * @param cm $cm
     * @param course $course
     * @param array $studentattempts Array of student's attempts to download essay submissions in a zip file
     * @param object $data Data to download
     * @return string - If an error occurs, this will contain the error notification.
     */
    protected function download_essay_submissions($quiz, $cm, $course, $studentattempts, $data = null) {
        global $CFG;

        // More efficient to load this here.
        require_once($CFG->libdir.'/filelib.php');

        // Increase the server timeout to handle the creation and sending of large zip files.
        core_php_time_limit::raise();

        // Build a list of files to zip.
        $filesforzipping = array();
        $fs = get_file_storage();
        $context = context_course::instance($course->id);

        // Construct the zip file name.
        $filename = clean_filename($course->fullname . ' - ' .
                $quiz->name . ' - ' .
                $cm->id . '.zip');

        // Get the file submissions of each student.
        foreach ($studentattempts as $student) {

            // Construct download folder name.
            $userid = $student->userid;
            $questionid = 'Q' . $student->slot;   // Or use slot number from {quiz_slots} table.

            $prefix1 = str_replace('_', ' ', $questionid);

            $prefix2 = '';
            if (!empty($student->idnumber)) {
                $prefix2 .= $student->idnumber;
            } else {
                $prefix2 .= $student->username;
            }
            $prefix2 .= ' - ' . str_replace('_', ' ', fullname($student)) . ' - ' . 'Attempt' . $student->userattemptnum .
                ' - '. date("Y-m-d g_i a", $student->timestart);

            $prefix3 = 'Attempt' . $student->userattemptnum . '_';

            // Get question attempt and question context id.
            $dm = new question_engine_data_mapper();
            $quba = $dm->load_questions_usage_by_activity($student->qubaid);
            $qa = $quba->get_question_attempt($student->slot);
            $qubacontextid = $quba->get_owning_context()->id;

            if ($qa->get_question()->get_type_name() == 'essay') {
                $questionname = $qa->get_question()->name;
                $prefix1 .= ' - ' . $questionname;

                $qa->get_question();  // Question object. (Has qt related info like responserequired, attachmentsrequired etc.).

                // Writing question text to a file.
                $questiontextfile = null;
                if ($data->questiontext == 1) {
                    if (!empty($qa->get_question_summary())) {
                        $qttextfilename = $questionid . ' - ' . $questionname . ' - ' . 'questiontext';
                        $qttextfileinfo = array (
                                'contextid' => $context->id,
                                'component' => 'quiz_downloadsubmissions',
                                'filearea'  => 'content',
                                'itemid'    => 0,
                                'filepath'  => '/',
                                'filename'  => $qttextfilename . '.text');

                        if (!$fs->file_exists(
                                $qttextfileinfo['contextid'],
                                $qttextfileinfo['component'],
                                $qttextfileinfo['filearea'],
                                $qttextfileinfo['itemid'],
                                $qttextfileinfo['filepath'],
                                $qttextfileinfo['filename'])) {
                            $fs->create_file_from_string($qttextfileinfo, $qa->get_question_summary());
                        }

                        $questiontextfile = $fs->get_file(
                            $qttextfileinfo['contextid'],
                            $qttextfileinfo['component'],
                            $qttextfileinfo['filearea'],
                            $qttextfileinfo['itemid'],
                            $qttextfileinfo['filepath'],
                            $qttextfileinfo['filename']);
                    }
                }

                // Writing text response to a file.
                $textfile = null;
                $hastextresponse = false;
                if ($data->textresponse == 1) {
                    if (!empty($qa->get_response_summary())) {
                        $hastextresponse = true;
                        $textfilename = $prefix1 . ' - ' . $prefix2 . ' - ' . 'textresponse';
                        $textfileinfo = array (
                                'contextid' => $context->id,
                                'component' => 'quiz_downloadsubmissions',
                                'filearea'  => 'content',
                                'itemid'    => 0,
                                'filepath'  => '/',
                                'filename'  => $textfilename . '.text');

                        if (!$fs->file_exists(
                                $textfileinfo['contextid'],
                                $textfileinfo['component'],
                                $textfileinfo['filearea'],
                                $textfileinfo['itemid'],
                                $textfileinfo['filepath'],
                                $textfileinfo['filename'])) {
                            $fs->create_file_from_string($textfileinfo, $qa->get_response_summary());
                        }

                        $textfile = $fs->get_file(
                            $textfileinfo['contextid'],
                            $textfileinfo['component'],
                            $textfileinfo['filearea'],
                            $textfileinfo['itemid'],
                            $textfileinfo['filepath'],
                            $textfileinfo['filename']);
                    }
                }

                // Fetching attachments.
                $name = 'attachments';

                // Check if attachments are allowed as response.
                $hasresponsefileareaattachments = false;
                $responsefileareas = $qa->get_question()->qtype->response_file_areas();
                if (in_array($name, $responsefileareas)) {
                    $hasresponsefileareaattachments = true;
                }

                // Check if student has submitted any attachment.
                $hassubmittedattachments = false;
                $varattachments = $qa->get_last_qt_var($name);
                if (isset($varattachments)) {
                    $hassubmittedattachments = true;
                }

                // Get files.
                if ($hasresponsefileareaattachments && $hassubmittedattachments) {
                    $files = $qa->get_last_qt_files($name, $qubacontextid);
                } else {
                    $files = array();
                }

                // Set the download folder hierarchy.
                if ($data->folders == 'questionwise') {
                    $prefixedfilename = clean_filename($prefix1 . '/' . $prefix2);
                    $pathprefix = $prefix1 . '/' . $prefix2;
                } else if ($data->folders == 'attemptwise') {
                    $prefixedfilename = clean_filename($prefix2 . '/' . $prefix1);
                    $pathprefix = $prefix2 . '/' . $prefix1;
                }

                // Send files for zipping.
                // I. File attachments/submissions.
                $fscount = 0;
                foreach ($files as $zipfilepath => $file) {
                    $fscount++;
                    $zipfilename = $file->get_filename();
                    $pathfilename = $pathprefix . $file->get_filepath() . $prefix3 . 'filesubmission' . '_' . $zipfilename;
                    $pathfilename = clean_param($pathfilename, PARAM_PATH);
                    $filesforzipping[$pathfilename] = $file;
                }

                // II. File containing text response.
                if ($textfile) {
                    $zipfilename = $textfile->get_filename();
                    $pathfilename = $pathprefix . $textfile->get_filepath() . $prefix3 . 'textresponse';
                    $pathfilename = clean_param($pathfilename, PARAM_PATH);
                    $filesforzipping[$pathfilename] = $textfile;
                }

                // III. File containing question text.
                if (!empty($files) | $hastextresponse) {
                    if ($questiontextfile) {
                        $zipfilename = $questiontextfile->get_filename();

                        if ($data->folders == 'questionwise') {
                            $pathfilename = $prefix1 . $questiontextfile->get_filepath() . 'Question text';
                        } else if ($data->folders == 'attemptwise') {
                            $pathfilename = $pathprefix . $questiontextfile->get_filepath() . 'Question text';
                        }
                        $pathfilename = clean_param($pathfilename, PARAM_PATH);
                        $filesforzipping[$pathfilename] = $questiontextfile;
                    }
                }
            }
        }

        $hassubmissions = true;
        if (count($filesforzipping) == 0) {
            $hassubmissions = false;
        } else if ($zipfile = $this->pack_files($filesforzipping)) {
            // Send file and delete after sending.
            send_temp_file($zipfile, $filename);
            // We will not get here - send_temp_file calls exit.
        }

        return $hassubmissions;
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
