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
 * Your Rewards page - displays list of rewards the user has exchanged
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

require_login();

global $USER, $DB;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/benefitsystem/your_rewards.php'));
$PAGE->set_title(get_string('yourrewards', 'local_benefitsystem'));
$PAGE->set_heading(get_string('yourrewards', 'local_benefitsystem'));
$PAGE->set_pagelayout('standard');

// Handle mark as redeemed action.
$redeem = optional_param('redeem', 0, PARAM_INT);
if ($redeem && confirm_sesskey()) {
    if (local_benefitsystem_mark_as_redeemed($redeem, $USER->id)) {
        \core\notification::success(get_string('markedasredeemed', 'local_benefitsystem'));
        redirect(new moodle_url('/local/benefitsystem/your_rewards.php'));
    } else {
        \core\notification::error(get_string('redeemfailed', 'local_benefitsystem'));
    }
}

// Get user's exchanged rewards (not yet redeemed).
$exchanges = local_benefitsystem_get_user_exchanges($USER->id);

echo $OUTPUT->header();

// Page title is already displayed by $OUTPUT->header() via $PAGE->set_heading(), so we don't need to display it again.

// Display user's current balance.
$userbalance = local_benefitsystem_get_user_balance($USER->id);
echo html_writer::start_div('user-balance-container', ['style' => 'margin-bottom: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 5px;']);
echo html_writer::tag('h3', get_string('yourbalance', 'local_benefitsystem'), ['style' => 'margin-top: 0;']);
echo html_writer::tag('div', number_format($userbalance) . ' ' . get_string('points', 'local_benefitsystem'), 
    ['style' => 'font-size: 1.5em; font-weight: bold; color: #0066cc;']);
echo html_writer::end_div();

// Link back to rewards page.
$rewardsurl = new moodle_url('/local/benefitsystem/rewards.php');
echo html_writer::start_div('mb-3');
echo html_writer::link($rewardsurl, get_string('viewrewards', 'local_benefitsystem'), 
    ['class' => 'btn btn-primary']);
echo html_writer::end_div();

