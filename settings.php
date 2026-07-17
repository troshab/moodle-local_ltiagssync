<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

/**
 * Admin settings.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ltiagssync', get_string('pluginname', 'local_ltiagssync'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_ltiagssync/enabled',
        get_string('enabled', 'local_ltiagssync'),
        get_string('enabled_desc', 'local_ltiagssync'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ltiagssync/includemanual',
        get_string('includemanual', 'local_ltiagssync'),
        get_string('includemanual_desc', 'local_ltiagssync'),
        0
    ));
}
