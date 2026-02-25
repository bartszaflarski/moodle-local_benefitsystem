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
 * Library functions for local_benefitsystem.
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Callback to extend course module form with points field in completion section.
 *
 * @param moodleform_mod $formwrapper The form wrapper
 * @param MoodleQuickForm $mform The form
 */
function local_benefitsystem_coursemodule_definition_after_data($formwrapper, $mform) {
    global $DB;

    // Get the suffix first.
    $suffix = '';
    if (method_exists($formwrapper, 'get_suffix')) {
        $suffix = $formwrapper->get_suffix();
    }

    // Only add the field if completion is enabled.
    $completionel = 'completion' . $suffix;
    if (!$mform->elementExists($completionel)) {
        return;
    }

    // Get the coursemodule ID if we're editing an existing module.
    $coursemoduleid = 0;
    if ($mform->elementExists('coursemodule')) {
        $coursemodulevalue = $mform->getElementValue('coursemodule');
        if ($coursemodulevalue && is_array($coursemodulevalue) && !empty($coursemodulevalue[0])) {
            $coursemoduleid = (int)$coursemodulevalue[0];
        } else if ($coursemodulevalue && !is_array($coursemodulevalue)) {
            $coursemoduleid = (int)$coursemodulevalue;
        }
    }

    $pointsfieldname = 'completionpoints' . $suffix;

    // Check if the field already exists to avoid duplicates.
    if ($mform->elementExists($pointsfieldname)) {
        // Field already exists, just update the value if needed.
        if ($coursemoduleid) {
            $record = $DB->get_record('local_benefitsystem_activity', ['coursemoduleid' => $coursemoduleid]);
            if ($record) {
                $mform->setDefault($pointsfieldname, $record->points);
            }
        }
        return;
    }

    // Get current points value if editing.
    $currentpoints = 0;
    if ($coursemoduleid) {
        $record = $DB->get_record('local_benefitsystem_activity', ['coursemoduleid' => $coursemoduleid]);
        if ($record) {
            $currentpoints = $record->points;
        }
    }

    // Find where to insert - after completionexpected if it exists, otherwise after completion section.
    $insertafter = 'completionexpected' . $suffix;
    if (!$mform->elementExists($insertafter)) {
        $insertafter = $completionel;
    }

    // Add the points field.
    $mform->addElement('text', $pointsfieldname, get_string('points', 'local_benefitsystem'), [
        'size' => 5,
        'maxlength' => 10
    ]);
    $mform->setType($pointsfieldname, PARAM_INT);
    $mform->setDefault($pointsfieldname, $currentpoints);
    $mform->addHelpButton($pointsfieldname, 'points', 'local_benefitsystem');
    $mform->addRule($pointsfieldname, get_string('error'), 'numeric', null, 'client');
    $mform->addRule($pointsfieldname, get_string('error'), 'regex', '/^[0-9]+$/', 'client');

    // Hide if completion tracking is disabled.
    if ($mform->elementExists($completionel)) {
        $mform->hideIf($pointsfieldname, $completionel, 'eq', COMPLETION_TRACKING_NONE);
    }

    // Try to insert after the specified element.
    if ($mform->elementExists($insertafter)) {
        try {
            $element = $mform->removeElement($pointsfieldname, false);
            $mform->insertElementBefore($element, $insertafter);
        } catch (Exception $e) {
            // If insertion fails, the element is already added, which is fine.
        }
    }
}

/**
 * Callback to save points when course module is saved.
 *
 * @param stdClass $moduleinfo The module info data
 * @param stdClass $course The course object
 * @return stdClass The updated module info
 */
