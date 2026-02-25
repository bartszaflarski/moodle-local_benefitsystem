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
 * Observers stay lightweight by deferring heavy work to an adhoc task.
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_benefitsystem;

defined('MOODLE_INTERNAL') || die();

/**
 * Observer class for handling events.
 */
class observer {

    /**
     * Handle course module completion updated event.
     * Queues an adhoc task to award points and send notification (no heavy work inline).
     *
     * @param \core\event\course_module_completion_updated $event The event
     */
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event) {
        $snapshot = $event->get_record_snapshot('course_modules_completion', $event->objectid);
        if (!$snapshot || ($snapshot->completionstate != COMPLETION_COMPLETE && $snapshot->completionstate != COMPLETION_COMPLETE_PASS)) {
            return;
        }

        $task = new \local_benefitsystem\task\award_completion_points_task();
        $task->set_custom_data((object)[
            'userid' => $snapshot->userid,
            'coursemoduleid' => $snapshot->coursemoduleid,
            'courseid' => 0,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Handle course completed event.
     * Queues an adhoc task to award points and send notification (no heavy work inline).
     *
     * @param \core\event\course_completed $event The event
     */
    public static function course_completed(\core\event\course_completed $event) {
        $snapshot = $event->get_record_snapshot('course_completions', $event->objectid);
        if (!$snapshot) {
            return;
        }

        $task = new \local_benefitsystem\task\award_completion_points_task();
        $task->set_custom_data((object)[
            'userid' => $snapshot->userid,
            'coursemoduleid' => 0,
            'courseid' => $snapshot->course,
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }
}
