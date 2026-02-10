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
 * Event observer for local_benefitsystem.
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_benefitsystem;

defined('MOODLE_INTERNAL') || die();

// Ensure lib.php is loaded so global functions are available.
require_once(__DIR__ . '/../lib.php');

/**
 * Observer class for handling events.
 */
class observer {

    /**
     * Handle course module completion updated event.
     *
     * @param \core\event\course_module_completion_updated $event The event
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event) {
        global $DB, $CFG;

        // Get the completion data.
        $completiondata = $event->get_record_snapshot('course_modules_completion', $event->objectid);
        if (!$completiondata) {
            return;
        }

        // Only award points if the activity is marked as complete.
        if ($completiondata->completionstate != COMPLETION_COMPLETE &&
            $completiondata->completionstate != COMPLETION_COMPLETE_PASS) {
            return;
        }

        // Check if this completion was already processed (to avoid duplicate awards).
        // We check if there's already a history record for this user and coursemodule.
        $existing = $DB->get_record('local_benefitsystem_history', [
            'userid' => $completiondata->userid,
            'coursemoduleid' => $completiondata->coursemoduleid
        ]);

        if ($existing) {
            // Points already awarded for this completion.
            return;
        }

        // Get points configuration for this activity.
        $activitypoints = $DB->get_record('local_benefitsystem_activity', [
            'coursemoduleid' => $completiondata->coursemoduleid
        ]);

        if (!$activitypoints || $activitypoints->points <= 0) {
            // No points configured for this activity.
            return;
        }

        // Award points to the user.
        \local_benefitsystem_add_points(
            $completiondata->userid,
            $activitypoints->points,
            $completiondata->coursemoduleid
        );

        // Send notification to the user about earning points.
        self::send_points_notification(
            $completiondata->userid,
            $activitypoints->points,
            $completiondata->coursemoduleid
        );
    }

    /**
     * Send notification to user about earning points.
     *
     * @param int $userid User ID
     * @param int $points Points awarded
     * @param int $coursemoduleid Course module ID
     */
    private static function send_points_notification($userid, $points, $coursemoduleid) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/messagelib.php');

        // Get course module info.
        $cm = get_coursemodule_from_id('', $coursemoduleid, 0, false, MUST_EXIST);
        
        // Get activity name - use the course module name if available.
        $activityname = $cm->name ?? get_string('pluginname', 'mod_' . $cm->modname);

        // Get user's total balance after the award.
        $userbalance = \local_benefitsystem_get_user_balance($userid);

        // Get user object.
        $userto = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // Prepare notification message.
        $message = new \core\message\message();
        $message->component = 'local_benefitsystem';
        $message->name = 'points_earned';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $userto;
        $message->subject = get_string('pointsaddednotificationsubject', 'local_benefitsystem');
        
        // Format the message.
        $a = (object)[
            'points' => number_format($points),
            'activityname' => format_string($activityname),
            'totalpoints' => number_format($userbalance)
        ];
        $message->fullmessage = get_string('pointsaddednotification', 'local_benefitsystem', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . get_string('pointsaddednotification', 'local_benefitsystem', $a) . '</p>';
        $message->smallmessage = get_string('pointsaddednotification', 'local_benefitsystem', $a);
        $message->notification = 1; // This is a notification, not a personal message.

        // Set context URL to the activity.
        $message->contexturl = new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $coursemoduleid]);
        $message->contexturlname = format_string($activityname);

        // Send the notification.
        message_send($message);
    }

    /**
     * Handle course completed event.
     *
     * @param \core\event\course_completed $event The event
     */
    public static function course_completed(\core\event\course_completed $event) {
        global $DB, $CFG;

        // Get the course completion data.
        $completiondata = $event->get_record_snapshot('course_completions', $event->objectid);
        if (!$completiondata) {
            return;
        }

        // Check if this completion was already processed (to avoid duplicate awards).
        // We check if there's already a history record for this user and course.
        $existing = $DB->get_record('local_benefitsystem_history', [
            'userid' => $completiondata->userid,
            'courseid' => $completiondata->course,
            'coursemoduleid' => 0
        ]);

        if ($existing) {
            // Points already awarded for this completion.
            return;
        }

        // Get points configuration for this course.
        $coursepoints = $DB->get_record('local_benefitsystem_course', [
            'courseid' => $completiondata->course
        ]);

        if (!$coursepoints || $coursepoints->points <= 0) {
            // No points configured for this course.
            return;
        }

        // Award points to the user.
        $now = time();
        
        // Get or create balance record.
        $balance = $DB->get_record('local_benefitsystem_balance', ['userid' => $completiondata->userid]);
        if (!$balance) {
            $balance = new stdClass();
            $balance->userid = $completiondata->userid;
            $balance->points = 0;
            $balance->timecreated = $now;
            $balance->timemodified = $now;
            $balance->id = $DB->insert_record('local_benefitsystem_balance', $balance);
        }

        // Update balance.
        $balance->points += $coursepoints->points;
        $balance->timemodified = $now;
        $DB->update_record('local_benefitsystem_balance', $balance);

        // Record in history with courseid (coursemoduleid = 0 for course completion).
        $history = new stdClass();
        $history->userid = $completiondata->userid;
        $history->coursemoduleid = 0; // Use 0 for course completion.
        $history->courseid = $completiondata->course; // Store course ID.
        $history->points = $coursepoints->points;
        $history->timecreated = $now;
        $DB->insert_record('local_benefitsystem_history', $history);

        // Send notification to the user about earning points.
        self::send_course_points_notification(
            $completiondata->userid,
            $coursepoints->points,
            $completiondata->course
        );
    }

    /**
     * Send notification to user about earning points for course completion.
     *
     * @param int $userid User ID
     * @param int $points Points awarded
     * @param int $courseid Course ID
     */
    private static function send_course_points_notification($userid, $points, $courseid) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/messagelib.php');

        // Get course info.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursename = format_string($course->fullname);

        // Get user's total balance after the award.
        $userbalance = \local_benefitsystem_get_user_balance($userid);

        // Get user object.
        $userto = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

        // Prepare notification message.
        $message = new \core\message\message();
        $message->component = 'local_benefitsystem';
        $message->name = 'points_earned';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $userto;
        $message->subject = get_string('coursepointsaddednotificationsubject', 'local_benefitsystem');
        
        // Format the message.
        $a = (object)[
            'points' => number_format($points),
            'coursename' => $coursename,
            'totalpoints' => number_format($userbalance)
        ];
        $message->fullmessage = get_string('coursepointsaddednotification', 'local_benefitsystem', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '<p>' . get_string('coursepointsaddednotification', 'local_benefitsystem', $a) . '</p>';
        $message->smallmessage = get_string('coursepointsaddednotification', 'local_benefitsystem', $a);
        $message->notification = 1; // This is a notification, not a personal message.

        // Set context URL to the course.
        $message->contexturl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        $message->contexturlname = $coursename;

        // Send the notification.
        message_send($message);
    }
}
