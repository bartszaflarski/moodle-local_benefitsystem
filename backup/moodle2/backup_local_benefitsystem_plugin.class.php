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
 * Backup plugin for local_benefitsystem.
 *
 * Backs up activity and course level points so they are preserved
 * across course backup / restore and duplication.
 *
 * @package     local_benefitsystem
 * @category    backup
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/moodle2/backup_local_plugin.class.php');

/**
 * Backup plugin class for local_benefitsystem.
 */
class backup_local_benefitsystem_plugin extends backup_local_plugin {

    /**
     * Defines the backup structure for module-level data.
     *
     * This backs up records from local_benefitsystem_activity linked to a coursemodule.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        // Create the plugin element (optigroup) under /module/local plugins.
        $plugin = $this->get_plugin_element();

        // Wrapper element for this plugin's data.
        $wrapper = new backup_nested_element('benefitsystem');
        $plugin->add_child($wrapper);

        // Activity configuration (points per activity).
        $activity = new backup_nested_element('activity', ['id'], ['points']);
        $wrapper->add_child($activity);

        // Source table: one row per coursemodule in local_benefitsystem_activity.
        $activity->set_source_table('local_benefitsystem_activity', [
            'coursemoduleid' => backup::VAR_MODID,
        ]);

        return $plugin;
    }

    /**
     * Defines the backup structure for course-level data.
     *
     * This backs up records from local_benefitsystem_course linked to a course.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        // Create the plugin element (optigroup) under /course/local plugins.
        $plugin = $this->get_plugin_element();

        // Wrapper element for this plugin's data (do not use name 'course' - conflicts with root).
        $wrapper = new backup_nested_element('benefitsystem_course');
        $plugin->add_child($wrapper);

        // Course configuration (points per course). Name must be unique in backup tree.
        $coursepoints = new backup_nested_element('course_points', ['id'], ['points']);
        $wrapper->add_child($coursepoints);

        // Source table: at most one row per course in local_benefitsystem_course.
        $coursepoints->set_source_table('local_benefitsystem_course', [
            'courseid' => backup::VAR_COURSEID,
        ]);

        return $plugin;
    }
}

