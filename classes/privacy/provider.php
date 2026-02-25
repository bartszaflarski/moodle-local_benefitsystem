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
 * Privacy API implementation for local_benefitsystem.
 *
 * @package     local_benefitsystem
 * @copyright   2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_benefitsystem\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy provider for local_benefitsystem.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata about this plugin's user data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_benefitsystem_balance', [
            'userid' => 'privacy:metadata:balance:userid',
            'points' => 'privacy:metadata:balance:points',
            'timecreated' => 'privacy:metadata:balance:timecreated',
            'timemodified' => 'privacy:metadata:balance:timemodified',
        ], 'privacy:metadata:balance');

        $collection->add_database_table('local_benefitsystem_history', [
            'userid' => 'privacy:metadata:history:userid',
            'coursemoduleid' => 'privacy:metadata:history:coursemoduleid',
            'courseid' => 'privacy:metadata:history:courseid',
            'points' => 'privacy:metadata:history:points',
            'timecreated' => 'privacy:metadata:history:timecreated',
        ], 'privacy:metadata:history');

        $collection->add_database_table('local_benefitsystem_exchanges', [
            'userid' => 'privacy:metadata:exchanges:userid',
            'rewardid' => 'privacy:metadata:exchanges:rewardid',
            'points' => 'privacy:metadata:exchanges:points',
            'status' => 'privacy:metadata:exchanges:status',
            'timecreated' => 'privacy:metadata:exchanges:timecreated',
            'timemodified' => 'privacy:metadata:exchanges:timemodified',
        ], 'privacy:metadata:exchanges');

        $collection->add_database_table('local_benefitsystem_exchange_history', [
            'userid' => 'privacy:metadata:exchange_history:userid',
            'rewardid' => 'privacy:metadata:exchange_history:rewardid',
            'points' => 'privacy:metadata:exchange_history:points',
            'code' => 'privacy:metadata:exchange_history:code',
            'timecreated' => 'privacy:metadata:exchange_history:timecreated',
            'timeredeemed' => 'privacy:metadata:exchange_history:timeredeemed',
        ], 'privacy:metadata:exchange_history');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :userlevel
                   AND ctx.instanceid = :userid
                   AND (EXISTS (SELECT 1 FROM {local_benefitsystem_balance} b WHERE b.userid = ctx.instanceid)
                     OR EXISTS (SELECT 1 FROM {local_benefitsystem_history} h WHERE h.userid = ctx.instanceid)
                     OR EXISTS (SELECT 1 FROM {local_benefitsystem_exchanges} e WHERE e.userid = ctx.instanceid)
                     OR EXISTS (SELECT 1 FROM {local_benefitsystem_exchange_history} eh WHERE eh.userid = ctx.instanceid))";
        $params = [
            'userlevel' => CONTEXT_USER,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }

        $userid = $context->instanceid;

        $sql = "SELECT userid FROM {local_benefitsystem_balance} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $userid]);

        $sql = "SELECT userid FROM {local_benefitsystem_history} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $userid]);

        $sql = "SELECT userid FROM {local_benefitsystem_exchanges} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $userid]);

        $sql = "SELECT userid FROM {local_benefitsystem_exchange_history} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $userid]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_USER || $context->instanceid != $user->id) {
                continue;
            }

            $path = [get_string('pluginname', 'local_benefitsystem')];

            // Balance.
            $balance = $DB->get_record('local_benefitsystem_balance', ['userid' => $user->id]);
            if ($balance) {
                $data = (object) [
                    'points' => $balance->points,
                    'timecreated' => transform::datetime($balance->timecreated),
                    'timemodified' => transform::datetime($balance->timemodified),
                ];
                writer::with_context($context)->export_data(
                    array_merge($path, [get_string('userbalance', 'local_benefitsystem')]),
                    $data
                );
            }

            // Points award history.
            $history = $DB->get_records('local_benefitsystem_history', ['userid' => $user->id], 'timecreated DESC');
            if ($history) {
                $items = [];
                foreach ($history as $record) {
                    $items[] = (object) [
                        'coursemoduleid' => $record->coursemoduleid,
                        'courseid' => $record->courseid,
                        'points' => $record->points,
                        'timecreated' => transform::datetime($record->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    array_merge($path, [get_string('pointsawarded', 'local_benefitsystem')]),
                    (object) ['items' => $items]
                );
            }

            // Active exchanges.
            $exchanges = $DB->get_records('local_benefitsystem_exchanges', ['userid' => $user->id], 'timecreated DESC');
            if ($exchanges) {
                $items = [];
                foreach ($exchanges as $record) {
                    $items[] = (object) [
                        'rewardid' => $record->rewardid,
                        'points' => $record->points,
                        'status' => $record->status,
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified),
                    ];
                }
                writer::with_context($context)->export_data(
                    array_merge($path, [get_string('exchangedrewards', 'local_benefitsystem')]),
                    (object) ['items' => $items]
                );
            }

            // Exchange history (redeemed).
            $exhistory = $DB->get_records('local_benefitsystem_exchange_history', ['userid' => $user->id], 'timeredeemed DESC');
            if ($exhistory) {
                $items = [];
                foreach ($exhistory as $record) {
                    $items[] = (object) [
                        'rewardid' => $record->rewardid,
                        'points' => $record->points,
                        'code' => $record->code,
                        'timecreated' => transform::datetime($record->timecreated),
                        'timeredeemed' => transform::datetime($record->timeredeemed),
                    ];
                }
                writer::with_context($context)->export_data(
                    array_merge($path, [get_string('exchangehistory', 'local_benefitsystem')]),
                    (object) ['items' => $items]
                );
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $user->id) {
                self::delete_user_data($user->id);
                break;
            }
        }
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel == CONTEXT_USER) {
            self::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_data($userid);
        }
    }

    /**
     * Delete all benefit system data for a user.
     *
     * @param int $userid The user ID.
     */
    protected static function delete_user_data(int $userid): void {
        global $DB;

        $DB->delete_records('local_benefitsystem_balance', ['userid' => $userid]);
        $DB->delete_records('local_benefitsystem_history', ['userid' => $userid]);
        $DB->delete_records('local_benefitsystem_exchanges', ['userid' => $userid]);
        $DB->delete_records('local_benefitsystem_exchange_history', ['userid' => $userid]);
    }
}
