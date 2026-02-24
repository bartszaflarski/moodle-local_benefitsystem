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
 * Restore plugin for local_benefitsystem.
 *
 * Restores activity and course level points from backups created
 * by backup_local_benefitsystem_plugin.
 *
 * @package     local_benefitsystem
 * @category    backup
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/moodle2/restore_local_plugin.class.php');

/**
 * Restore plugin class for local_benefitsystem.
 */
class restore_local_benefitsystem_plugin extends restore_local_plugin {

    /**
     * Define structure for module-level restore.
     *
     * @return restore_path_element[]
     */
    public function define_module_plugin_structure() {
        $paths = [];

        // Path under /module/local plugins, matching backup structure.
        $paths[] = new restore_path_element('local_benefitsystem_activity',
            $this->get_pathfor('/benefitsystem/activity'));

        return $paths;
    }

    /**
     * Process one activity-level record during restore.
     *
     * @param array $data
     */
    public function process_local_benefitsystem_activity($data) {
        global $DB;

        $data = (object)$data;

        // Map to the new coursemodule id in this restore.
        $cmid = $this->get_task()->get_moduleid();
        if (empty($cmid)) {
            return;
        }

        $now = time();

        // Ensure points are non-negative integer.
        $points = isset($data->points) ? (int)$data->points : 0;
        if ($points < 0) {
            $points = 0;
        }

        // Update existing record if present, otherwise insert.
        if ($record = $DB->get_record('local_benefitsystem_activity', ['coursemoduleid' => $cmid])) {
            $record->points = $points;
            $record->timemodified = $now;
            $DB->update_record('local_benefitsystem_activity', $record);
        } else {
            $record = new stdClass();
            $record->coursemoduleid = $cmid;
            $record->points = $points;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_benefitsystem_activity', $record);
        }
    }

    /**
     * Define structure for course-level restore.
     *
     * @return restore_path_element[]
     */
    public function define_course_plugin_structure() {
        $paths = [];

        // Path under /course/local plugins, matching backup structure.
        $paths[] = new restore_path_element('local_benefitsystem_course',
            $this->get_pathfor('/benefitsystem_course/course'));

        return $paths;
    }

    /**
     * Process one course-level record during restore.
     *
     * @param array $data
     */
    public function process_local_benefitsystem_course($data) {
        global $DB;

        $data = (object)$data;

        $courseid = $this->get_task()->get_courseid();
        if (empty($courseid)) {
            return;
        }

        $now = time();

        // Ensure points are non-negative integer.
        $points = isset($data->points) ? (int)$data->points : 0;
        if ($points < 0) {
            $points = 0;
        }

        // Update existing record if present, otherwise insert.
        if ($record = $DB->get_record('local_benefitsystem_course', ['courseid' => $courseid])) {
            $record->points = $points;
            $record->timemodified = $now;
            $DB->update_record('local_benefitsystem_course', $record);
        } else {
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->points = $points;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_benefitsystem_course', $record);
        }
    }
}

