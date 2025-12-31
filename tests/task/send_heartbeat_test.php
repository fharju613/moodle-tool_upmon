<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_upmon\task;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the send_heartbeat scheduled task.
 *
 * @package    tool_upmon
 * @category   test
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(send_heartbeat::class)]
final class send_heartbeat_test extends \advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test task get_name returns the correct name.
     */
    public function test_get_name(): void {
        $task = new send_heartbeat();

        $name = $task->get_name();

        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    /**
     * Test execute returns early when plugin is disabled.
     */
    public function test_execute_plugin_disabled(): void {
        global $CFG;

        set_config('enable', 0, 'tool_upmon');

        $task = new send_heartbeat();

        // Capture mtrace output.
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('disabled', $output);
    }

    /**
     * Test execute returns early when no heartbeat URL is configured.
     */
    public function test_execute_no_heartbeat_url(): void {
        set_config('enable', 1, 'tool_upmon');
        unset_config('heartbeat_url', 'tool_upmon');
        set_config('monitor_type', 'heartbeat', 'tool_upmon');

        $task = new send_heartbeat();

        // Capture mtrace output.
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('No heartbeat URL', $output);
    }

    /**
     * Test execute runs health checks and reports failures.
     */
    public function test_execute_health_checks_fail(): void {
        global $CFG;

        set_config('enable', 1, 'tool_upmon');
        set_config('heartbeat_url', 'https://heartbeat.example.com/1234', 'tool_upmon');
        set_config('monitor_type', 'heartbeat', 'tool_upmon');

        // Set up failing cron check.
        set_config('lastcronstart', time() - 7200, 'tool_task'); // 2 hours ago.
        set_config('cron_threshold', 60, 'tool_upmon');
        set_config('enable_cron_alerts', 1, 'tool_upmon');

        $CFG->maintenance_enabled = false;

        $task = new send_heartbeat();

        // Capture mtrace output.
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('Health check failed', $output);
        $this->assertStringContainsString('cron', $output);
        $this->assertStringContainsString('Skipping heartbeat ping', $output);
    }

    /**
     * Test execute pings heartbeat when all checks pass.
     */
    public function test_execute_health_checks_pass(): void {
        global $CFG;

        set_config('enable', 1, 'tool_upmon');
        set_config('heartbeat_url', 'https://heartbeat.uptimerobot.com/test123', 'tool_upmon');
        set_config('monitor_type', 'heartbeat', 'tool_upmon');

        // Set up passing cron check.
        set_config('lastcronstart', time() - 300, 'tool_task'); // 5 minutes ago.
        set_config('cron_threshold', 60, 'tool_upmon');
        set_config('enable_cron_alerts', 1, 'tool_upmon');

        $CFG->maintenance_enabled = false;

        // Mock the curl client using DI.
        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn('OK');
        $curl->method('get_info')->willReturn(['http_code' => 200]);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setopt')->willReturn(true);
        \core\di::set(\curl::class, $curl);

        $task = new send_heartbeat();

        // Capture mtrace output.
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('All health checks passed', $output);
        $this->assertStringContainsString('Pinging heartbeat', $output);
        $this->assertStringContainsString('Heartbeat ping successful', $output);
    }
}
