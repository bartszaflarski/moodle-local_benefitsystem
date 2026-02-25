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
 * Language strings for local_benefitsystem.
 *
 * @package     local_benefitsystem
 * @copyright   2025
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Benefit system';
$string['points'] = 'Points';
$string['points_desc'] = 'Number of points to award when this activity is completed';
$string['points_help'] = 'Enter the number of points that will be added to the user\'s balance when they complete this activity. Leave empty or set to 0 for no points.';
$string['userbalance'] = 'Your points balance';
$string['pointsawarded'] = 'Points awarded';
$string['pointsadded'] = '{$a} points have been added to your balance';
$string['pointsaddednotification'] = 'Congratulations! You earned {$a->points} points for completing "{$a->activityname}". Your total balance is now {$a->totalpoints} points.';
$string['pointsaddednotificationsubject'] = 'Points earned for activity completion';
$string['messageprovider:points_earned'] = 'Points earned notifications';
$string['coursepoints'] = 'Course Completion Points';
$string['coursepointsaddednotification'] = 'Congratulations! You earned {$a->points} points for completing the course "{$a->coursename}". Your total balance is now {$a->totalpoints} points.';
$string['coursepointsaddednotificationsubject'] = 'Points earned for course completion';
$string['rewards'] = 'Rewards';
$string['addreward'] = 'Add Reward';
$string['managereward'] = 'Manage Reward';
$string['editreward'] = 'Edit Reward';
$string['newreward'] = 'New Reward';
$string['image'] = 'Image';
$string['image_help'] = 'Upload an image for this reward';
$string['type'] = 'Type';
$string['type_help'] = 'Select whether this is a digital reward (e.g., certificate, badge) or physical reward (e.g., merchandise)';
$string['typedigital'] = 'Digital';
$string['typephysical'] = 'Physical';
$string['digitalsubtype'] = 'Digital Subtype';
$string['digitalsubtype_help'] = 'For digital rewards, specify whether this is a file (e.g., ebook) or a code (e.g., voucher code)';
$string['subtypefile'] = 'File (ebook)';
$string['subtypecode'] = 'Code';
$string['codes'] = 'Codes';
$string['codes_help'] = 'Enter codes one per line. Codes will be assigned to users when they exchange points for this reward.';
$string['codescsv'] = 'Upload Codes (CSV)';
$string['codescsv_help'] = 'Upload a CSV file with codes. Each line should contain one code. This will add codes to the existing list.';
$string['howtoredeem'] = 'How to Redeem';
$string['howtoredeem_help'] = 'Instructions on how users can redeem this reward after exchanging points for it.';
$string['howtoredeem_empty'] = 'No specific instructions.';
$string['codessaved'] = 'Saved {$a} new code(s)';
$string['yourcode'] = 'Your Code';
$string['quantity'] = 'Quantity';
$string['quantity_help'] = 'Set the available quantity for this reward. Select "Infinite" for unlimited availability, or "Limited" and enter a number. Note: For code-type rewards, quantity is automatically determined by the number of available codes.';
$string['infinite'] = 'Infinite';
$string['limited'] = 'Limited';
$string['quantitymustbenumber'] = 'Quantity must be a number';
$string['quantitymustbepositive'] = 'Quantity must be a positive number';
$string['quantityavailable'] = 'Available: {$a}';
$string['quantityinfinite'] = 'Available: Infinite';
$string['quantityunavailable'] = 'This reward is out of stock';
$string['codesavailable'] = 'Codes available: {$a}';
$string['code'] = 'Code';
$string['admincannotexchange'] = 'Administrators cannot exchange points for rewards';
$string['nocodesavailable'] = 'No codes available for this reward';
$string['markasredeemed'] = 'Mark as Redeemed';
$string['confirmredeem'] = 'Are you sure you want to mark this reward as redeemed? This will move it to your exchange history.';
$string['markedasredeemed'] = 'Reward marked as redeemed successfully!';
$string['redeemfailed'] = 'Failed to mark reward as redeemed. Please try again.';
$string['exchangehistory'] = 'Exchange History';
$string['redeemed'] = 'Redeemed';
$string['redeemedon'] = 'Redeemed on';
$string['reward'] = 'Reward';
$string['purchasehistory'] = 'Purchase History';
$string['backtorewards'] = 'Back to Rewards';
$string['activeexchanges'] = 'Active Exchanges';
$string['noactiveexchanges'] = 'No active exchanges found.';
$string['nohistory'] = 'No exchange history found.';
$string['user'] = 'User';
$string['managecodes'] = 'Manage Codes';
$string['availablecodes'] = 'Available Codes';
$string['purchasedcodes'] = 'Purchased Codes (Locked)';
$string['codedeleted'] = 'Code deleted successfully';
$string['codeupdated'] = 'Code updated successfully';
$string['codenotfound'] = 'Code not found or cannot be modified';
$string['codealreadyexists'] = 'This code already exists for this reward';
$string['confirmdeletecode'] = 'Are you sure you want to delete this code? This action cannot be undone.';
$string['editreward'] = 'Edit Reward';
$string['nocodesfound'] = 'No codes found for this reward.';
$string['created'] = 'Created';
$string['actions'] = 'Actions';
$string['purchasedon'] = 'Purchased On';
$string['purchased'] = 'Purchased';
$string['status'] = 'Status';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['invalidrewardtype'] = 'This page is only available for code-type rewards';
$string['addcode'] = 'Add Code';
$string['codeadded'] = 'Code added successfully';
$string['entercode'] = 'Enter code';
$string['cost'] = 'Cost (Points)';
$string['cost_help'] = 'Number of points required to exchange for this reward';
$string['available'] = 'Available';
$string['rewardcreated'] = 'Reward created successfully';
$string['rewardupdated'] = 'Reward updated successfully';
$string['rewarddeleted'] = 'Reward deleted successfully';
$string['confirmdeletereward'] = 'Are you sure you want to delete this reward?';
$string['pointsmustbepositive'] = 'Points must be a positive number';
$string['managerewards'] = 'Manage rewards';
$string['unavailable'] = 'Unavailable';
$string['name'] = 'Name';
$string['description'] = 'Description';
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['availablerewards'] = 'Available Rewards';
$string['yourbalance'] = 'Your Balance';
$string['pointsrequired'] = 'Points Required';
$string['youcanafford'] = 'You can afford this';
$string['needmorepoints'] = 'You need {$a} more points';
$string['norewardsavailable'] = 'No rewards are currently available.';
$string['viewrewards'] = 'View Rewards';
$string['exchangereward'] = 'Exchange';
$string['exchangepoints'] = 'Exchange Points';
$string['confirmexchange'] = 'Are you sure you want to exchange {$a->points} points for "{$a->name}"? This reward can be purchased multiple times.';
$string['rewardexchanged'] = 'Successfully exchanged {$a->points} points for "{$a->name}"!';
$string['exchangefailed'] = 'Failed to exchange reward. Please try again.';
$string['notenoughpoints'] = 'You do not have enough points to exchange for this reward.';
$string['rewardnotavailable'] = 'This reward is not currently available.';
$string['benefitsystem:viewrewards'] = 'View rewards and own exchanged rewards';
$string['benefitsystem:managerewards'] = 'Manage Benefit system rewards';
$string['yourrewards'] = 'Your Rewards';
$string['exchangedrewards'] = 'Exchanged Rewards';
$string['noexchanges'] = 'You have not exchanged any rewards yet.';
$string['exchangedon'] = 'Exchanged on';
$string['pointsspent'] = 'Points Spent';
$string['benefitpoints'] = 'Benefit points';
$string['benefitpoints_help'] = 'Enter the number of points that will be awarded when users complete this course. This is separate from course completion points and applies to the main course settings.';

