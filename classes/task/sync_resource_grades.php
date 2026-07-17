<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

namespace local_ltiagssync\task;

use core\task\adhoc_task;
use local_ltiagssync\local\grade_pusher;

/**
 * Adhoc task: push a per-activity line item + score for one shared course-level resource.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */
class sync_resource_grades extends adhoc_task {

    /**
     * @return void
     */
    public function execute() {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        $resource = $this->get_custom_data();
        if (empty($resource) || empty($resource->id)) {
            mtrace('Skipping - no resource data supplied to the adhoc task.');
            return;
        }

        mtrace("Starting - per-activity grade sync for shared resource '{$resource->id}' (course {$resource->courseid}).");

        $pusher = new grade_pusher();
        [$usercount, $itemcount, $sendcount] = $pusher->push_for_resource($resource);

        mtrace("Completed - resource '{$resource->id}': processed {$usercount} user(s) across {$itemcount} activity item(s); " .
            "sent {$sendcount} grade(s).");
    }
}
