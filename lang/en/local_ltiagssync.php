<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

/**
 * English strings for local_ltiagssync.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LTI Advantage per-activity grade sync';
$string['tasksyncgrades'] = 'Sync per-activity grades to LTI platforms (AGS)';
$string['enabled'] = 'Enable per-activity grade sync';
$string['enabled_desc'] = 'When enabled, courses shared via "Publish as LTI tool" report each graded activity as a separate AGS line item (a gradebook column on the consumer platform), in addition to the course total that enrol_lti sends by default.';
$string['includemanual'] = 'Include manual grade items';
$string['includemanual_desc'] = 'Also sync manually created gradebook items (item type "manual"), not only activity (module) grades.';
$string['privacy:metadata:local_ltiagssync_state'] = 'Per-activity grade values already synced to a consumer platform, kept to avoid re-sending unchanged grades.';
$string['privacy:metadata:local_ltiagssync_state:ltiuserid'] = 'The enrolment record of the platform user whose grade was synced.';
$string['privacy:metadata:local_ltiagssync_state:gradeitemid'] = 'The activity gradebook item the grade belongs to.';
$string['privacy:metadata:local_ltiagssync_state:lastgrade'] = 'The last grade value sent to the platform.';
$string['privacy:metadata:local_ltiagssync_state:timemodified'] = 'When the grade was last sent.';