function local_benefitsystem_coursemodule_edit_post_actions($moduleinfo, $course) {
    global $DB;

    // Get the coursemodule ID.
    if (empty($moduleinfo->coursemodule)) {
        return $moduleinfo;
    }

    $coursemoduleid = $moduleinfo->coursemodule;
    
    // Check for points field with various possible names (with/without suffix).
    // For bulk completion forms, fields may have suffixes that need to be stripped.
    $points = 0;
    $moduleinfoarray = (array)$moduleinfo;
    
    // Look for completionpoints field (with or without suffix).
    foreach ($moduleinfoarray as $key => $value) {
        if (strpos($key, 'completionpoints') === 0) {
            $points = (int)$value;
            break;
        }
    }

    // Ensure points is non-negative.
    if ($points < 0) {
        $points = 0;
    }

    // Get or create the record.
    $record = $DB->get_record('local_benefitsystem_activity', ['coursemoduleid' => $coursemoduleid]);
    $now = time();

    if ($record) {
        // Update existing record.
        $record->points = $points;
        $record->timemodified = $now;
        $DB->update_record('local_benefitsystem_activity', $record);
    } else if ($points > 0) {
        // Only create record if points > 0.
        $record = new stdClass();
        $record->coursemoduleid = $coursemoduleid;
        $record->points = $points;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_benefitsystem_activity', $record);
    } else if ($record && $points == 0) {
        // If points is 0 and record exists, we can optionally delete it to keep DB clean.
        // For now, we'll keep it but update it to 0.
        $record->points = 0;
        $record->timemodified = $now;
        $DB->update_record('local_benefitsystem_activity', $record);
    }

    return $moduleinfo;
}

/**
 * Get user's points balance.
 *
 * @param int $userid User ID
 * @return int Points balance
 */
function local_benefitsystem_get_user_balance($userid) {
    global $DB;

    $record = $DB->get_record('local_benefitsystem_balance', ['userid' => $userid]);
    if ($record) {
        return (int)$record->points;
    }

    // Create initial balance record if it doesn't exist.
    $record = new stdClass();
    $record->userid = $userid;
    $record->points = 0;
    $record->timecreated = time();
    $record->timemodified = time();
    $DB->insert_record('local_benefitsystem_balance', $record);

    return 0;
}

/**
 * Add points to user's balance.
 *
 * @param int $userid User ID
 * @param int $points Points to add
 * @param int $coursemoduleid Course module ID (for history)
 * @return bool Success
 */
function local_benefitsystem_add_points($userid, $points, $coursemoduleid = 0) {
    global $DB;

    if ($points <= 0) {
        return false;
    }

    $now = time();

    // Get or create balance record.
    $balance = $DB->get_record('local_benefitsystem_balance', ['userid' => $userid]);
    if (!$balance) {
        $balance = new stdClass();
        $balance->userid = $userid;
        $balance->points = 0;
        $balance->timecreated = $now;
        $balance->timemodified = $now;
        $balance->id = $DB->insert_record('local_benefitsystem_balance', $balance);
    }

    // Update balance.
    $balance->points += $points;
    $balance->timemodified = $now;
    $DB->update_record('local_benefitsystem_balance', $balance);

    // Record in history.
    if ($coursemoduleid > 0) {
        $history = new stdClass();
        $history->userid = $userid;
        $history->coursemoduleid = $coursemoduleid;
        $history->points = $points;
        $history->timecreated = $now;
        $DB->insert_record('local_benefitsystem_history', $history);
    }

    return true;
}

/**
 * Process activity completion and award points (for use by adhoc task).
 * Performs DB checks, awards points, and sends notification.
 *
 * @param int $userid User ID
 * @param int $coursemoduleid Course module ID
 * @return bool True if points were awarded, false if skipped or error
 */
function local_benefitsystem_award_activity_completion_points($userid, $coursemoduleid) {
    global $DB;

    $existing = $DB->get_record('local_benefitsystem_history', [
        'userid' => $userid,
        'coursemoduleid' => $coursemoduleid
    ]);
    if ($existing) {
        return false;
    }

    $activitypoints = $DB->get_record('local_benefitsystem_activity', [
        'coursemoduleid' => $coursemoduleid
    ]);
    if (!$activitypoints || $activitypoints->points <= 0) {
        return false;
    }

    local_benefitsystem_add_points($userid, $activitypoints->points, $coursemoduleid);
    local_benefitsystem_send_activity_points_notification($userid, $activitypoints->points, $coursemoduleid);
    return true;
}

