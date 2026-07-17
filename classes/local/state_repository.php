<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

namespace local_ltiagssync\local;

/**
 * Stores the last per-(user, activity) grade sent to a platform, so unchanged grades are skipped.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */
class state_repository {

    /** @var string the state table name. */
    const TABLE = 'local_ltiagssync_state';

    /**
     * The last grade value synced for a user/activity pair, or null if never synced.
     *
     * @param int $ltiuserid enrol_lti_users.id
     * @param int $gradeitemid grade_items.id
     * @return float|null
     */
    public function get_lastgrade(int $ltiuserid, int $gradeitemid): ?float {
        global $DB;
        $val = $DB->get_field(self::TABLE, 'lastgrade', [
            'ltiuserid' => $ltiuserid,
            'gradeitemid' => $gradeitemid,
        ]);
        return ($val === false || $val === null) ? null : (float) $val;
    }

    /**
     * Record the grade just sent for a user/activity pair (insert or update).
     *
     * @param int $toolid enrol_lti_tools.id
     * @param int $ltiuserid enrol_lti_users.id
     * @param int $gradeitemid grade_items.id
     * @param float $grade the grade value that was sent
     * @return void
     */
    public function set_lastgrade(int $toolid, int $ltiuserid, int $gradeitemid, float $grade): void {
        global $DB;
        $now = time();
        $existing = $DB->get_record(self::TABLE, [
            'ltiuserid' => $ltiuserid,
            'gradeitemid' => $gradeitemid,
        ]);
        if ($existing) {
            $existing->toolid = $toolid;
            $existing->lastgrade = $grade;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
        } else {
            $DB->insert_record(self::TABLE, (object) [
                'toolid' => $toolid,
                'ltiuserid' => $ltiuserid,
                'gradeitemid' => $gradeitemid,
                'lastgrade' => $grade,
                'timemodified' => $now,
            ]);
        }
    }
}
