<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

/**
 * Plugin version and metadata.
 *
 * Provider-side companion for enrol_lti: when a whole course is shared via
 * "Publish as LTI tool" (LTI 1.3 / Advantage), stock enrol_lti reports only the
 * course total grade as a single AGS line item. This plugin additionally reports
 * every graded activity as its own decoupled line item, so the consumer platform
 * renders a separate gradebook column per activity.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ltiagssync';
$plugin->version   = 2026071701;
$plugin->requires  = 2023100900;        // Moodle 4.3 - first release with the Packback-based enrol_lti AGS tool-side sync.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    'enrol_lti' => ANY_VERSION,          // Reuses enrol_lti's LTI 1.3 registration, repositories and the bundled Packback\Lti1p3 library.
];
