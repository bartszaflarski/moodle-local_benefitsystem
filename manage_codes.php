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
 * Manage codes page - manage codes for a code-type reward
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

// Check if user has permission to manage rewards.
require_capability('local/benefitsystem:managerewards', $context);

$rewardid = required_param('rewardid', PARAM_INT);
$reward = $DB->get_record('local_benefitsystem_rewards', ['id' => $rewardid], '*', MUST_EXIST);

// Verify this is a code-type reward.
if ($reward->type !== 'digital' || $reward->digitalsubtype !== 'code') {
    throw new moodle_exception('invalidrewardtype', 'local_benefitsystem');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/benefitsystem/manage_codes.php', ['rewardid' => $rewardid]));
$PAGE->set_title(get_string('managecodes', 'local_benefitsystem'));
$PAGE->set_heading(get_string('managecodes', 'local_benefitsystem') . ' - ' . format_string($reward->name));
$PAGE->set_pagelayout('standard');

// Handle delete code action.
$deletecode = optional_param('deletecode', 0, PARAM_INT);
if ($deletecode && confirm_sesskey()) {
    $code = $DB->get_record('local_benefitsystem_codes', [
        'id' => $deletecode,
        'rewardid' => $rewardid,
        'used' => 0  // Only allow deletion of unused codes
    ]);
    
    if ($code) {
        $DB->delete_records('local_benefitsystem_codes', ['id' => $deletecode]);
        \core\notification::success(get_string('codedeleted', 'local_benefitsystem'));
        redirect(new moodle_url('/local/benefitsystem/manage_codes.php', ['rewardid' => $rewardid]));
    } else {
        \core\notification::error(get_string('codenotfound', 'local_benefitsystem'));
    }
}

// Handle add code action.
$addcode = optional_param('addcode', '', PARAM_TEXT);
if ($addcode && confirm_sesskey()) {
    $addcode = trim($addcode);
    if (!empty($addcode)) {
        // Check if code already exists for this reward.
        $existing = $DB->get_record('local_benefitsystem_codes', [
            'rewardid' => $rewardid,
            'code' => $addcode
        ]);
        
        if ($existing) {
            \core\notification::error(get_string('codealreadyexists', 'local_benefitsystem'));
        } else {
            $newcode = new stdClass();
            $newcode->rewardid = $rewardid;
            $newcode->code = $addcode;
            $newcode->used = 0;
            $newcode->exchangeid = 0;
            $newcode->timecreated = time();
            $newcode->timeused = 0;
            $DB->insert_record('local_benefitsystem_codes', $newcode);
            
            // If reward was marked as unavailable (no codes), mark it as available again.
            if (empty($reward->available)) {
                $reward->available = 1;
                $reward->timemodified = time();
                $DB->update_record('local_benefitsystem_rewards', $reward);
            }
            
            \core\notification::success(get_string('codeadded', 'local_benefitsystem'));
            redirect(new moodle_url('/local/benefitsystem/manage_codes.php', ['rewardid' => $rewardid]));
        }
    }
}

// Handle edit code action.
$editcode = optional_param('editcode', 0, PARAM_INT);
$newcodevalue = optional_param('newcode', '', PARAM_TEXT);
if ($editcode && $newcodevalue && confirm_sesskey()) {
    $code = $DB->get_record('local_benefitsystem_codes', [
        'id' => $editcode,
        'rewardid' => $rewardid,
        'used' => 0  // Only allow editing of unused codes
    ]);
    
    if ($code) {
        // Check if new code value already exists for this reward.
        $existing = $DB->get_record('local_benefitsystem_codes', [
            'rewardid' => $rewardid,
            'code' => trim($newcodevalue),
            'id' => $editcode
        ], 'id', IGNORE_MISSING);
        
        if ($existing && $existing->id != $editcode) {
            \core\notification::error(get_string('codealreadyexists', 'local_benefitsystem'));
        } else {
            $code->code = trim($newcodevalue);
            $code->timemodified = time();
            $DB->update_record('local_benefitsystem_codes', $code);
            \core\notification::success(get_string('codeupdated', 'local_benefitsystem'));
            redirect(new moodle_url('/local/benefitsystem/manage_codes.php', ['rewardid' => $rewardid]));
        }
    } else {
        \core\notification::error(get_string('codenotfound', 'local_benefitsystem'));
    }
}

echo $OUTPUT->header();

// Page title is already displayed by $OUTPUT->header() via $PAGE->set_heading(), so we don't need to display it again.

// Link back to rewards page.
$rewardsurl = new moodle_url('/local/benefitsystem/rewards.php');
$editrewardurl = new moodle_url('/local/benefitsystem/manage_reward.php', ['id' => $rewardid]);
echo html_writer::start_div('mb-3');
echo html_writer::link($rewardsurl, get_string('backtorewards', 'local_benefitsystem'), 
    ['class' => 'btn btn-secondary me-2']);
echo html_writer::link($editrewardurl, get_string('editreward', 'local_benefitsystem'), 
    ['class' => 'btn btn-primary me-2']);

// Add Code button and form.
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(),
    'style' => 'display: inline-block;'
]);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'addcode',
    'placeholder' => get_string('entercode', 'local_benefitsystem'),
    'class' => 'form-control',
    'style' => 'display: inline-block; width: 200px; margin-right: 5px;',
    'required' => true
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rewardid', 'value' => $rewardid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('button', get_string('addcode', 'local_benefitsystem'), 
    ['type' => 'submit', 'class' => 'btn btn-success']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

// Get all codes for this reward.
$allcodes = $DB->get_records('local_benefitsystem_codes', 
    ['rewardid' => $rewardid], 
    'used ASC, code ASC'
);

