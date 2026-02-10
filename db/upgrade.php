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
 * Upgrade script for local_benefitsystem
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function
 *
 * @param int $oldversion The old version
 * @return bool
 */
function xmldb_local_benefitsystem_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025020801) {
        // Define table local_benefitsystem_rewards to be created.
        $table = new xmldb_table('local_benefitsystem_rewards');

        // Adding fields to table local_benefitsystem_rewards.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('available', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_benefitsystem_rewards.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_benefitsystem_rewards.
        $table->add_index('available', XMLDB_INDEX_NOTUNIQUE, ['available']);

        // Conditionally launch create table for local_benefitsystem_rewards.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020801, 'local', 'benefitsystem');
    }

    if ($oldversion < 2025020802) {
        // Define table local_benefitsystem_course to be created.
        $table = new xmldb_table('local_benefitsystem_course');

        // Adding fields to table local_benefitsystem_course.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_benefitsystem_course.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_UNIQUE, ['courseid']);

        // Adding indexes to table local_benefitsystem_course.
        $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);

        // Conditionally launch create table for local_benefitsystem_course.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add courseid field to history table.
        $historytable = new xmldb_table('local_benefitsystem_history');
        $courseidfield = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'coursemoduleid');
        
        if (!$dbman->field_exists($historytable, $courseidfield)) {
            $dbman->add_field($historytable, $courseidfield);
        }

        // Add index for courseid.
        $courseidindex = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($historytable, $courseidindex)) {
            $dbman->add_index($historytable, $courseidindex);
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020802, 'local', 'benefitsystem');
    }

    if ($oldversion < 2025020803) {
        // Add image and type fields to rewards table.
        $rewardstable = new xmldb_table('local_benefitsystem_rewards');
        
        // Add image field.
        $imagefield = new xmldb_field('image', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description');
        if (!$dbman->field_exists($rewardstable, $imagefield)) {
            $dbman->add_field($rewardstable, $imagefield);
        }
        
        // Add type field.
        $typefield = new xmldb_field('type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'digital', 'image');
        if (!$dbman->field_exists($rewardstable, $typefield)) {
            $dbman->add_field($rewardstable, $typefield);
        }
        
        // Add index for type.
        $typeindex = new xmldb_index('type', XMLDB_INDEX_NOTUNIQUE, ['type']);
        if (!$dbman->index_exists($rewardstable, $typeindex)) {
            $dbman->add_index($rewardstable, $typeindex);
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020803, 'local', 'benefitsystem');
    }

    if ($oldversion < 2025020804) {
        // Rename tables from local_pointstorewards_* to local_benefitsystem_*
        $tables = [
            'local_pointstorewards_activity' => 'local_benefitsystem_activity',
            'local_pointstorewards_balance' => 'local_benefitsystem_balance',
            'local_pointstorewards_history' => 'local_benefitsystem_history',
            'local_pointstorewards_rewards' => 'local_benefitsystem_rewards',
            'local_pointstorewards_course' => 'local_benefitsystem_course',
        ];

        foreach ($tables as $oldname => $newname) {
            $oldtable = new xmldb_table($oldname);
            $newtable = new xmldb_table($newname);
            
            // Check if old table exists and new table doesn't exist.
            if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
                $dbman->rename_table($oldtable, $newname);
            }
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020804, 'local', 'benefitsystem');
    }

    if ($oldversion < 2025020806) {
        // Define table local_benefitsystem_exchanges to be created.
        $table = new xmldb_table('local_benefitsystem_exchanges');

        // Adding fields to table local_benefitsystem_exchanges.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rewardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_benefitsystem_exchanges.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_benefitsystem_exchanges.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('rewardid', XMLDB_INDEX_NOTUNIQUE, ['rewardid']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        // Conditionally launch create table for local_benefitsystem_exchanges.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020806, 'local', 'benefitsystem');
    }

    if ($oldversion < 2025020807) {
        // Add digitalsubtype field to rewards table.
        $rewardstable = new xmldb_table('local_benefitsystem_rewards');
        
        // Add digitalsubtype field.
        $subtypefield = new xmldb_field('digitalsubtype', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'type');
        if (!$dbman->field_exists($rewardstable, $subtypefield)) {
            $dbman->add_field($rewardstable, $subtypefield);
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020807, 'local', 'benefitsystem');
    }

    if ($oldversion < 2025020808) {
        // Add howtoredeem field to rewards table.
        $rewardstable = new xmldb_table('local_benefitsystem_rewards');
        
        // Add howtoredeem field.
        $howtoredeemfield = new xmldb_field('howtoredeem', XMLDB_TYPE_TEXT, null, null, null, null, null, 'digitalsubtype');
        if (!$dbman->field_exists($rewardstable, $howtoredeemfield)) {
            $dbman->add_field($rewardstable, $howtoredeemfield);
        }

        // Define table local_benefitsystem_codes to be created.
        $table = new xmldb_table('local_benefitsystem_codes');

        // Adding fields to table local_benefitsystem_codes.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('rewardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('used', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('exchangeid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeused', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Adding keys to table local_benefitsystem_codes.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_benefitsystem_codes.
        $table->add_index('rewardid', XMLDB_INDEX_NOTUNIQUE, ['rewardid']);
        $table->add_index('code', XMLDB_INDEX_NOTUNIQUE, ['code']);
        $table->add_index('used', XMLDB_INDEX_NOTUNIQUE, ['used']);
        $table->add_index('rewardid_used', XMLDB_INDEX_NOTUNIQUE, ['rewardid', 'used']);

        // Conditionally launch create table for local_benefitsystem_codes.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020808, 'local', 'benefitsystem');
    }

    if ($oldversion < 2025020809) {
        // Add quantity field to rewards table.
        $rewardstable = new xmldb_table('local_benefitsystem_rewards');
        
        // Add quantity field.
        $quantityfield = new xmldb_field('quantity', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'points');
        if (!$dbman->field_exists($rewardstable, $quantityfield)) {
            $dbman->add_field($rewardstable, $quantityfield);
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020809, 'local', 'benefitsystem');
    }

    if ($oldversion < 2025020810) {
        // Define table local_benefitsystem_exchange_history to be created.
        $table = new xmldb_table('local_benefitsystem_exchange_history');

        // Adding fields to table local_benefitsystem_exchange_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('rewardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('code', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timeredeemed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_benefitsystem_exchange_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_benefitsystem_exchange_history.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('rewardid', XMLDB_INDEX_NOTUNIQUE, ['rewardid']);
        $table->add_index('timeredeemed', XMLDB_INDEX_NOTUNIQUE, ['timeredeemed']);

        // Conditionally launch create table for local_benefitsystem_exchange_history.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Benefitsystem savepoint reached.
        upgrade_plugin_savepoint(true, 2025020810, 'local', 'benefitsystem');
    }

    return true;
}
