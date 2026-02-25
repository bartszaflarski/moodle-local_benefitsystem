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

defined('MOODLE_INTERNAL') || die();

/**
 * Manage reward page - create/edit rewards
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

require_login();

$context = context_system::instance();
require_capability('local/benefitsystem:managerewards', $context);

$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/benefitsystem/manage_reward.php', ['id' => $id]));
if ($id) {
    $PAGE->set_title(get_string('editreward', 'local_benefitsystem'));
    $PAGE->set_heading(get_string('editreward', 'local_benefitsystem'));
} else {
    $PAGE->set_title(get_string('newreward', 'local_benefitsystem'));
    $PAGE->set_heading(get_string('newreward', 'local_benefitsystem'));
}
$PAGE->set_pagelayout('admin');

require_once($CFG->dirroot . '/local/benefitsystem/classes/form/reward_form.php');

global $DB;

// Handle delete.
if ($delete && confirm_sesskey()) {
    $reward = $DB->get_record('local_benefitsystem_rewards', ['id' => $delete], '*', MUST_EXIST);
    
    // Delete image file if exists.
    if (!empty($reward->image)) {
        $fs = get_file_storage();
        $fileinfo = json_decode($reward->image, true);
        if ($fileinfo && isset($fileinfo['filearea'])) {
            $file = $fs->get_file(
                $fileinfo['contextid'],
                $fileinfo['component'],
                $fileinfo['filearea'],
                $fileinfo['itemid'],
                $fileinfo['filepath'],
                $fileinfo['filename']
            );
            if ($file) {
                $file->delete();
            }
        }
    }
    
    $DB->delete_records('local_benefitsystem_rewards', ['id' => $delete]);
    redirect(new moodle_url('/local/benefitsystem/rewards.php'), 
        get_string('rewarddeleted', 'local_benefitsystem'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Get reward data if editing.
$reward = null;

if ($id) {
    $reward = $DB->get_record('local_benefitsystem_rewards', ['id' => $id], '*', MUST_EXIST);
}

// Prepare file manager for image - get draft itemid (check for submitted first, then create new).
$draftitemid = file_get_submitted_draft_itemid('image');
if (empty($draftitemid)) {
    $draftitemid = file_get_unused_draft_itemid();
}

if ($reward && !empty($reward->image)) {
    // Prepare file manager for image.
    $fileinfo = json_decode($reward->image, true);
    if ($fileinfo && isset($fileinfo['filearea']) && isset($fileinfo['itemid'])) {
        $fs = get_file_storage();
        // Use system context (files are stored in system context).
        $systemcontext = context_system::instance();
        
        // Use get_area_files to get all files in the area (more reliable).
        $files = $fs->get_area_files(
            $systemcontext->id,
            $fileinfo['component'] ?? 'local_benefitsystem',
            $fileinfo['filearea'] ?? 'reward_image',
            $fileinfo['itemid'],
            'id DESC',
            false
        );
        if (!empty($files)) {
            // Prepare draft area with the correct context and itemid.
            file_prepare_draft_area(
                $draftitemid,
                $systemcontext->id,
                $fileinfo['component'] ?? 'local_benefitsystem',
                $fileinfo['filearea'] ?? 'reward_image',
                $fileinfo['itemid'],
                ['subdirs' => 0, 'maxfiles' => 1]
            );
        }
    }
}

// Prepare form data.
$customdata = [
    'reward' => $reward
];

$formdata = [];
if ($reward) {
    $formdata['id'] = $reward->id;
    $formdata['name'] = $reward->name;
    $formdata['description'] = [
        'text' => $reward->description ?? '',
        'format' => FORMAT_HTML
    ];
    $formdata['image'] = $draftitemid;
    $formdata['type'] = $reward->type ?? 'digital';
    $formdata['digitalsubtype'] = $reward->digitalsubtype ?? 'file';
    $formdata['howtoredeem'] = [
        'text' => $reward->howtoredeem ?? '',
        'format' => FORMAT_HTML
    ];
    
    // Load existing codes for code-type rewards.
    if ($reward->digitalsubtype === 'code') {
        $existingcodes = $DB->get_records('local_benefitsystem_codes', 
            ['rewardid' => $reward->id, 'used' => 0], 
            'code ASC'
        );
        $codeslist = [];
        foreach ($existingcodes as $code) {
            $codeslist[] = $code->code;
        }
        $formdata['codes'] = implode("\n", $codeslist);
    }
    
    $formdata['points'] = $reward->points;
    // For code-type rewards, quantity is managed by codes (field is hidden anyway).
    if ($reward->digitalsubtype === 'code') {
        $formdata['quantitytype'] = 'infinite'; // Not used, but set for form consistency.
        $formdata['quantityvalue'] = '';
    } else if (is_null($reward->quantity)) {
        $formdata['quantitytype'] = 'infinite';
        $formdata['quantityvalue'] = '';
    } else {
        $formdata['quantitytype'] = 'limited';
        $formdata['quantityvalue'] = $reward->quantity;
    }
    $formdata['available'] = $reward->available ?? 1;
} else {
    $formdata['image'] = $draftitemid;
    $formdata['type'] = 'digital';
    $formdata['digitalsubtype'] = 'file';
    $formdata['howtoredeem'] = [
        'text' => '',
        'format' => FORMAT_HTML
    ];
    $formdata['quantitytype'] = 'infinite';
    $formdata['quantityvalue'] = '';
    $formdata['available'] = 1;
}

$mform = new \local_benefitsystem\form\reward_form(null, $customdata);

// Set form data.
if (!empty($formdata)) {
    $mform->set_data($formdata);
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/benefitsystem/rewards.php'));
} else if ($data = $mform->get_data()) {
    $now = time();
    
    if ($id) {
        // Update existing reward.
        $reward->name = $data->name;
        $reward->description = $data->description['text'] ?? '';
        $reward->type = $data->type;
        $reward->digitalsubtype = ($data->type === 'digital' && isset($data->digitalsubtype)) ? $data->digitalsubtype : null;
        $reward->howtoredeem = $data->howtoredeem['text'] ?? '';
        $reward->points = (int)$data->points;
        // Handle quantity: infinite (NULL) or limited (integer).
        // For code-type rewards, quantity is automatically set to NULL (managed by codes).
        if ($data->type === 'digital' && $data->digitalsubtype === 'code') {
            $reward->quantity = null; // Quantity for codes is managed by code count.
        } else if (isset($data->quantitytype) && $data->quantitytype === 'infinite') {
            $reward->quantity = null;
        } else if (isset($data->quantitytype) && $data->quantitytype === 'limited' && !empty($data->quantityvalue)) {
            $reward->quantity = (int)$data->quantityvalue;
        } else {
            $reward->quantity = null; // Default to infinite if not specified.
        }
        $reward->available = (int)$data->available;
        $reward->timemodified = $now;
        
        // Handle image upload.
        if (!empty($data->image)) {
            $fs = get_file_storage();
            $context = context_system::instance();
            $fileinfo = file_get_draft_area_info($data->image);
            
            if ($fileinfo['filecount'] > 0) {
                // Delete old image if exists.
                if (!empty($reward->image)) {
                    $oldfileinfo = json_decode($reward->image, true);
                    if ($oldfileinfo && isset($oldfileinfo['filearea'])) {
                        $oldfile = $fs->get_file(
                            $oldfileinfo['contextid'],
                            $oldfileinfo['component'],
                            $oldfileinfo['filearea'],
                            $oldfileinfo['itemid'],
                            $oldfileinfo['filepath'],
                            $oldfileinfo['filename']
                        );
                        if ($oldfile) {
                            $oldfile->delete();
                        }
                    }
                }
                
                // Save new image.
                file_save_draft_area_files(
                    $data->image,
                    $context->id,
                    'local_benefitsystem',
                    'reward_image',
                    $reward->id,
                    ['subdirs' => 0, 'maxfiles' => 1]
                );
                
                // Get the saved file and store its info.
                $files = $fs->get_area_files(
                    $context->id,
                    'local_benefitsystem',
                    'reward_image',
                    $reward->id,
                    'id DESC',
                    false
                );
                if ($file = reset($files)) {
                    $reward->image = json_encode([
                        'contextid' => $file->get_contextid(),
                        'component' => $file->get_component(),
                        'filearea' => $file->get_filearea(),
                        'itemid' => $file->get_itemid(),
                        'filepath' => $file->get_filepath(),
                        'filename' => $file->get_filename()
                    ]);
                }
            }
        }
        
        // Handle codes for code-type rewards.
        if ($data->type === 'digital' && $data->digitalsubtype === 'code') {
            $codes = [];
            
            // Get codes from textarea.
            if (!empty($data->codes)) {
                $codes = array_merge($codes, local_benefitsystem_parse_codes_from_text($data->codes));
            }
            
            // Get codes from CSV file if uploaded.
            if (!empty($data->codescsv)) {
                global $USER;
                $fs = get_file_storage();
                $context = context_user::instance($USER->id);
                $files = $fs->get_area_files(
                    $context->id,
                    'user',
                    'draft',
                    $data->codescsv,
                    'id DESC',
                    false
                );
                if ($file = reset($files)) {
                    $csvcodes = local_benefitsystem_parse_codes_from_csv($file);
                    $codes = array_merge($codes, $csvcodes);
                    // Delete draft file after processing.
                    $file->delete();
                }
            }
            
            // Save codes.
            if (!empty($codes)) {
                $savedcount = local_benefitsystem_save_reward_codes($reward->id, $codes);
                if ($savedcount > 0) {
                    \core\notification::info(get_string('codessaved', 'local_benefitsystem', $savedcount));
                    
                    // If reward was marked as unavailable (no codes), mark it as available again.
                    if (empty($reward->available)) {
                        $reward->available = 1;
                    }
                }
            }
        }
        
        $DB->update_record('local_benefitsystem_rewards', $reward);
        redirect(new moodle_url('/local/benefitsystem/rewards.php'), 
            get_string('rewardupdated', 'local_benefitsystem'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // Create new reward.
        $reward = new stdClass();
        $reward->name = $data->name;
        $reward->description = $data->description['text'] ?? '';
        $reward->type = $data->type;
        $reward->digitalsubtype = ($data->type === 'digital' && isset($data->digitalsubtype)) ? $data->digitalsubtype : null;
        $reward->howtoredeem = $data->howtoredeem['text'] ?? '';
        $reward->points = (int)$data->points;
        // Handle quantity: infinite (NULL) or limited (integer).
        // For code-type rewards, quantity is automatically set to NULL (managed by codes).
        if ($data->type === 'digital' && $data->digitalsubtype === 'code') {
            $reward->quantity = null; // Quantity for codes is managed by code count.
        } else if (isset($data->quantitytype) && $data->quantitytype === 'infinite') {
            $reward->quantity = null;
        } else if (isset($data->quantitytype) && $data->quantitytype === 'limited' && !empty($data->quantityvalue)) {
            $reward->quantity = (int)$data->quantityvalue;
        } else {
            $reward->quantity = null; // Default to infinite if not specified.
        }
        $reward->available = (int)$data->available;
        $reward->timecreated = $now;
        $reward->timemodified = $now;
        $reward->image = null;
        
        $reward->id = $DB->insert_record('local_benefitsystem_rewards', $reward);
        
        // Handle image upload.
        if (!empty($data->image)) {
            $fs = get_file_storage();
            $context = context_system::instance();
            
            file_save_draft_area_files(
                $data->image,
                $context->id,
                'local_benefitsystem',
                'reward_image',
                $reward->id,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            
            // Get the saved file and store its info.
            $files = $fs->get_area_files(
                $context->id,
                'local_benefitsystem',
                'reward_image',
                $reward->id,
                'id DESC',
                false
            );
            if ($file = reset($files)) {
                $reward->image = json_encode([
                    'contextid' => $file->get_contextid(),
                    'component' => $file->get_component(),
                    'filearea' => $file->get_filearea(),
                    'itemid' => $file->get_itemid(),
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename()
                ]);
                $DB->update_record('local_benefitsystem_rewards', $reward);
            }
        }
        
        // Handle codes for code-type rewards.
        if ($data->type === 'digital' && $data->digitalsubtype === 'code') {
            $codes = [];
            
            // Get codes from textarea.
            if (!empty($data->codes)) {
                $codes = array_merge($codes, local_benefitsystem_parse_codes_from_text($data->codes));
            }
            
            // Get codes from CSV file if uploaded.
            if (!empty($data->codescsv)) {
                global $USER;
                $fs = get_file_storage();
                $context = context_user::instance($USER->id);
                $files = $fs->get_area_files(
                    $context->id,
                    'user',
                    'draft',
                    $data->codescsv,
                    'id DESC',
                    false
                );
                if ($file = reset($files)) {
                    $csvcodes = local_benefitsystem_parse_codes_from_csv($file);
                    $codes = array_merge($codes, $csvcodes);
                    // Delete draft file after processing.
                    $file->delete();
                }
            }
            
            // Save codes.
            if (!empty($codes)) {
                $savedcount = local_benefitsystem_save_reward_codes($reward->id, $codes);
                if ($savedcount > 0) {
                    \core\notification::info(get_string('codessaved', 'local_benefitsystem', $savedcount));
                    
                    // Ensure reward is marked as available when codes are added.
                    if (empty($reward->available)) {
                        $reward->available = 1;
                        $reward->timemodified = time();
                        $DB->update_record('local_benefitsystem_rewards', $reward);
                    }
                }
            }
        }
        
        redirect(new moodle_url('/local/benefitsystem/rewards.php'), 
            get_string('rewardcreated', 'local_benefitsystem'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

// Add link to manage codes if this is a code-type reward.
if ($reward && $reward->type === 'digital' && $reward->digitalsubtype === 'code') {
    $codesurl = new moodle_url('/local/benefitsystem/manage_codes.php', ['rewardid' => $reward->id]);
    echo html_writer::start_div('mb-3');
    echo html_writer::link($codesurl, get_string('managecodes', 'local_benefitsystem'), 
        ['class' => 'btn btn-info']);
    echo html_writer::end_div();
}

$mform->display();

echo $OUTPUT->footer();
