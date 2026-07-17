<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

namespace local_ltiagssync\task;

use core\task\scheduled_task;
use enrol_lti\helper;

/**
 * Scheduled task: spawn one adhoc task per shared course-level resource to push per-activity grades.
 *
 * Mirrors enrol_lti's own \enrol_lti\local\ltiadvantage\task\sync_grades, but only targets
 * course-level shared resources (module-level resources already sync their single grade via enrol_lti).
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */
class sync_grades extends scheduled_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('tasksyncgrades', 'local_ltiagssync');
    }

    /**
     * Queue an adhoc sync_resource_grades task for every eligible shared resource.
     *
     * @return bool
     */
    public function execute() {
        if (get_config('local_ltiagssync', 'enabled') === '0') {
            mtrace('Skipping - local_ltiagssync is disabled in settings.');
            return true;
        }
        if (!is_enabled_auth('lti') || !enrol_is_enabled('lti')) {
            mtrace('Skipping - auth_lti and/or enrol_lti is not enabled.');
            return true;
        }

        $resources = helper::get_lti_tools([
            'status' => ENROL_INSTANCE_ENABLED,
            'gradesync' => 1,
            'ltiversion' => 'LTI-1p3',
        ]);
        if (empty($resources)) {
            mtrace('Skipping - no LTI 1.3 shared resources with grade sync enabled.');
            return true;
        }

        $spawned = 0;
        foreach ($resources as $resource) {
            // Only course-level shared resources benefit from a per-activity breakdown.
            $context = \context::instance_by_id($resource->contextid, IGNORE_MISSING);
            if (!$context || $context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $task = new sync_resource_grades();
            $task->set_custom_data($resource);
            $task->set_component('local_ltiagssync');
            \core\task\manager::queue_adhoc_task($task, true);
            $spawned++;
        }

        mtrace("Spawned $spawned adhoc task(s) for per-activity grade sync.");
        return true;
    }
}