// Privacy API.
$string['privacy:metadata:balance'] = 'Stores the user\'s points balance.';
$string['privacy:metadata:balance:userid'] = 'The user ID.';
$string['privacy:metadata:balance:points'] = 'Current points balance.';
$string['privacy:metadata:balance:timecreated'] = 'When the balance record was created.';
$string['privacy:metadata:balance:timemodified'] = 'When the balance was last modified.';
$string['privacy:metadata:history'] = 'History of points awarded to the user (activity/course completion).';
$string['privacy:metadata:history:userid'] = 'The user who received the points.';
$string['privacy:metadata:history:coursemoduleid'] = 'Activity that awarded points (0 if course completion).';
$string['privacy:metadata:history:courseid'] = 'Course ID (for course completion points).';
$string['privacy:metadata:history:points'] = 'Points awarded.';
$string['privacy:metadata:history:timecreated'] = 'When the points were awarded.';
$string['privacy:metadata:exchanges'] = 'Reward exchanges made by the user (active, not yet redeemed).';
$string['privacy:metadata:exchanges:userid'] = 'The user who made the exchange.';
$string['privacy:metadata:exchanges:rewardid'] = 'The reward that was exchanged.';
$string['privacy:metadata:exchanges:points'] = 'Points spent.';
$string['privacy:metadata:exchanges:status'] = 'Exchange status.';
$string['privacy:metadata:exchanges:timecreated'] = 'When the exchange was made.';
$string['privacy:metadata:exchanges:timemodified'] = 'When the exchange was last modified.';
$string['privacy:metadata:exchange_history'] = 'History of redeemed reward exchanges.';
$string['privacy:metadata:exchange_history:userid'] = 'The user who made the exchange.';
$string['privacy:metadata:exchange_history:rewardid'] = 'The reward that was exchanged.';
$string['privacy:metadata:exchange_history:points'] = 'Points spent.';
$string['privacy:metadata:exchange_history:code'] = 'Redemption code (if code-type reward).';
$string['privacy:metadata:exchange_history:timecreated'] = 'When the exchange was made.';
$string['privacy:metadata:exchange_history:timeredeemed'] = 'When the user marked the reward as redeemed.';
