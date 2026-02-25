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
 * Rewards page - displays list of rewards that can be exchanged for points
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
require_capability('local/benefitsystem:viewrewards', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/benefitsystem/rewards.php'));
$PAGE->set_title(get_string('rewards', 'local_benefitsystem'));
$PAGE->set_heading(get_string('rewards', 'local_benefitsystem'));
$PAGE->set_pagelayout('standard');

// Handle exchange action.
$exchange = optional_param('exchange', 0, PARAM_INT);
if ($exchange && confirm_sesskey()) {
    $reward = $DB->get_record('local_benefitsystem_rewards', ['id' => $exchange], '*', MUST_EXIST);
    
    // Get current balance.
    $userbalance = local_benefitsystem_get_user_balance($USER->id);
    
    // Check if reward is available.
    if (empty($reward->available)) {
        \core\notification::error(get_string('rewardnotavailable', 'local_benefitsystem'));
    } else {
        // Check if user has enough points.
        if ($userbalance < $reward->points) {
            $needed = $reward->points - $userbalance;
            \core\notification::error(get_string('notenoughpoints', 'local_benefitsystem') . 
                ' (' . get_string('needmorepoints', 'local_benefitsystem', $needed) . ')');
        } else {
            // Check quantity/codes availability before attempting exchange.
            $quantityavailable = true;
            $quantityreason = '';
            
            if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
                // For code-type rewards, check available codes.
                $codecount = local_benefitsystem_get_available_code_count($exchange);
                if ($codecount <= 0) {
                    $quantityavailable = false;
                    $quantityreason = get_string('quantityunavailable', 'local_benefitsystem') . 
                        ' (' . get_string('nocodesavailable', 'local_benefitsystem') . ')';
                }
            } else if (!is_null($reward->quantity) && $reward->quantity <= 0) {
                // For other rewards, check quantity.
                $quantityavailable = false;
                $quantityreason = get_string('quantityunavailable', 'local_benefitsystem');
            }
            
            if (!$quantityavailable) {
                \core\notification::error($quantityreason);
            } else {
                $exchangeid = local_benefitsystem_exchange_reward($USER->id, $exchange);
                if ($exchangeid) {
                    \core\notification::success(get_string('rewardexchanged', 'local_benefitsystem', 
                        (object)['name' => format_string($reward->name), 'points' => $reward->points]));
                    redirect(new moodle_url('/local/benefitsystem/rewards.php'));
                } else {
                    // Provide more detailed error message.
                    $errormsg = get_string('exchangefailed', 'local_benefitsystem');
                    // Try to determine why it failed.
                    if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
                        $codecount = local_benefitsystem_get_available_code_count($exchange);
                        if ($codecount <= 0) {
                            $errormsg .= ' - ' . get_string('nocodesavailable', 'local_benefitsystem');
                        }
                    } else if (!is_null($reward->quantity) && $reward->quantity <= 0) {
                        $errormsg .= ' - ' . get_string('quantityunavailable', 'local_benefitsystem');
                    }
                    \core\notification::error($errormsg);
                }
            }
        }
    }
}

// Get user's current balance.
$userbalance = local_benefitsystem_get_user_balance($USER->id);

// Get all available rewards (or all if admin).
$isadmin = has_capability('local/benefitsystem:managerewards', $context);
if ($isadmin) {
    $rewards = $DB->get_records('local_benefitsystem_rewards', null, 'points ASC');
} else {
    $rewards = $DB->get_records('local_benefitsystem_rewards', ['available' => 1], 'points ASC');
}

echo $OUTPUT->header();

// Add "Add Reward" button for admins.
if (has_capability('local/benefitsystem:managerewards', $context)) {
    $addurl = new moodle_url('/local/benefitsystem/manage_reward.php');
    echo html_writer::start_div('mb-3');
    echo html_writer::link($addurl, get_string('addreward', 'local_benefitsystem'), 
        ['class' => 'btn btn-primary']);
    echo html_writer::end_div();
}

// Display user's current balance.
echo html_writer::start_div('user-balance-container', ['style' => 'margin-bottom: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 5px;']);
echo html_writer::tag('h3', get_string('yourbalance', 'local_benefitsystem'), ['style' => 'margin-top: 0;']);
echo html_writer::tag('div', number_format($userbalance) . ' ' . get_string('points', 'local_benefitsystem'), 
    ['style' => 'font-size: 1.5em; font-weight: bold; color: #0066cc;']);
