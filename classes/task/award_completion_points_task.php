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
 * Adhoc task to award benefit points for activity or course completion.
 * Runs asynchronously so event observers stay lightweight.
 *
 * @package     local_benefitsystem
 * @copyright   2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_benefitsystem\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Adhoc task for awarding completion points.
 */
class award_completion_points_task extends \core\task\adhoc_task {

    /**
     * Execute the task: award points and send notification.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $userid = (int) ($data->userid ?? 0);
        $coursemoduleid = (int) ($data->coursemoduleid ?? 0);
        $courseid = (int) ($data->courseid ?? 0);

        if ($userid <= 0) {
            return;
        }

        if ($coursemoduleid > 0) {
            local_benefitsystem_award_activity_completion_points($userid, $coursemoduleid);
            return;
        }

        if ($courseid > 0) {
            local_benefitsystem_award_course_completion_points($userid, $courseid);
        }
    }
}
