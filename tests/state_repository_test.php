<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

namespace local_ltiagssync;

use local_ltiagssync\local\state_repository;

/**
 * Tests for the per-activity grade sync state repository.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 * @covers     \local_ltiagssync\local\state_repository
 */
final class state_repository_test extends \advanced_testcase {

    public function test_get_returns_null_when_absent(): void {
        $this->resetAfterTest();
        $repo = new state_repository();
        $this->assertNull($repo->get_lastgrade(1, 2));
    }

    public function test_set_then_get(): void {
        $this->resetAfterTest();
        $repo = new state_repository();
        $repo->set_lastgrade(5, 10, 20, 85.5);
        $this->assertEqualsWithDelta(85.5, $repo->get_lastgrade(10, 20), 0.00001);
    }

    public function test_set_updates_existing_row_in_place(): void {
        global $DB;
        $this->resetAfterTest();
        $repo = new state_repository();
        $repo->set_lastgrade(5, 10, 20, 85.5);
        $repo->set_lastgrade(5, 10, 20, 92.0);
        $this->assertEqualsWithDelta(92.0, $repo->get_lastgrade(10, 20), 0.00001);
        $this->assertEquals(1, $DB->count_records('local_ltiagssync_state',
            ['ltiuserid' => 10, 'gradeitemid' => 20]));
    }

    public function test_state_is_keyed_per_user_and_item(): void {
        $this->resetAfterTest();
        $repo = new state_repository();
        $repo->set_lastgrade(5, 10, 20, 85.5);
        $this->assertNull($repo->get_lastgrade(10, 21), 'Different activity must be independent.');
        $this->assertNull($repo->get_lastgrade(11, 20), 'Different user must be independent.');
    }
}
