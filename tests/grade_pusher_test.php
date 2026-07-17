<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

namespace local_ltiagssync;

use grade_item;
use local_ltiagssync\local\grade_pusher;
use local_ltiagssync\local\state_repository;
use Packback\Lti1p3\LtiAssignmentsGradesService;

/**
 * Tests for the per-activity grade pusher.
 *
 * The AGS network calls are mocked; everything else (grade item enumeration, grade lookup,
 * change detection against the state table) exercises real Moodle grade APIs.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 * @covers     \local_ltiagssync\local\grade_pusher
 */
final class grade_pusher_test extends \advanced_testcase {

    /**
     * Invoke a protected method of grade_pusher via reflection.
     *
     * @param grade_pusher $object
     * @param string $method
     * @param array $args
     * @return mixed
     */
    protected function call_protected(grade_pusher $object, string $method, array $args) {
        $rm = new \ReflectionMethod(grade_pusher::class, $method);
        $rm->setAccessible(true);
        return $rm->invokeArgs($object, $args);
    }

    /**
     * A minimal resource-link stand-in exposing only what push_activity_grade uses.
     *
     * @return object
     */
    protected function fake_resource_link(): object {
        return new class {
            public function get_resourcelinkid(): string {
                return 'rl-1';
            }
        };
    }

    /**
     * A minimal LTI user stand-in exposing only what push_activity_grade uses.
     *
     * @param int $localid the local (provider) Moodle user id
     * @return object
     */
    protected function fake_lti_user(int $localid): object {
        return new class($localid) {
            /** @var int */
            private $localid;
            public function __construct(int $localid) {
                $this->localid = $localid;
            }
            public function get_localid(): int {
                return $this->localid;
            }
            public function get_id(): int {
                return 4242;
            }
            public function get_sourceid(): string {
                return 'sub-abc';
            }
        };
    }

    public function test_get_activity_grade_items_filters_by_type(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 50]);
        // A manual gradebook column - excluded unless includemanual is enabled.
        $manual = new grade_item(['courseid' => $course->id, 'itemtype' => 'manual',
            'itemname' => 'Bonus', 'grademax' => 20], false);
        $manual->insert();

        $pusher = new grade_pusher();

        $items = $this->call_protected($pusher, 'get_activity_grade_items', [$course->id]);
        $this->assertCount(2, $items, 'Only the two module items should be returned by default.');
        foreach ($items as $gi) {
            $this->assertSame('mod', $gi->itemtype);
        }

        set_config('includemanual', 1, 'local_ltiagssync');
        $items = $this->call_protected($pusher, 'get_activity_grade_items', [$course->id]);
        $this->assertCount(3, $items, 'Manual item should be included once the setting is on.');
    }

    public function test_push_activity_grade_sends_and_records_new_grade(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $gi = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->id, 'courseid' => $course->id]);
        $gi->update_final_grade($student->id, 80.0);

        // The pusher reports each successful send via mtrace(); declare that output is expected.
        $this->expectOutputRegex('/Sent -/');

        $ags = $this->createMock(LtiAssignmentsGradesService::class);
        $ags->method('findOrCreateLineitem')->willReturnArgument(0);
        $ags->expects($this->once())->method('putGrade')->willReturn(['status' => 200]);

        $resource = (object) ['id' => 77];
        $pusher = new grade_pusher();
        $sent = $this->call_protected($pusher, 'push_activity_grade',
            [$ags, $resource, $this->fake_resource_link(), $gi, $this->fake_lti_user((int) $student->id)]);

        $this->assertTrue($sent);
        $this->assertEqualsWithDelta(80.0,
            (new state_repository())->get_lastgrade(4242, (int) $gi->id), 0.00001,
            'The sent grade must be recorded in the state table.');
    }

    public function test_push_activity_grade_skips_unchanged_grade(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $gi = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->id, 'courseid' => $course->id]);
        $gi->update_final_grade($student->id, 80.0);

        // Pre-seed the state with the identical grade - the push must be skipped.
        (new state_repository())->set_lastgrade(77, 4242, (int) $gi->id, 80.0);

        $ags = $this->createMock(LtiAssignmentsGradesService::class);
        $ags->method('findOrCreateLineitem')->willReturnArgument(0);
        $ags->expects($this->never())->method('putGrade');

        $resource = (object) ['id' => 77];
        $pusher = new grade_pusher();
        $sent = $this->call_protected($pusher, 'push_activity_grade',
            [$ags, $resource, $this->fake_resource_link(), $gi, $this->fake_lti_user((int) $student->id)]);

        $this->assertFalse($sent);
    }
}
