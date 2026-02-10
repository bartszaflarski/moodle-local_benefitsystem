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
 * Hook callbacks for local_benefitsystem
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_benefitsystem;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks class
 */
class hook_callbacks {
    /**
     * Extend course edit form with Benefit points section
     *
     * @param \core_course\hook\after_form_definition $hook
     */
    public static function course_form_definition(\core_course\hook\after_form_definition $hook): void {
        global $DB;

        $formwrapper = $hook->formwrapper;
        $mform = $hook->mform;

        // Get course from form wrapper
        $course = $formwrapper->get_course();
        $currentpoints = 0;

        // Get current points value if editing an existing course
        if ($course && !empty($course->id)) {
            $record = $DB->get_record('local_benefitsystem_course', ['courseid' => $course->id]);
            if ($record) {
                $currentpoints = $record->points;
            }
        }

        $pointsfieldname = 'benefitpoints';

        // Check if the field already exists to avoid duplicates
        if ($mform->elementExists($pointsfieldname)) {
            return;
        }

        // Add Benefit points section header
        $mform->addElement('header', 'benefitpointsheader', get_string('benefitpoints', 'local_benefitsystem'));
        $mform->setExpanded('benefitpointsheader', false);

        // Add the points field
        $mform->addElement('text', $pointsfieldname, get_string('points', 'local_benefitsystem'), [
            'size' => 5,
            'maxlength' => 10
        ]);
        $mform->setType($pointsfieldname, PARAM_INT);
        $mform->setDefault($pointsfieldname, $currentpoints);
        $mform->addHelpButton($pointsfieldname, 'benefitpoints', 'local_benefitsystem');
        $mform->addRule($pointsfieldname, get_string('error'), 'numeric', null, 'client');
        $mform->addRule($pointsfieldname, get_string('error'), 'regex', '/^[0-9]+$/', 'client');

        // Insert before action buttons if buttonar exists
        if ($mform->elementExists('buttonar')) {
            try {
                $pointsfield = $mform->removeElement($pointsfieldname, false);
                $headerfield = $mform->removeElement('benefitpointsheader', false);
                $mform->insertElementBefore($headerfield, 'buttonar');
                $mform->insertElementBefore($pointsfield, 'buttonar');
            } catch (\Exception $e) {
                // If insertion fails, elements are already added which is fine
            }
        }
    }

    /**
     * Save Benefit points when course form is submitted
     *
     * @param \core_course\hook\after_form_submission $hook
     */
    public static function course_form_submission(\core_course\hook\after_form_submission $hook): void {
        global $DB;

        $data = $hook->get_data();

        // Only process if we have a course ID
        if (empty($data->id)) {
            return;
        }

        $points = 0;
        if (isset($data->benefitpoints)) {
            $points = (int)$data->benefitpoints;
        }

        // Ensure points is non-negative
        if ($points < 0) {
            $points = 0;
        }

        // Get or create the record
        $record = $DB->get_record('local_benefitsystem_course', ['courseid' => $data->id]);
        $now = time();

        if ($record) {
            // Update existing record
            $record->points = $points;
            $record->timemodified = $now;
            $DB->update_record('local_benefitsystem_course', $record);
        } else {
            // Create new record
            $record = new \stdClass();
            $record->courseid = $data->id;
            $record->points = $points;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_benefitsystem_course', $record);
        }
    }
}