if (empty($allcodes)) {
    echo html_writer::div(get_string('nocodesfound', 'local_benefitsystem'), 'alert alert-info');
} else {
    // Separate used and unused codes.
    $usedcodes = [];
    $unusedcodes = [];
    
    foreach ($allcodes as $code) {
        if ($code->used) {
            $usedcodes[] = $code;
        } else {
            $unusedcodes[] = $code;
        }
    }
    
    // Display unused codes table with edit/remove buttons.
    if (!empty($unusedcodes)) {
        echo html_writer::tag('h3', get_string('availablecodes', 'local_benefitsystem'), 
            ['style' => 'margin-top: 20px; margin-bottom: 15px;']);
        
        echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered', 
            'style' => 'width: 100%; margin-bottom: 30px;']);
        
        // Table header.
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('code', 'local_benefitsystem'), ['style' => 'width: 40%;']);
        echo html_writer::tag('th', get_string('created', 'local_benefitsystem'), ['style' => 'width: 30%;']);
        echo html_writer::tag('th', get_string('actions', 'local_benefitsystem'), ['style' => 'width: 30%;']);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        // Table body.
        echo html_writer::start_tag('tbody');
        
        foreach ($unusedcodes as $code) {
            echo html_writer::start_tag('tr', ['id' => 'code-row-' . $code->id]);
            
            // Code value (editable inline).
            echo html_writer::start_tag('td');
            echo html_writer::start_tag('span', ['id' => 'code-display-' . $code->id, 'class' => 'code-display']);
            echo html_writer::tag('code', htmlspecialchars($code->code), 
                ['style' => 'font-weight: bold; font-size: 1.1em;']);
            echo html_writer::end_tag('span');
            echo html_writer::start_tag('span', ['id' => 'code-edit-' . $code->id, 'class' => 'code-edit', 'style' => 'display: none;']);
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $PAGE->url->out(),
                'style' => 'display: inline;'
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'newcode',
                'value' => htmlspecialchars($code->code),
                'class' => 'form-control',
                'style' => 'display: inline-block; width: 200px; margin-right: 5px;',
                'required' => true
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'editcode', 'value' => $code->id]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rewardid', 'value' => $rewardid]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::tag('button', get_string('save', 'local_benefitsystem'), 
                ['type' => 'submit', 'class' => 'btn btn-sm btn-success']);
            echo html_writer::tag('button', get_string('cancel'), 
                ['type' => 'button', 'class' => 'btn btn-sm btn-secondary', 
                 'onclick' => 'document.getElementById("code-display-' . $code->id . '").style.display="inline"; document.getElementById("code-edit-' . $code->id . '").style.display="none";']);
            echo html_writer::end_tag('form');
            echo html_writer::end_tag('span');
            echo html_writer::end_tag('td');
            
            // Created date.
            echo html_writer::tag('td', userdate($code->timecreated, get_string('strftimedatefullshort', 'langconfig')));
            
            // Actions.
            echo html_writer::start_tag('td');
            echo html_writer::tag('button', get_string('edit'), 
                ['class' => 'btn btn-sm btn-primary me-2', 
                 'onclick' => 'document.getElementById("code-display-' . $code->id . '").style.display="none"; document.getElementById("code-edit-' . $code->id . '").style.display="inline";']);
            $deleteurl = new moodle_url('/local/benefitsystem/manage_codes.php', 
                ['rewardid' => $rewardid, 'deletecode' => $code->id, 'sesskey' => sesskey()]);
            echo html_writer::link($deleteurl, get_string('delete'), 
                ['class' => 'btn btn-sm btn-danger',
                 'onclick' => 'return confirm("' . get_string('confirmdeletecode', 'local_benefitsystem') . '");']);
            echo html_writer::end_tag('td');
            
            echo html_writer::end_tag('tr');
        }
        
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
    
    // Display used codes table (locked/purchased).
    if (!empty($usedcodes)) {
        echo html_writer::tag('h3', get_string('purchasedcodes', 'local_benefitsystem'), 
            ['style' => 'margin-top: 20px; margin-bottom: 15px;']);
        
        echo html_writer::start_tag('table', ['class' => 'table table-striped table-bordered', 
            'style' => 'width: 100%; margin-bottom: 20px; opacity: 0.7;']);
        
        // Table header.
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('code', 'local_benefitsystem'), ['style' => 'width: 40%;']);
        echo html_writer::tag('th', get_string('created', 'local_benefitsystem'), ['style' => 'width: 25%;']);
        echo html_writer::tag('th', get_string('purchasedon', 'local_benefitsystem'), ['style' => 'width: 25%;']);
        echo html_writer::tag('th', get_string('status', 'local_benefitsystem'), ['style' => 'width: 10%;']);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        
        // Table body.
        echo html_writer::start_tag('tbody');
        
        foreach ($usedcodes as $code) {
            echo html_writer::start_tag('tr');
            
            // Code value (locked).
            echo html_writer::tag('td', html_writer::tag('code', htmlspecialchars($code->code), 
                ['style' => 'font-weight: bold; font-size: 1.1em;']));
            
            // Created date.
            echo html_writer::tag('td', userdate($code->timecreated, get_string('strftimedatefullshort', 'langconfig')));
            
            // Purchased date.
            $purchaseddate = $code->timeused > 0 ? 
                userdate($code->timeused, get_string('strftimedatefullshort', 'langconfig')) : '-';
            echo html_writer::tag('td', $purchaseddate);
            
            // Status.
            echo html_writer::tag('td', html_writer::tag('span', get_string('purchased', 'local_benefitsystem'), 
                ['class' => 'badge bg-danger']));
            
            echo html_writer::end_tag('tr');
        }
        
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
}

echo $OUTPUT->footer();
