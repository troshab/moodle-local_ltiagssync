<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

namespace local_ltiagssync\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the per-activity grade sync state table.
 *
 * The state table caches, per user and activity, the last grade value already delivered to a
 * consumer platform (so unchanged grades are not re-sent). The underlying grades themselves live
 * in the course gradebook and are handled by core.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_ltiagssync_state', [
            'ltiuserid' => 'privacy:metadata:local_ltiagssync_state:ltiuserid',
            'gradeitemid' => 'privacy:metadata:local_ltiagssync_state:gradeitemid',
            'lastgrade' => 'privacy:metadata:local_ltiagssync_state:lastgrade',
            'timemodified' => 'privacy:metadata:local_ltiagssync_state:timemodified',
        ], 'privacy:metadata:local_ltiagssync_state');
        return $collection;
    }

    /**
     * Course contexts holding synced-grade state for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT t.contextid
                  FROM {local_ltiagssync_state} s
                  JOIN {enrol_lti_users} lu ON lu.id = s.ltiuserid
                  JOIN {enrol_lti_tools} t ON t.id = s.toolid
                 WHERE lu.userid = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users with synced-grade state in a given course context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $sql = "SELECT lu.userid
                  FROM {local_ltiagssync_state} s
                  JOIN {enrol_lti_users} lu ON lu.id = s.ltiuserid
                  JOIN {enrol_lti_tools} t ON t.id = s.toolid
                 WHERE t.contextid = :contextid";
        $userlist->add_from_sql('userid', $sql, ['contextid' => $context->id]);
    }

    /**
     * Export the synced-grade state for a user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $sql = "SELECT s.id, s.gradeitemid, s.lastgrade, s.timemodified
                      FROM {local_ltiagssync_state} s
                      JOIN {enrol_lti_users} lu ON lu.id = s.ltiuserid
                      JOIN {enrol_lti_tools} t ON t.id = s.toolid
                     WHERE t.contextid = :contextid AND lu.userid = :userid";
            $records = $DB->get_records_sql($sql, ['contextid' => $context->id, 'userid' => $user->id]);
            if (!$records) {
                continue;
            }
            $data = array_map(function($r) {
                return [
                    'gradeitemid' => $r->gradeitemid,
                    'lastgrade' => $r->lastgrade,
                    'timemodified' => \core_privacy\local\request\transform::datetime($r->timemodified),
                ];
            }, array_values($records));
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_ltiagssync')],
                (object) ['synced_grades' => $data]
            );
        }
    }

    /**
     * Delete all synced-grade state in a course context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_course) {
            return;
        }
        $toolids = $DB->get_fieldset_select('enrol_lti_tools', 'id', 'contextid = :contextid',
            ['contextid' => $context->id]);
        if ($toolids) {
            $DB->delete_records_list('local_ltiagssync_state', 'toolid', $toolids);
        }
    }

    /**
     * Delete a single user's synced-grade state across the given contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $sql = "SELECT s.id
                      FROM {local_ltiagssync_state} s
                      JOIN {enrol_lti_users} lu ON lu.id = s.ltiuserid
                      JOIN {enrol_lti_tools} t ON t.id = s.toolid
                     WHERE t.contextid = :contextid AND lu.userid = :userid";
            $ids = $DB->get_fieldset_sql($sql, ['contextid' => $context->id, 'userid' => $user->id]);
            if ($ids) {
                $DB->delete_records_list('local_ltiagssync_state', 'id', $ids);
            }
        }
    }

    /**
     * Delete several users' synced-grade state in a course context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "SELECT s.id
                  FROM {local_ltiagssync_state} s
                  JOIN {enrol_lti_users} lu ON lu.id = s.ltiuserid
                  JOIN {enrol_lti_tools} t ON t.id = s.toolid
                 WHERE t.contextid = :contextid AND lu.userid $insql";
        $ids = $DB->get_fieldset_sql($sql, array_merge(['contextid' => $context->id], $inparams));
        if ($ids) {
            $DB->delete_records_list('local_ltiagssync_state', 'id', $ids);
        }
    }
}