/**
 * Process course completion and award points (for use by adhoc task).
 * Performs DB checks, awards points, and sends notification.
 *
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @return bool True if points were awarded, false if skipped or error
 */
function local_benefitsystem_award_course_completion_points($userid, $courseid) {
    global $DB;

    $existing = $DB->get_record('local_benefitsystem_history', [
        'userid' => $userid,
        'courseid' => $courseid,
        'coursemoduleid' => 0
    ]);
    if ($existing) {
        return false;
    }

    $coursepoints = $DB->get_record('local_benefitsystem_course', ['courseid' => $courseid]);
    if (!$coursepoints || $coursepoints->points <= 0) {
        return false;
    }

    $now = time();
    $balance = $DB->get_record('local_benefitsystem_balance', ['userid' => $userid]);
    if (!$balance) {
        $balance = new stdClass();
        $balance->userid = $userid;
        $balance->points = 0;
        $balance->timecreated = $now;
        $balance->timemodified = $now;
        $balance->id = $DB->insert_record('local_benefitsystem_balance', $balance);
    }
    $balance->points += $coursepoints->points;
    $balance->timemodified = $now;
    $DB->update_record('local_benefitsystem_balance', $balance);

    $history = new stdClass();
    $history->userid = $userid;
    $history->coursemoduleid = 0;
    $history->courseid = $courseid;
    $history->points = $coursepoints->points;
    $history->timecreated = $now;
    $DB->insert_record('local_benefitsystem_history', $history);

    local_benefitsystem_send_course_points_notification($userid, $coursepoints->points, $courseid);
    return true;
}

/**
 * Send notification to user about earning points for activity completion.
 *
 * @param int $userid User ID
 * @param int $points Points awarded
 * @param int $coursemoduleid Course module ID
 */
function local_benefitsystem_send_activity_points_notification($userid, $points, $coursemoduleid) {
    global $CFG;

    require_once($CFG->libdir . '/messagelib.php');

    $cm = get_coursemodule_from_id('', $coursemoduleid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return;
    }
    $activityname = $cm->name ?? get_string('pluginname', 'mod_' . $cm->modname);
    $userbalance = local_benefitsystem_get_user_balance($userid);
    $userto = \core_user::get_user($userid, '*', MUST_EXIST);

    $message = new \core\message\message();
    $message->component = 'local_benefitsystem';
    $message->name = 'points_earned';
    $message->userfrom = \core_user::get_noreply_user();
    $message->userto = $userto;
    $message->subject = get_string('pointsaddednotificationsubject', 'local_benefitsystem');
    $a = (object)[
        'points' => number_format($points),
        'activityname' => format_string($activityname),
        'totalpoints' => number_format($userbalance)
    ];
    $message->fullmessage = get_string('pointsaddednotification', 'local_benefitsystem', $a);
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = '<p>' . get_string('pointsaddednotification', 'local_benefitsystem', $a) . '</p>';
    $message->smallmessage = get_string('pointsaddednotification', 'local_benefitsystem', $a);
    $message->notification = 1;
    $message->contexturl = new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $coursemoduleid]);
    $message->contexturlname = format_string($activityname);
    message_send($message);
}

/**
 * Send notification to user about earning points for course completion.
 *
 * @param int $userid User ID
 * @param int $points Points awarded
 * @param int $courseid Course ID
 */
