<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

namespace local_ltiagssync;

use core\event\user_graded;
use enrol_lti\helper;
use local_ltiagssync\task\sync_resource_grades;

/**
 * Event observers for near-real-time per-activity grade sync.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */
class observer {

    /**
     * When a user is graded in a shared course, queue a per-activity sync for that resource.
     *
     * The adhoc task is queued with de-duplication, so a burst of grade changes collapses into a
     * single sync run; the state table then ensures only the changed activity grades are sent.
     *
     * @param user_graded $event
     * @return void
     */
    public static function user_graded(user_graded $event): void {
        if (get_config('local_ltiagssync', 'enabled') === '0') {
            return;
        }
        if (!is_enabled_auth('lti') || !enrol_is_enabled('lti')) {
            return;
        }
        $courseid = $event->courseid;
        if (empty($courseid)) {
            return;
        }

        $resources = helper::get_lti_tools([
            'courseid' => $courseid,
            'gradesync' => 1,
            'ltiversion' => 'LTI-1p3',
            'status' => ENROL_INSTANCE_ENABLED,
        ]);
        foreach ($resources as $resource) {
            $context = \context::instance_by_id($resource->contextid, IGNORE_MISSING);
            if (!$context || $context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $task = new sync_resource_grades();
            $task->set_custom_data($resource);
            $task->set_component('local_ltiagssync');
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }
}
