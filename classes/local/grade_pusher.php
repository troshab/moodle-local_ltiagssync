<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

namespace local_ltiagssync\local;

use core\http_client;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use Packback\Lti1p3\LtiAssignmentsGradesService;
use Packback\Lti1p3\LtiGrade;
use Packback\Lti1p3\LtiLineitem;
use Packback\Lti1p3\LtiServiceConnector;

/**
 * Pushes one AGS line item + score per graded activity of a shared course-level resource.
 *
 * Reuses enrol_lti's LTI 1.3 registration lookup, resource-link/user repositories and the bundled
 * Packback\Lti1p3 library - the same machinery enrol_lti's own tool-side grade sync uses - but instead
 * of a single course-total line item it creates a decoupled line item for each course grade item.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */
class grade_pusher {

    /** @var int[] HTTP statuses AGS treats as a successful grade delivery. */
    const SUCCESS_STATUSES = [200, 201, 202, 204];

    /** @var user_repository */
    protected $userrepo;

    /** @var resource_link_repository */
    protected $resourcelinkrepo;

    /** @var application_registration_repository */
    protected $appregistrationrepo;

    /** @var issuer_database */
    protected $issuerdb;

    /** @var state_repository */
    protected $staterepo;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->userrepo = new user_repository();
        $this->resourcelinkrepo = new resource_link_repository();
        $this->appregistrationrepo = new application_registration_repository();
        $this->issuerdb = new issuer_database($this->appregistrationrepo, new deployment_repository());
        $this->staterepo = new state_repository();
    }

    /**
     * Push a per-activity line item + score for every graded activity in the resource's course.
     *
     * @param \stdClass $resource enrol_lti_tools record for the shared (course-level) resource.
     * @return array [usercount, itemcount, sendcount]
     */
    public function push_for_resource(\stdClass $resource): array {
        $context = \context::instance_by_id($resource->contextid, IGNORE_MISSING);
        if (!$context || $context->contextlevel != CONTEXT_COURSE) {
            mtrace("Skipping - resource '{$resource->id}' is not a course-level shared resource.");
            return [0, 0, 0];
        }

        $gradeitems = $this->get_activity_grade_items($resource->courseid);
        $itemcount = count($gradeitems);
        if (!$itemcount) {
            mtrace("Skipping - no gradable activity items found in course '{$resource->courseid}'.");
            return [0, 0, 0];
        }

        $users = $this->userrepo->find_by_resource($resource->id);
        if (!$users) {
            return [0, $itemcount, 0];
        }

        $usercount = 0;
        $sendcount = 0;
        foreach ($users as $user) {
            $usercount++;
            $userresourcelinks = $this->resourcelinkrepo->find_by_resource_and_user($resource->id, $user->get_id());
            foreach ($userresourcelinks as $rl) {
                if (!$rl->get_grade_service()) {
                    continue;
                }
                if (!$ags = $this->build_ags($rl)) {
                    mtrace("Skipping - could not build an AGS line-item service for resource link " .
                        "'{$rl->get_resourcelinkid()}' (no line items endpoint / registration).");
                    continue;
                }
                foreach ($gradeitems as $gi) {
                    if ($this->push_activity_grade($ags, $resource, $rl, $gi, $user)) {
                        $sendcount++;
                    }
                }
            }
        }

        return [$usercount, $itemcount, $sendcount];
    }

    /**
     * Fetch the gradable activity items of a course (one gradebook column each).
     *
     * Excludes the course total and category aggregates. Manual items are included only if configured.
     *
     * @param int $courseid
     * @return \grade_item[] keyed by grade item id
     */
    protected function get_activity_grade_items(int $courseid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $allowedtypes = get_config('local_ltiagssync', 'includemanual') ? ['mod', 'manual'] : ['mod'];
        $items = \grade_item::fetch_all(['courseid' => $courseid]) ?: [];

        $result = [];
        foreach ($items as $gi) {
            if (!in_array($gi->itemtype, $allowedtypes, true)) {
                continue;
            }
            // Only numeric or scale items carry a value AGS can transmit.
            if (!in_array((int) $gi->gradetype, [GRADE_TYPE_VALUE, GRADE_TYPE_SCALE], true)) {
                continue;
            }
            if (empty($gi->grademax) || (float) $gi->grademax <= 0) {
                continue;
            }
            $result[$gi->id] = $gi;
        }
        return $result;
    }

    /**
     * Build an AGS line-item service for a resource link, reusing enrol_lti's registration lookup.
     *
     * @param \enrol_lti\local\ltiadvantage\entity\resource_link $rl
     * @return LtiAssignmentsGradesService|null null when the link cannot manage line items.
     */
    protected function build_ags($rl): ?LtiAssignmentsGradesService {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $gradeservice = $rl->get_grade_service();
        $lineitemsurl = $gradeservice->get_lineitemsurl();
        if (empty($lineitemsurl)) {
            // Without a line items endpoint we cannot create additional (decoupled) columns.
            return null;
        }

        try {
            $appregistration = $this->appregistrationrepo->find_by_deployment($rl->get_deploymentid());
            $registration = $this->issuerdb->findRegistrationByIssuer(
                $appregistration->get_platformid()->out(false),
                $appregistration->get_clientid()
            );
        } catch (\Exception $e) {
            mtrace('Failed to resolve the platform registration: ' . $e->getMessage());
            return null;
        }

        $lineitemurl = $gradeservice->get_lineitemurl();
        $servicedata = [
            'lineitems' => $lineitemsurl->out(false),
            'lineitem' => $lineitemurl ? $lineitemurl->out(false) : null,
            'scope' => $gradeservice->get_scopes(),
        ];

        $sc = new LtiServiceConnector(new launch_cache_session(), new http_client());
        return new LtiAssignmentsGradesService($sc, $registration, $servicedata);
    }

    /**
     * Create/refresh the decoupled line item for one activity and push the user's grade to it.
     *
     * @param LtiAssignmentsGradesService $ags
     * @param \stdClass $resource enrol_lti_tools record.
     * @param \enrol_lti\local\ltiadvantage\entity\resource_link $rl
     * @param \grade_item $gi the activity grade item (one gradebook column).
     * @param \enrol_lti\local\ltiadvantage\entity\user $user
     * @return bool true if a grade was sent.
     */
    protected function push_activity_grade(LtiAssignmentsGradesService $ags, \stdClass $resource, $rl,
            \grade_item $gi, $user): bool {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $gradegrade = \grade_grade::fetch(['itemid' => $gi->id, 'userid' => $user->get_localid()]);
        if (!$gradegrade || $gradegrade->finalgrade === null || strlen((string) $gradegrade->finalgrade) < 1) {
            return false;
        }
        $finalgrade = (float) $gradegrade->finalgrade;
        $grademax = (float) $gi->grademax;
        if ($grademax <= 0) {
            return false;
        }

        // Skip if this exact grade was already delivered for this user/activity.
        $lastgrade = $this->staterepo->get_lastgrade($user->get_id(), $gi->id);
        if ($lastgrade !== null && !grade_floats_different($finalgrade, $lastgrade)) {
            return false;
        }

        $dategraded = $gradegrade->timemodified ?: time();
        $label = $gi->get_name(true);

        try {
            $lineitem = $ags->findOrCreateLineitem(new LtiLineitem([
                'label' => $label,
                'scoreMaximum' => $grademax,
                'tag' => 'activitygrade',
                'resourceId' => 'ltiagssync_gi_' . $gi->id,
                'resourceLinkId' => $rl->get_resourcelinkid(),
            ]));

            $ltigrade = LtiGrade::new()
                ->setScoreGiven($finalgrade)
                ->setScoreMaximum($grademax)
                ->setUserId($user->get_sourceid())
                ->setTimestamp(date(\DateTimeInterface::ISO8601, $dategraded))
                ->setActivityProgress('Completed')
                ->setGradingProgress('FullyGraded');

            $response = $ags->putGrade($ltigrade, $lineitem);
        } catch (\Exception $e) {
            mtrace("Failed - grade for user '{$user->get_localid()}' on item '{$gi->id}' ('{$label}'): " .
                $e->getMessage());
            return false;
        }

        if (!empty($response['status']) && in_array($response['status'], self::SUCCESS_STATUSES, true)) {
            $this->staterepo->set_lastgrade($resource->id, $user->get_id(), $gi->id, $finalgrade);
            mtrace("Sent - '{$label}' = {$finalgrade}/{$grademax} for platform user '{$user->get_sourceid()}'.");
            return true;
        }

        $status = $response['status'] ?? 'unknown';
        mtrace("Failed - grade for item '{$gi->id}' ('{$label}') returned status '{$status}'.");
        return false;
    }
}