function local_benefitsystem_send_course_points_notification($userid, $points, $courseid) {
    global $DB, $CFG;

    require_once($CFG->libdir . '/messagelib.php');

    $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
    if (!$course) {
        return;
    }
    $coursename = format_string($course->fullname);
    $userbalance = local_benefitsystem_get_user_balance($userid);
    $userto = \core_user::get_user($userid, '*', MUST_EXIST);

    $message = new \core\message\message();
    $message->component = 'local_benefitsystem';
    $message->name = 'points_earned';
    $message->userfrom = \core_user::get_noreply_user();
    $message->userto = $userto;
    $message->subject = get_string('coursepointsaddednotificationsubject', 'local_benefitsystem');
    $a = (object)[
        'points' => number_format($points),
        'coursename' => $coursename,
        'totalpoints' => number_format($userbalance)
    ];
    $message->fullmessage = get_string('coursepointsaddednotification', 'local_benefitsystem', $a);
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = '<p>' . get_string('coursepointsaddednotification', 'local_benefitsystem', $a) . '</p>';
    $message->smallmessage = get_string('coursepointsaddednotification', 'local_benefitsystem', $a);
    $message->notification = 1;
    $message->contexturl = new \moodle_url('/course/view.php', ['id' => $courseid]);
    $message->contexturlname = $coursename;
    message_send($message);
}

/**
 * Callback to extend course completion form with points field.
 * This is called from course/completion_form.php after the form definition.
 *
 * @param course_completion_form $form The completion form
 */
function local_benefitsystem_course_completion_form_definition_after_data($form) {
    global $DB;

    // Validate form structure.
    if (!isset($form->_form)) {
        return;
    }

    $mform = $form->_form;

    // Get course from customdata if available.
    $course = null;
    $currentpoints = 0;
    if (isset($form->_customdata['course'])) {
        $course = $form->_customdata['course'];
    }
    
    // If course not in customdata, try to get it from form data.
    if (!$course && $mform->elementExists('id')) {
        $courseidvalue = $mform->getElementValue('id');
        if ($courseidvalue) {
            $courseid = is_array($courseidvalue) ? (int)$courseidvalue[0] : (int)$courseidvalue;
            if ($courseid > 0) {
                $course = $DB->get_record('course', ['id' => $courseid]);
            }
        }
    }
    
    // Get current points value if editing an existing course.
    if ($course && !empty($course->id)) {
        $record = $DB->get_record('local_benefitsystem_course', ['courseid' => $course->id]);
        if ($record) {
            $currentpoints = $record->points;
        }
    }

    $pointsfieldname = 'coursecompletionpoints';

    // Check if the field already exists to avoid duplicates.
    if ($mform->elementExists($pointsfieldname)) {
        return;
    }

    // Always add the points field - it should be available for all courses.
    // Add points field before the action buttons.
    $mform->addElement('header', 'coursepointsheader', get_string('coursepoints', 'local_benefitsystem'));
    $mform->addElement('text', $pointsfieldname, get_string('points', 'local_benefitsystem'), [
        'size' => 5,
        'maxlength' => 10
    ]);
    $mform->setType($pointsfieldname, PARAM_INT);
    $mform->setDefault($pointsfieldname, $currentpoints);
    $mform->addHelpButton($pointsfieldname, 'points', 'local_benefitsystem');
    $mform->addRule($pointsfieldname, get_string('error'), 'numeric', null, 'client');
    $mform->addRule($pointsfieldname, get_string('error'), 'regex', '/^[0-9]+$/', 'client');
    $mform->setExpanded('coursepointsheader', false);

    // Insert before action buttons if buttonar exists.
    if ($mform->elementExists('buttonar')) {
        try {
            $pointsfield = $mform->removeElement($pointsfieldname, false);
            $headerfield = $mform->removeElement('coursepointsheader', false);
            $mform->insertElementBefore($headerfield, 'buttonar');
            $mform->insertElementBefore($pointsfield, 'buttonar');
        } catch (Exception $e) {
            // If insertion fails, elements are already added which is fine.
        }
    } else {
        // If buttonar doesn't exist, add at the end of the form.
        // This ensures the field is always added even if buttonar is missing.
    }
}