// Display exchanged rewards.
if (empty($exchanges)) {
    echo html_writer::div(get_string('noexchanges', 'local_benefitsystem'), 'alert alert-info');
} else {
    echo html_writer::tag('h3', get_string('exchangedrewards', 'local_benefitsystem'), ['style' => 'margin-bottom: 20px;']);
    echo html_writer::start_tag('div', ['class' => 'rewards-grid', 'style' => 
        'display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;']);
    
    foreach ($exchanges as $exchange) {
        $reward = $exchange->reward;
        
        $cardstyle = 'border: 1px solid #ddd; border-radius: 5px; padding: 15px; flex: 1 1 300px; min-width: 300px; max-width: 400px; display: flex; flex-direction: column; background-color: #f9f9f9;';
        
        echo html_writer::start_tag('div', ['class' => 'reward-card', 'style' => $cardstyle]);
        
        // Reward image.
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
                    echo html_writer::start_div('reward-image mb-3', ['style' => 'text-align: center;']);
                    echo html_writer::img($imageurl->out(), format_string($reward->name), 
                        ['style' => 'max-width: 100%; max-height: 200px; border-radius: 5px; object-fit: contain;']);
                    echo html_writer::end_div();
                }
            }
        }
        
        // Reward name.
        echo html_writer::tag('h4', format_string($reward->name), ['style' => 'margin-top: 0;']);
        
        // Reward type badge.
        if (!empty($reward->type)) {
            $typeclass = $reward->type === 'digital' ? 'badge bg-info' : 'badge bg-warning';
            if ($reward->type === 'digital') {
                // Show digital type with subtype if available.
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
            echo html_writer::tag('span', $typestring, ['class' => $typeclass . ' mb-2']);
        }
        
        // Reward description.
        if (!empty($reward->description)) {
            echo html_writer::tag('p', format_text($reward->description, FORMAT_HTML), 
                ['class' => 'reward-description']);
        }
        
        // Display code if this is a code-type reward.
        if ($reward->type === 'digital' && $reward->digitalsubtype === 'code' && !empty($exchange->code)) {
            echo html_writer::start_div('reward-code mb-2', ['style' => 'padding: 10px; background-color: #fff3cd; border-radius: 5px; border: 2px solid #ffc107;']);
            echo html_writer::tag('strong', get_string('yourcode', 'local_benefitsystem') . ': ');
            echo html_writer::tag('code', htmlspecialchars($exchange->code), 
                ['style' => 'font-size: 1.2em; font-weight: bold; color: #856404; display: block; margin-top: 5px;']);
            echo html_writer::end_div();
        }
        
        // How to redeem (if available).
        if (!empty($reward->howtoredeem)) {
            echo html_writer::start_div('how-to-redeem mb-2', ['style' => 'padding: 10px; background-color: #e7f3ff; border-radius: 5px;']);
            echo html_writer::tag('strong', get_string('howtoredeem', 'local_benefitsystem') . ': ');
            echo html_writer::tag('div', format_text($reward->howtoredeem, FORMAT_HTML), 
                ['style' => 'margin-top: 5px;']);
            echo html_writer::end_div();
        }
        
        // Exchange details.
        echo html_writer::start_tag('div', ['class' => 'exchange-details', 'style' => 
            'margin-top: auto; padding-top: 10px; border-top: 1px solid #eee;']);
        echo html_writer::tag('div', get_string('pointsspent', 'local_benefitsystem') . ': ' . 
            html_writer::tag('strong', number_format($exchange->points)), 
            ['style' => 'margin-bottom: 5px;']);
        echo html_writer::tag('div', get_string('exchangedon', 'local_benefitsystem') . ': ' . 
            userdate($exchange->timecreated, get_string('strftimedatefullshort', 'langconfig')), 
            ['style' => 'font-size: 0.9em; color: #666;']);
        
        // Mark as redeemed button.
        echo html_writer::start_div('mt-3');
        $redeemurl = new moodle_url('/local/benefitsystem/your_rewards.php', 
            ['redeem' => $exchange->id, 'sesskey' => sesskey()]);
        echo html_writer::link($redeemurl, get_string('markasredeemed', 'local_benefitsystem'), 
            ['class' => 'btn btn-primary', 'style' => 'width: 100%;',
             'onclick' => 'return confirm("' . get_string('confirmredeem', 'local_benefitsystem') . '");']);
        echo html_writer::end_div();
        
        echo html_writer::end_tag('div');
        
        echo html_writer::end_tag('div');
    }
    
    echo html_writer::end_tag('div');
}

// Display exchange history (redeemed exchanges) as a table.
$history = local_benefitsystem_get_user_exchange_history($USER->id);
if (!empty($history)) {
    echo html_writer::tag('h3', get_string('exchangehistory', 'local_benefitsystem'), 
        ['style' => 'margin-top: 40px; margin-bottom: 20px;']);
    
    // Start table.
    echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered', 
        'style' => 'width: 100%; margin-bottom: 20px;']);
    
    // Table header.
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('reward', 'local_benefitsystem'), ['style' => 'width: 25%;']);
    echo html_writer::tag('th', get_string('type', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::tag('th', get_string('code', 'local_benefitsystem'), ['style' => 'width: 20%;']);
    echo html_writer::tag('th', get_string('points', 'local_benefitsystem'), ['style' => 'width: 10%;']);
    echo html_writer::tag('th', get_string('exchangedon', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::tag('th', get_string('redeemedon', 'local_benefitsystem'), ['style' => 'width: 15%;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Table body.
    echo html_writer::start_tag('tbody');
    
    foreach ($history as $record) {
        $reward = $record->reward;
        
        echo html_writer::start_tag('tr');
        
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
}

echo $OUTPUT->footer();
