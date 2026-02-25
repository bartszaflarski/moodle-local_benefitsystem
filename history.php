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
 * Purchase History page - displays all purchases/exchanges from all users (admin only)
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

require_login();

global $USER, $DB;

$context = context_system::instance();
require_capability('local/benefitsystem:managerewards', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/benefitsystem/history.php'));
$PAGE->set_title(get_string('purchasehistory', 'local_benefitsystem'));
$PAGE->set_heading(get_string('purchasehistory', 'local_benefitsystem'));
$PAGE->set_pagelayout('standard');

// Get all exchanges (active and history).
$exchanges = local_benefitsystem_get_all_exchanges();
$history = local_benefitsystem_get_all_exchange_history();

echo $OUTPUT->header();

// Display page title.
echo html_writer::tag('h2', get_string('purchasehistory', 'local_benefitsystem'));

// Link back to rewards page.
$rewardsurl = new moodle_url('/local/benefitsystem/rewards.php');
echo html_writer::start_div('mb-3');
echo html_writer::link($rewardsurl, get_string('backtorewards', 'local_benefitsystem'), 
    ['class' => 'btn btn-secondary']);
echo html_writer::end_div();

// Display active exchanges.
if (!empty($exchanges)) {
    echo html_writer::tag('h3', get_string('activeexchanges', 'local_benefitsystem'), 
        ['style' => 'margin-top: 20px; margin-bottom: 15px;']);
    
    // Start table.
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered', 
        'style' => 'width: 100%; margin-bottom: 30px;']);
    
    // Table header.
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('user', 'local_benefitsystem'), ['style' => 'width: 20%;']);
    echo html_writer::tag('th', get_string('reward', 'local_benefitsystem'), ['style' => 'width: 20%;']);
    echo html_writer::tag('th', get_string('type', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::tag('th', get_string('code', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::tag('th', get_string('points', 'local_benefitsystem'), ['style' => 'width: 10%;']);
    echo html_writer::tag('th', get_string('exchangedon', 'local_benefitsystem'), ['style' => 'width: 20%;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Table body.
    echo html_writer::start_tag('tbody');
    
    foreach ($exchanges as $exchange) {
        $reward = $exchange->reward;
        $user = $exchange->user;
        
        echo html_writer::start_tag('tr');
        
        // User name.
        echo html_writer::tag('td', fullname($user) . '<br><small>' . $user->email . '</small>');
        
        // Reward name.
        $rewardname = format_string($reward->name);
        // Add image thumbnail if available.
        if (!empty($reward->image)) {
            $fileinfo = json_decode($reward->image, true);
            if ($fileinfo && isset($fileinfo['filearea'])) {
                $fs = get_file_storage();
                $files = $fs->get_area_files(
                    $fileinfo['contextid'],
                    $fileinfo['component'],
                    $fileinfo['filearea'],
                    $fileinfo['itemid'],
                    'id DESC',
                    false
                );
                if ($file = reset($files)) {
                    $imageurl = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    );
                    $rewardname = html_writer::img($imageurl->out(), '', 
                        ['style' => 'max-width: 50px; max-height: 50px; margin-right: 10px; vertical-align: middle;']) . 
                        html_writer::tag('span', format_string($reward->name), ['style' => 'vertical-align: middle;']);
                }
            }
        }
        echo html_writer::tag('td', $rewardname);
        
        // Reward type.
        $typestring = '';
        if ($reward->type === 'digital') {
            $typestring = get_string('typedigital', 'local_benefitsystem');
            if (!empty($reward->digitalsubtype)) {
                if ($reward->digitalsubtype === 'file') {
                    $typestring .= ' - ' . get_string('subtypefile', 'local_benefitsystem');
                } else if ($reward->digitalsubtype === 'code') {
                    $typestring .= ' - ' . get_string('subtypecode', 'local_benefitsystem');
                }
            }
        } else {
            $typestring = get_string('typephysical', 'local_benefitsystem');
        }
        echo html_writer::tag('td', $typestring);
        
        // Code (if code-type reward).
        if ($reward->type === 'digital' && $reward->digitalsubtype === 'code' && !empty($exchange->code)) {
            echo html_writer::tag('td', html_writer::tag('code', htmlspecialchars($exchange->code), 
                ['style' => 'font-weight: bold; color: #856404;']));
        } else {
            echo html_writer::tag('td', '-', ['style' => 'color: #999;']);
        }
        
        // Points spent.
        echo html_writer::tag('td', html_writer::tag('strong', number_format($exchange->points)));
        
        // Exchange date.
        echo html_writer::tag('td', userdate($exchange->timecreated, get_string('strftimedatefullshort', 'langconfig')));
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
} else {
    echo html_writer::div(get_string('noactiveexchanges', 'local_benefitsystem'), 'alert alert-info');
}

// Display exchange history (redeemed exchanges).
if (!empty($history)) {
    echo html_writer::tag('h3', get_string('exchangehistory', 'local_benefitsystem'), 
        ['style' => 'margin-top: 40px; margin-bottom: 15px;']);
    
    // Start table.
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered', 
        'style' => 'width: 100%; margin-bottom: 20px;']);
    
    // Table header.
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('user', 'local_benefitsystem'), ['style' => 'width: 20%;']);
    echo html_writer::tag('th', get_string('reward', 'local_benefitsystem'), ['style' => 'width: 20%;']);
    echo html_writer::tag('th', get_string('type', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::tag('th', get_string('code', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::tag('th', get_string('points', 'local_benefitsystem'), ['style' => 'width: 10%;']);
    echo html_writer::tag('th', get_string('exchangedon', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::tag('th', get_string('redeemedon', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Table body.
    echo html_writer::start_tag('tbody');
    
    foreach ($history as $record) {
        $reward = $record->reward;
        $user = $record->user;
        
        echo html_writer::start_tag('tr');
        
        // User name.
        echo html_writer::tag('td', fullname($user) . '<br><small>' . $user->email . '</small>');
        
        // Reward name.
        $rewardname = format_string($reward->name);
        // Add image thumbnail if available.
        if (!empty($reward->image)) {
            $fileinfo = json_decode($reward->image, true);
            if ($fileinfo && isset($fileinfo['filearea'])) {
                $fs = get_file_storage();
                $files = $fs->get_area_files(
                    $fileinfo['contextid'],
                    $fileinfo['component'],
                    $fileinfo['filearea'],
                    $fileinfo['itemid'],
                    'id DESC',
                    false
                );
                if ($file = reset($files)) {
                    $imageurl = moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename()
                    );
                    $rewardname = html_writer::img($imageurl->out(), '', 
                        ['style' => 'max-width: 50px; max-height: 50px; margin-right: 10px; vertical-align: middle;']) . 
                        html_writer::tag('span', format_string($reward->name), ['style' => 'vertical-align: middle;']);
                }
            }
        }
        echo html_writer::tag('td', $rewardname);
        
        // Reward type.
        $typestring = '';
        if ($reward->type === 'digital') {
            $typestring = get_string('typedigital', 'local_benefitsystem');
            if (!empty($reward->digitalsubtype)) {
                if ($reward->digitalsubtype === 'file') {
                    $typestring .= ' - ' . get_string('subtypefile', 'local_benefitsystem');
                } else if ($reward->digitalsubtype === 'code') {
                    $typestring .= ' - ' . get_string('subtypecode', 'local_benefitsystem');
                }
            }
        } else {
            $typestring = get_string('typephysical', 'local_benefitsystem');
        }
        echo html_writer::tag('td', $typestring);
        
        // Code (if code-type reward).
        if ($reward->type === 'digital' && $reward->digitalsubtype === 'code' && !empty($record->code)) {
            echo html_writer::tag('td', html_writer::tag('code', htmlspecialchars($record->code), 
                ['style' => 'font-weight: bold; color: #856404;']));
        } else {
            echo html_writer::tag('td', '-', ['style' => 'color: #999;']);
        }
        
        // Points spent.
        echo html_writer::tag('td', html_writer::tag('strong', number_format($record->points)));
        
        // Exchange date.
        echo html_writer::tag('td', userdate($record->timecreated, get_string('strftimedatefullshort', 'langconfig')));
        
        // Redeemed date.
        echo html_writer::tag('td', html_writer::tag('span', 
            userdate($record->timeredeemed, get_string('strftimedatefullshort', 'langconfig')), 
            ['style' => 'color: #28a745; font-weight: bold;']));
        
        echo html_writer::end_tag('tr');
    }
    
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
} else {
    echo html_writer::div(get_string('nohistory', 'local_benefitsystem'), 'alert alert-info');
}

echo $OUTPUT->footer();