/**
 * Callback to save course completion points when form is submitted.
 *
 * @param stdClass $data The form data
 * @param stdClass $course The course object
 */
function local_benefitsystem_course_completion_save($data, $course) {
    global $DB;

    $points = 0;
    if (isset($data->coursecompletionpoints)) {
        $points = (int)$data->coursecompletionpoints;
    }

    // Ensure points is non-negative.
    if ($points < 0) {
        $points = 0;
    }

    // Get or create the record.
    $record = $DB->get_record('local_benefitsystem_course', ['courseid' => $course->id]);
    $now = time();

    if ($record) {
        // Update existing record.
        $record->points = $points;
        $record->timemodified = $now;
        $DB->update_record('local_benefitsystem_course', $record);
    } else if ($points > 0) {
        // Only create record if points > 0.
        $record = new stdClass();
        $record->courseid = $course->id;
        $record->points = $points;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_benefitsystem_course', $record);
    } else if ($record && $points == 0) {
        // If points is 0 and record exists, update it to 0.
        $record->points = 0;
        $record->timemodified = $now;
        $DB->update_record('local_benefitsystem_course', $record);
    }
}

/**
 * Serve the files from the local_benefitsystem file areas.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return
 */
function local_benefitsystem_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    // Check the contextlevel is as expected.
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'reward_image') {
        return false;
    }

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // $args is empty => the path is '/'
    } else {
        $filepath = '/' . implode('/', $args) . '/'; // $args contains elements of the filepath
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_benefitsystem', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * Exchange points for a reward.
 *
 * @param int $userid User ID
 * @param int $rewardid Reward ID
 * @return bool|int Exchange ID on success, false on failure
 */
function local_benefitsystem_exchange_reward($userid, $rewardid) {
    global $DB;

    // Get reward details.
    $reward = $DB->get_record('local_benefitsystem_rewards', ['id' => $rewardid], '*', MUST_EXIST);
    
    // Check if reward is available.
    if (empty($reward->available)) {
        return false;
    }

    // For code-type rewards, quantity = number of available codes.
    if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
        $codecount = local_benefitsystem_get_available_code_count($rewardid);
        if ($codecount <= 0) {
            return false; // No codes available.
        }
    } else {
        // For other rewards, check quantity if not infinite.
        if (!is_null($reward->quantity) && $reward->quantity <= 0) {
            return false; // No quantity available.
        }
    }

    // Get user balance.
    $balance = $DB->get_record('local_benefitsystem_balance', ['userid' => $userid]);
    if (!$balance) {
        $balance = new stdClass();
        $balance->userid = $userid;
        $balance->points = 0;
        $balance->timecreated = time();
        $balance->timemodified = time();
        $balance->id = $DB->insert_record('local_benefitsystem_balance', $balance);
    }

    // Check if user has enough points.
    if ($balance->points < $reward->points) {
        return false;
    }

    // For code-type rewards, check if there are available codes.
    if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
        $availablecode = $DB->get_record('local_benefitsystem_codes', [
            'rewardid' => $rewardid,
            'used' => 0
        ], '*', IGNORE_MULTIPLE);
        
        if (!$availablecode) {
            return false; // No codes available.
        }
    }

    $now = time();

    // Deduct points from balance.
    $balance->points -= $reward->points;
    $balance->timemodified = $now;
    $DB->update_record('local_benefitsystem_balance', $balance);

    // Record the exchange.
    $exchange = new stdClass();
    $exchange->userid = $userid;
    $exchange->rewardid = $rewardid;
    $exchange->points = $reward->points;
    $exchange->status = 'completed';
    $exchange->timecreated = $now;
    $exchange->timemodified = $now;
    $exchangeid = $DB->insert_record('local_benefitsystem_exchanges', $exchange);

    // For code-type rewards, assign a code to the user.
    if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
        $availablecode = $DB->get_record('local_benefitsystem_codes', [
            'rewardid' => $rewardid,
            'used' => 0
        ], '*', IGNORE_MULTIPLE);
        
        if ($availablecode) {
            $availablecode->used = 1;
            $availablecode->exchangeid = $exchangeid;
            $availablecode->timeused = $now;
            $DB->update_record('local_benefitsystem_codes', $availablecode);
        }
    }

    // For code-type rewards, quantity is managed by codes (already handled above).
    // For other rewards, decrement quantity if not infinite.
    if ($reward->type !== 'digital' || $reward->digitalsubtype !== 'code') {
        if (!is_null($reward->quantity)) {
            $reward->quantity--;
            $reward->timemodified = $now;
            $DB->update_record('local_benefitsystem_rewards', $reward);
            
            // If quantity reaches 0, mark as unavailable.
            if ($reward->quantity <= 0) {
                $reward->available = 0;
                $DB->update_record('local_benefitsystem_rewards', $reward);
            }
        }
    } else {
        // For code-type rewards, check if we should mark as unavailable when codes run out.
        // Note: We check remaining codes AFTER assigning one, so remainingcodes should be >= 0.
        $remainingcodes = local_benefitsystem_get_available_code_count($rewardid);
        if ($remainingcodes <= 0) {
            $reward->available = 0;
            $reward->timemodified = $now;
            $DB->update_record('local_benefitsystem_rewards', $reward);
        } else {
            // If codes are available, ensure reward is marked as available.
            if (empty($reward->available)) {
                $reward->available = 1;
                $reward->timemodified = $now;
                $DB->update_record('local_benefitsystem_rewards', $reward);
            }
        }
    }

    return $exchangeid;
}

