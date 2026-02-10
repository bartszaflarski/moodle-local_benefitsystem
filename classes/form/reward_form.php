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
 * Reward form
 *
 * @package     local_benefitsystem
 * @copyright 2026 Bartosz Szaflarski bartosz.szaflarski@shafla.pl
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_benefitsystem\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing rewards
 */
class reward_form extends \moodleform {

    /**
     * Form definition
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $reward = $this->_customdata['reward'] ?? null;

        // Reward name.
        $mform->addElement('text', 'name', get_string('name'), ['size' => 50, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Description.
        $mform->addElement('editor', 'description', get_string('description'), null, [
            'maxfiles' => 0,
            'maxbytes' => 0,
            'context' => \context_system::instance()
        ]);
        $mform->setType('description', PARAM_RAW);

        // Image upload.
        $mform->addElement('filemanager', 'image', get_string('image', 'local_benefitsystem'), null, [
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
            'subdirs' => 0,
            'maxbytes' => $CFG->maxbytes
        ]);
        $mform->addHelpButton('image', 'image', 'local_benefitsystem');

        // Type (digital or physical).
        $mform->addElement('select', 'type', get_string('type', 'local_benefitsystem'), [
            'digital' => get_string('typedigital', 'local_benefitsystem'),
            'physical' => get_string('typephysical', 'local_benefitsystem')
        ]);
        $mform->setType('type', PARAM_TEXT);
        $mform->setDefault('type', 'digital');
        $mform->addHelpButton('type', 'type', 'local_benefitsystem');

        // Digital subtype (file or code) - only shown when type is digital.
        $mform->addElement('select', 'digitalsubtype', get_string('digitalsubtype', 'local_benefitsystem'), [
            'file' => get_string('subtypefile', 'local_benefitsystem'),
            'code' => get_string('subtypecode', 'local_benefitsystem')
        ]);
        $mform->setType('digitalsubtype', PARAM_TEXT);
        $mform->setDefault('digitalsubtype', 'file');
        $mform->addHelpButton('digitalsubtype', 'digitalsubtype', 'local_benefitsystem');
        $mform->hideIf('digitalsubtype', 'type', 'neq', 'digital');

        // Codes textarea - only shown when digitalsubtype is code.
        $mform->addElement('textarea', 'codes', get_string('codes', 'local_benefitsystem'), [
            'rows' => 5,
            'cols' => 50
        ]);
        $mform->setType('codes', PARAM_TEXT);
        $mform->addHelpButton('codes', 'codes', 'local_benefitsystem');
        $mform->hideIf('codes', 'digitalsubtype', 'neq', 'code');
        $mform->hideIf('codes', 'type', 'neq', 'digital');

        // CSV upload for codes - only shown when digitalsubtype is code.
        $mform->addElement('filepicker', 'codescsv', get_string('codescsv', 'local_benefitsystem'), null, [
            'accepted_types' => ['.csv']
        ]);
        $mform->addHelpButton('codescsv', 'codescsv', 'local_benefitsystem');
        $mform->hideIf('codescsv', 'digitalsubtype', 'neq', 'code');
        $mform->hideIf('codescsv', 'type', 'neq', 'digital');

        // How to redeem field.
        $mform->addElement('editor', 'howtoredeem', get_string('howtoredeem', 'local_benefitsystem'), null, [
            'maxfiles' => 0,
            'maxbytes' => 0,
            'context' => \context_system::instance()
        ]);
        $mform->setType('howtoredeem', PARAM_RAW);
        $mform->addHelpButton('howtoredeem', 'howtoredeem', 'local_benefitsystem');

        // Cost (points).
        $mform->addElement('text', 'points', get_string('cost', 'local_benefitsystem'), ['size' => 10, 'maxlength' => 10]);
        $mform->setType('points', PARAM_INT);
        $mform->addRule('points', null, 'required', null, 'client');
        $mform->addRule('points', get_string('error'), 'numeric', null, 'client');
        $mform->addRule('points', get_string('error'), 'regex', '/^[0-9]+$/', 'client');
        $mform->addHelpButton('points', 'cost', 'local_benefitsystem');

        // Quantity (infinite or integer) - hidden for code-type rewards (quantity = number of codes).
        $quantitygroup = [];
        $quantitygroup[] = $mform->createElement('radio', 'quantitytype', '', get_string('infinite', 'local_benefitsystem'), 'infinite');
        $quantitygroup[] = $mform->createElement('radio', 'quantitytype', '', get_string('limited', 'local_benefitsystem'), 'limited');
        $quantitygroup[] = $mform->createElement('text', 'quantityvalue', '', ['size' => 10, 'maxlength' => 10]);
        $mform->addGroup($quantitygroup, 'quantitygroup', get_string('quantity', 'local_benefitsystem'), [' '], false);
        $mform->setType('quantityvalue', PARAM_INT);
        $mform->addHelpButton('quantitygroup', 'quantity', 'local_benefitsystem');
        $mform->hideIf('quantityvalue', 'quantitytype', 'neq', 'limited');
        $mform->hideIf('quantitygroup', 'digitalsubtype', 'eq', 'code');
        $mform->hideIf('quantitygroup', 'type', 'neq', 'digital');
        $mform->setDefault('quantitytype', 'infinite');

        // Available checkbox.
        $mform->addElement('advcheckbox', 'available', get_string('available', 'local_benefitsystem'));
        $mform->setType('available', PARAM_INT);
        $mform->setDefault('available', 1);

        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['points']) && $data['points'] < 0) {
            $errors['points'] = get_string('pointsmustbepositive', 'local_benefitsystem');
        }

        // Validate quantity if limited.
        if (isset($data['quantitytype']) && $data['quantitytype'] === 'limited') {
            if (empty($data['quantityvalue']) || !is_numeric($data['quantityvalue'])) {
                $errors['quantitygroup'] = get_string('quantitymustbenumber', 'local_benefitsystem');
            } else if ((int)$data['quantityvalue'] < 0) {
                $errors['quantitygroup'] = get_string('quantitymustbepositive', 'local_benefitsystem');
            }
        }

        return $errors;
    }
}