echo html_writer::end_div();

// Display rewards list.
$isadmin = has_capability('local/benefitsystem:managerewards', $context);

if (empty($rewards)) {
    echo html_writer::div(get_string('norewardsavailable', 'local_benefitsystem'), 'alert alert-info');
} else {
    echo html_writer::tag('h3', get_string('availablerewards', 'local_benefitsystem'), ['style' => 'margin-bottom: 20px;']);
    echo html_writer::start_tag('div', ['class' => 'rewards-grid', 'style' => 
        'display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;']);
    
    foreach ($rewards as $reward) {
        // For non-admins, only show available rewards.
        if (!$isadmin && empty($reward->available)) {
            continue;
        }
        
        $canafford = $userbalance >= $reward->points;
        $cardclass = $canafford ? 'reward-card' : 'reward-card disabled';
        $cardstyle = 'border: 1px solid #ddd; border-radius: 5px; padding: 15px; flex: 1 1 300px; min-width: 300px; max-width: 400px; display: flex; flex-direction: column;';
        if (!$canafford && !$isadmin) {
            $cardstyle .= ' opacity: 0.6;';
        }
        if (!$reward->available && $isadmin) {
            $cardstyle .= ' border-left: 4px solid #ffc107;';
        }
        
        echo html_writer::start_tag('div', ['class' => $cardclass, 'style' => $cardstyle]);
        
        // Admin actions.
        if ($isadmin) {
            echo html_writer::start_div('text-end mb-2');
            $editurl = new moodle_url('/local/benefitsystem/manage_reward.php', ['id' => $reward->id]);
            echo html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-secondary me-2']);
            
            // Add "Manage Codes" button for code-type rewards.
            if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
                $codesurl = new moodle_url('/local/benefitsystem/manage_codes.php', ['rewardid' => $reward->id]);
                echo html_writer::link($codesurl, get_string('managecodes', 'local_benefitsystem'), 
                    ['class' => 'btn btn-sm btn-info me-2']);
            }
            
            $deleteurl = new moodle_url('/local/benefitsystem/manage_reward.php', 
                ['delete' => $reward->id, 'sesskey' => sesskey()]);
            echo html_writer::link($deleteurl, get_string('delete'), 
                ['class' => 'btn btn-sm btn-danger', 'onclick' => 'return confirm("' . 
                get_string('confirmdeletereward', 'local_benefitsystem') . '");']);
            if (!$reward->available) {
                echo html_writer::tag('span', get_string('unavailable', 'local_benefitsystem'), 
                    ['class' => 'badge bg-warning ms-2']);
            }
            echo html_writer::end_div();
        }
        
        // Reward image.
        if (!empty($reward->image)) {
            $fileinfo = json_decode($reward->image, true);
            if ($fileinfo && isset($fileinfo['filearea'])) {
                $fs = get_file_storage();
                $file = $fs->get_file(
                    $fileinfo['contextid'],
                    $fileinfo['component'],
                    $fileinfo['filearea'],
                    $fileinfo['itemid'],
                    $fileinfo['filepath'],
                    $fileinfo['filename']
                );
                if ($file) {
                    // Generate the pluginfile URL.
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
                } else {
                    // Try alternative method: get file by area if direct retrieval fails.
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
                        echo html_writer::start_div('reward-image mb-3');
                        echo html_writer::img($imageurl->out(), format_string($reward->name), 
                            ['style' => 'max-width: 200px; max-height: 200px; border-radius: 5px;']);
                        echo html_writer::end_div();
                    }
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
        
        // How to redeem (if available).
        if (!empty($reward->howtoredeem)) {
            echo html_writer::start_div('how-to-redeem mb-2', ['style' => 'padding: 10px; background-color: #e7f3ff; border-radius: 5px;']);
            echo html_writer::tag('strong', get_string('howtoredeem', 'local_benefitsystem') . ': ');
            echo html_writer::tag('div', format_text($reward->howtoredeem, FORMAT_HTML), 
                ['style' => 'margin-top: 5px;']);
            echo html_writer::end_div();
        }
        
        // Points required.
        echo html_writer::start_tag('div', ['class' => 'reward-points', 'style' => 
            'margin-top: auto; padding-top: 10px; border-top: 1px solid #eee;']);
        echo html_writer::tag('strong', get_string('pointsrequired', 'local_benefitsystem') . ': ');
        echo html_writer::tag('span', number_format($reward->points), 
            ['style' => 'color: #0066cc; font-size: 1.2em; font-weight: bold;']);
        
        // Show if user can afford it.
        if ($canafford) {
            echo html_writer::tag('span', ' (' . get_string('youcanafford', 'local_benefitsystem') . ')', 
                ['style' => 'color: green; margin-left: 10px;']);
        } else {
            $needed = $reward->points - $userbalance;
            echo html_writer::tag('span', ' (' . get_string('needmorepoints', 'local_benefitsystem', $needed) . ')', 
                ['style' => 'color: #cc0000; margin-left: 10px;']);
        }
        
        // Display quantity if limited.
        echo html_writer::start_div('mt-2');
        // For code-type rewards, quantity = number of available codes.
        if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
            $codecount = local_benefitsystem_get_available_code_count($reward->id);
            $quantitytext = get_string('codesavailable', 'local_benefitsystem', $codecount);
            $quantityclass = $codecount > 0 ? 'text-success' : 'text-danger';
            echo html_writer::tag('div', $quantitytext, 
                ['style' => 'font-size: 0.9em;', 'class' => $quantityclass]);
        } else if (!is_null($reward->quantity)) {
            $quantitytext = get_string('quantityavailable', 'local_benefitsystem', $reward->quantity);
            $quantityclass = $reward->quantity > 0 ? 'text-success' : 'text-danger';
            echo html_writer::tag('div', $quantitytext, 
                ['style' => 'font-size: 0.9em;', 'class' => $quantityclass]);
        } else {
            echo html_writer::tag('div', get_string('quantityinfinite', 'local_benefitsystem'), 
                ['style' => 'font-size: 0.9em; color: #28a745;']);
        }
        echo html_writer::end_div();
        
        // Add exchange button for all users (always visible).
        // Check if quantity is available.
        $quantityavailable = true;
        if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
            // For code-type rewards, check available codes.
            $codecount = local_benefitsystem_get_available_code_count($reward->id);
            if ($codecount <= 0) {
                $quantityavailable = false;
            }
        } else if (!is_null($reward->quantity) && $reward->quantity <= 0) {
            // For other rewards, check quantity.
            $quantityavailable = false;
        }
        
        // Determine if exchange is possible.
        $canexchange = $reward->available && $canafford && $quantityavailable;
        
        echo html_writer::start_div('mt-3');
        $exchangeurl = new moodle_url('/local/benefitsystem/rewards.php', 
            ['exchange' => $reward->id, 'sesskey' => sesskey()]);
        
        // Allow exchange for all users (including admins) - button always enabled if conditions are met.
        if ($canexchange) {
            // User can exchange - enable button.
            echo html_writer::link($exchangeurl, get_string('exchangepoints', 'local_benefitsystem'), 
                ['class' => 'btn btn-success', 'style' => 'width: 100%;',
                 'onclick' => 'return confirm("' . get_string('confirmexchange', 'local_benefitsystem', 
                 (object)['name' => format_string($reward->name), 'points' => $reward->points]) . '");']);
        } else {
            // Cannot exchange - show disabled button with reason.
            $disabledreason = '';
            if (!$reward->available) {
                $disabledreason = get_string('rewardnotavailable', 'local_benefitsystem');
            } else if (!$canafford) {
                $needed = $reward->points - $userbalance;
                $disabledreason = get_string('notenoughpoints', 'local_benefitsystem') . 
                    ' (' . get_string('needmorepoints', 'local_benefitsystem', $needed) . ')';
            } else if (!$quantityavailable) {
                if ($reward->type === 'digital' && $reward->digitalsubtype === 'code') {
                    $disabledreason = get_string('nocodesavailable', 'local_benefitsystem');
                } else {
                    $disabledreason = get_string('quantityunavailable', 'local_benefitsystem');
                }
            }
            echo html_writer::tag('button', get_string('exchangepoints', 'local_benefitsystem'), 
                ['class' => 'btn btn-secondary', 'style' => 'width: 100%;', 'disabled' => true,
                 'title' => $disabledreason]);
        }
        echo html_writer::end_div();
        
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
    }
    
    echo html_writer::end_tag('div');
}

echo $OUTPUT->footer();