/**
 * Get user's exchanged rewards.
 *
 * @param int $userid User ID
 * @return array Array of exchange records with reward details
 */
function local_benefitsystem_get_user_exchanges($userid) {
    global $DB;

    $exchanges = $DB->get_records('local_benefitsystem_exchanges',
        ['userid' => $userid, 'status' => 'completed'],
        'timecreated DESC'
    );

    if (empty($exchanges)) {
        return [];
    }

    // Bulk load rewards (avoid N+1).
    $rewardids = array_unique(array_map(function($e) {
        return $e->rewardid;
    }, $exchanges));
    $rewards = $DB->get_records_list('local_benefitsystem_rewards', 'id', $rewardids);
    if (empty($rewards)) {
        return [];
    }

    // Bulk load codes for code-type rewards (used=1, exchangeid IN (...)).
    $codeexchangeids = [];
    foreach ($exchanges as $exchange) {
        $r = $rewards[$exchange->rewardid] ?? null;
        if ($r && $r->type === 'digital' && $r->digitalsubtype === 'code') {
            $codeexchangeids[] = $exchange->id;
        }
    }
    $codesbyexchange = [];
    if (!empty($codeexchangeids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($codeexchangeids, SQL_PARAMS_NAMED);
        $inparams['used'] = 1;
        $codes = $DB->get_records_sql(
            "SELECT id, exchangeid, code FROM {local_benefitsystem_codes} WHERE used = :used AND exchangeid $insql",
            $inparams
        );
        foreach ($codes as $c) {
            $codesbyexchange[$c->exchangeid] = $c->code;
        }
    }

    $result = [];
    foreach ($exchanges as $exchange) {
        $reward = $rewards[$exchange->rewardid] ?? null;
        if (!$reward) {
            continue;
        }
        $exchange->reward = $reward;
        if ($reward->type === 'digital' && $reward->digitalsubtype === 'code' && isset($codesbyexchange[$exchange->id])) {
            $exchange->code = $codesbyexchange[$exchange->id];
        }
        $result[] = $exchange;
    }

    return $result;
}

/**
 * Mark exchange as redeemed and move to history.
 *
 * @param int $exchangeid Exchange ID
 * @param int $userid User ID (for security check)
 * @return bool Success
 */
function local_benefitsystem_mark_as_redeemed($exchangeid, $userid) {
    global $DB;
    
    // Get the exchange record.
    $exchange = $DB->get_record('local_benefitsystem_exchanges', [
        'id' => $exchangeid,
        'userid' => $userid,
        'status' => 'completed'
    ]);
    
    if (!$exchange) {
        return false;
    }
    
    // Get the code if it's a code-type reward.
    $code = null;
    $reward = $DB->get_record('local_benefitsystem_rewards', ['id' => $exchange->rewardid]);
    if ($reward && $reward->type === 'digital' && $reward->digitalsubtype === 'code') {
        $coderecord = $DB->get_record('local_benefitsystem_codes', [
            'exchangeid' => $exchangeid,
            'used' => 1
        ]);
        if ($coderecord) {
            $code = $coderecord->code;
        }
    }
    
    // Create history record.
    $history = new stdClass();
    $history->userid = $exchange->userid;
    $history->rewardid = $exchange->rewardid;
    $history->points = $exchange->points;
    $history->code = $code;
    $history->timecreated = $exchange->timecreated;
    $history->timeredeemed = time();
    
    $DB->insert_record('local_benefitsystem_exchange_history', $history);
    
    // Delete the exchange record.
    $DB->delete_records('local_benefitsystem_exchanges', ['id' => $exchangeid]);
    
    return true;
}

/**
 * Get user's exchange history (redeemed exchanges).
 *
 * @param int $userid User ID
 * @return array Array of history records with reward details
 */
function local_benefitsystem_get_user_exchange_history($userid) {
    global $DB;

    $history = $DB->get_records('local_benefitsystem_exchange_history',
        ['userid' => $userid],
        'timeredeemed DESC'
    );

    if (empty($history)) {
        return [];
    }

    $rewardids = array_unique(array_map(function($r) {
        return $r->rewardid;
    }, $history));
    $rewards = $DB->get_records_list('local_benefitsystem_rewards', 'id', $rewardids);
    if (empty($rewards)) {
        return [];
    }

    $result = [];
    foreach ($history as $record) {
        $reward = $rewards[$record->rewardid] ?? null;
        if (!$reward) {
            continue;
        }
        $record->reward = $reward;
        $result[] = $record;
    }

    return $result;
}

/**
 * Get all exchanges from all users (for admin purchase history).
 *
 * @return array Array of exchange records with reward and user details
 */
function local_benefitsystem_get_all_exchanges() {
    global $DB;

    $exchanges = $DB->get_records('local_benefitsystem_exchanges',
        ['status' => 'completed'],
        'timecreated DESC'
    );

    if (empty($exchanges)) {
        return [];
    }

    $rewardids = array_unique(array_map(function($e) {
        return $e->rewardid;
    }, $exchanges));
    $userids = array_unique(array_map(function($e) {
        return $e->userid;
    }, $exchanges));
    $rewards = $DB->get_records_list('local_benefitsystem_rewards', 'id', $rewardids);
    $users = $DB->get_records_list('user', 'id', $userids, '',
        'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email');

    $exchangeids = array_map(function($e) {
        return $e->id;
    }, $exchanges);
    $codesbyexchange = [];
    if (!empty($exchangeids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($exchangeids, SQL_PARAMS_NAMED);
        $inparams['used'] = 1;
        $codes = $DB->get_records_sql(
            "SELECT id, exchangeid, code FROM {local_benefitsystem_codes} WHERE used = :used AND exchangeid $insql",
            $inparams
        );
        foreach ($codes as $c) {
            $codesbyexchange[$c->exchangeid] = $c->code;
        }
    }

    $result = [];
    foreach ($exchanges as $exchange) {
        $reward = $rewards[$exchange->rewardid] ?? null;
        $user = $users[$exchange->userid] ?? null;
        if (!$reward || !$user) {
            continue;
        }
        $exchange->reward = $reward;
        $exchange->user = $user;
        if ($reward->type === 'digital' && $reward->digitalsubtype === 'code' && isset($codesbyexchange[$exchange->id])) {
            $exchange->code = $codesbyexchange[$exchange->id];
        }
        $result[] = $exchange;
    }

    return $result;
}

/**
 * Get all exchange history from all users (for admin purchase history).
 *
 * @return array Array of history records with reward and user details
 */
function local_benefitsystem_get_all_exchange_history() {
    global $DB;

    $history = $DB->get_records('local_benefitsystem_exchange_history',
        null,
        'timeredeemed DESC'
    );

    if (empty($history)) {
        return [];
    }

    $rewardids = array_unique(array_map(function($r) {
        return $r->rewardid;
    }, $history));
    $userids = array_unique(array_map(function($r) {
        return $r->userid;
    }, $history));
    $rewards = $DB->get_records_list('local_benefitsystem_rewards', 'id', $rewardids);
    $users = $DB->get_records_list('user', 'id', $userids, '',
        'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email');

    $result = [];
    foreach ($history as $record) {
        $reward = $rewards[$record->rewardid] ?? null;
        $user = $users[$record->userid] ?? null;
        if (!$reward || !$user) {
            continue;
        }
        $record->reward = $reward;
        $record->user = $user;
        $result[] = $record;
    }

    return $result;
}

/**
 * Parse codes from textarea (one per line).
 *
 * @param string $text Text containing codes (one per line)
 * @return array Array of unique codes
 */
function local_benefitsystem_parse_codes_from_text($text) {
    if (empty($text)) {
        return [];
    }
    
    $lines = explode("\n", $text);
    $codes = [];
    foreach ($lines as $line) {
        $code = trim($line);
        if (!empty($code)) {
            $codes[] = $code;
        }
    }
    
    // Remove duplicates and return.
    return array_unique($codes);
}

/**
 * Parse codes from CSV file.
 *
 * @param stored_file $file CSV file
 * @return array Array of unique codes
 */
function local_benefitsystem_parse_codes_from_csv($file) {
    if (!$file) {
        return [];
    }
    
    $content = $file->get_content();
    if (empty($content)) {
        return [];
    }
    
    // Parse CSV - codes can be in first column or one per line.
    $lines = explode("\n", $content);
    $codes = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        // Try to parse as CSV line.
        $parts = str_getcsv($line);
        if (!empty($parts)) {
            // Use first column as code.
            $code = trim($parts[0]);
            if (!empty($code)) {
                $codes[] = $code;
            }
        }
    }
    
    // Remove duplicates and return.
    return array_unique($codes);
}

/**
 * Save codes for a reward.
 *
 * @param int $rewardid Reward ID
 * @param array $codes Array of codes to save
 * @return int Number of codes saved
 */
function local_benefitsystem_save_reward_codes($rewardid, $codes) {
    global $DB;
    
    if (empty($codes)) {
        return 0;
    }
    
    $now = time();
    $saved = 0;
    
    foreach ($codes as $code) {
        $code = trim($code);
        if (empty($code)) {
            continue;
        }
        
        // Check if code already exists for this reward.
        $existing = $DB->get_record('local_benefitsystem_codes', [
            'rewardid' => $rewardid,
            'code' => $code
        ]);
        
        if (!$existing) {
            $coderecord = new stdClass();
            $coderecord->rewardid = $rewardid;
            $coderecord->code = $code;
            $coderecord->used = 0;
            $coderecord->exchangeid = 0;
            $coderecord->timecreated = $now;
            $coderecord->timeused = 0;
            $DB->insert_record('local_benefitsystem_codes', $coderecord);
            $saved++;
        }
    }
    
    return $saved;
}

/**
 * Get available code count for a reward.
 *
 * @param int $rewardid Reward ID
 * @return int Number of unused codes available
 */
function local_benefitsystem_get_available_code_count($rewardid) {
    global $DB;
    
    return $DB->count_records('local_benefitsystem_codes', [
        'rewardid' => $rewardid,
        'used' => 0
    ]);
}
