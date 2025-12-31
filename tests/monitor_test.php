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

namespace tool_upmon;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the monitor class.
 *
 * @package    tool_upmon
 * @category   test
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(monitor::class)]
final class monitor_test extends \advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test check_cron_health when cron has never run.
     */
    public function test_check_cron_health_never_run(): void {
        // Ensure no lastcronstart is set.
        unset_config('lastcronstart', 'tool_task');

        $result = monitor::check_cron_health();

        $this->assertIsString($result);
        $this->assertStringContainsString('never run', $result);
    }

    /**
     * Test check_cron_health when cron is healthy (ran recently).
     */
    public function test_check_cron_health_healthy(): void {
        // Set cron as having run 5 minutes ago.
        set_config('lastcronstart', time() - 300, 'tool_task');
        set_config('cron_threshold', 60, 'tool_upmon');

        $result = monitor::check_cron_health();

        $this->assertTrue($result);
    }

    /**
     * Test check_cron_health when cron threshold is exceeded.
     */
    public function test_check_cron_health_exceeded(): void {
        // Set cron as having run 120 minutes ago with 60 min threshold.
        set_config('lastcronstart', time() - 7200, 'tool_task');
        set_config('cron_threshold', 60, 'tool_upmon');

        $result = monitor::check_cron_health();

        $this->assertIsString($result);
        $this->assertStringContainsString('not run for', $result);
        $this->assertStringContainsString('120', $result);
    }

    /**
     * Test check_cron_health with custom threshold.
     */
    public function test_check_cron_health_custom_threshold(): void {
        // Cron ran 90 minutes ago, threshold is 120 minutes - should be healthy.
        set_config('lastcronstart', time() - 5400, 'tool_task');
        set_config('cron_threshold', 120, 'tool_upmon');

        $result = monitor::check_cron_health();

        $this->assertTrue($result);
    }

    /**
     * Test is_maintenance_mode when not in maintenance.
     */
    public function test_is_maintenance_mode_not_in_maintenance(): void {
        global $CFG;

        $CFG->maintenance_enabled = false;

        $result = monitor::is_maintenance_mode();

        $this->assertFalse($result);
    }

    /**
     * Test is_maintenance_mode with config-based maintenance.
     */
    public function test_is_maintenance_mode_config_enabled(): void {
        global $CFG;

        $CFG->maintenance_enabled = true;

        $result = monitor::is_maintenance_mode();

        $this->assertTrue($result);

        // Clean up.
        $CFG->maintenance_enabled = false;
    }

    /**
     * Test is_maintenance_mode with CLI maintenance file.
     */
    public function test_is_maintenance_mode_cli_file(): void {
        global $CFG;

        $CFG->maintenance_enabled = false;

        // Create the CLI maintenance file.
        $maintenancefile = $CFG->dataroot . '/climaintenance.html';
        file_put_contents($maintenancefile, '<html><body>Maintenance</body></html>');

        $result = monitor::is_maintenance_mode();

        $this->assertTrue($result);

        // Clean up.
        unlink($maintenancefile);
    }

    /**
     * Test check_maintenance_threshold_exceeded when not in maintenance.
     */
    public function test_check_maintenance_threshold_not_in_maintenance(): void {
        global $CFG;

        $CFG->maintenance_enabled = false;

        $result = monitor::check_maintenance_threshold_exceeded();

        $this->assertTrue($result);
    }

    /**
     * Test check_maintenance_threshold_exceeded when under threshold.
     */
    public function test_check_maintenance_threshold_under(): void {
        global $CFG;

        $CFG->maintenance_enabled = true;
        set_config('maintenance_threshold', 60, 'tool_upmon');

        // Clear any existing maintenance start time.
        unset_config('maintenance_start', 'tool_upmon');

        $result = monitor::check_maintenance_threshold_exceeded();

        // Just entered maintenance, should be OK.
        $this->assertTrue($result);

        // Verify maintenance_start was recorded.
        $start = get_config('tool_upmon', 'maintenance_start');
        $this->assertNotEmpty($start);

        // Clean up.
        $CFG->maintenance_enabled = false;
        unset_config('maintenance_start', 'tool_upmon');
    }

    /**
     * Test check_maintenance_threshold_exceeded when threshold exceeded.
     */
    public function test_check_maintenance_threshold_exceeded(): void {
        global $CFG;

        $CFG->maintenance_enabled = true;
        set_config('maintenance_threshold', 60, 'tool_upmon');

        // Set maintenance as having started 120 minutes ago.
        set_config('maintenance_start', time() - 7200, 'tool_upmon');

        $result = monitor::check_maintenance_threshold_exceeded();

        $this->assertIsString($result);
        $this->assertStringContainsString('maintenance mode for', $result);
        $this->assertStringContainsString('120', $result);

        // Clean up.
        $CFG->maintenance_enabled = false;
        unset_config('maintenance_start', 'tool_upmon');
    }

    /**
     * Test check_maintenance_reset clears tracker when exiting maintenance.
     */
    public function test_check_maintenance_reset(): void {
        global $CFG;

        // Set up as if we were in maintenance.
        set_config('maintenance_start', time() - 3600, 'tool_upmon');

        // But now we're out of maintenance.
        $CFG->maintenance_enabled = false;

        monitor::check_maintenance_reset();

        // The start time should be cleared.
        $start = get_config('tool_upmon', 'maintenance_start');
        $this->assertEmpty($start);
    }

    /**
     * Test run_health_checks when all checks pass.
     */
    public function test_run_health_checks_all_pass(): void {
        global $CFG;

        $CFG->maintenance_enabled = false;
        set_config('lastcronstart', time() - 300, 'tool_task');
        set_config('cron_threshold', 60, 'tool_upmon');
        set_config('enable_cron_alerts', 1, 'tool_upmon');
        set_config('enable_maintenance_alerts', 1, 'tool_upmon');

        $errors = monitor::run_health_checks();

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    /**
     * Test run_health_checks when cron check fails.
     */
    public function test_run_health_checks_cron_fails(): void {
        global $CFG;

        $CFG->maintenance_enabled = false;
        set_config('lastcronstart', time() - 7200, 'tool_task');
        set_config('cron_threshold', 60, 'tool_upmon');
        set_config('enable_cron_alerts', 1, 'tool_upmon');

        $errors = monitor::run_health_checks();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('cron', $errors);
    }

    /**
     * Test run_health_checks with cron alerts disabled.
     */
    public function test_run_health_checks_cron_disabled(): void {
        global $CFG;

        $CFG->maintenance_enabled = false;
        set_config('lastcronstart', time() - 7200, 'tool_task'); // Would fail if checked.
        set_config('cron_threshold', 60, 'tool_upmon');
        set_config('enable_cron_alerts', 0, 'tool_upmon'); // Disabled.

        $errors = monitor::run_health_checks();

        $this->assertIsArray($errors);
        $this->assertArrayNotHasKey('cron', $errors);
    }

    /**
     * Test run_health_checks with maintenance alerts disabled.
     */
    public function test_run_health_checks_maintenance_disabled(): void {
        global $CFG;

        $CFG->maintenance_enabled = true;
        set_config('maintenance_start', time() - 7200, 'tool_upmon'); // Would fail if checked.
        set_config('maintenance_threshold', 60, 'tool_upmon');
        set_config('enable_maintenance_alerts', 0, 'tool_upmon'); // Disabled.
        set_config('lastcronstart', time() - 300, 'tool_task');
        set_config('enable_cron_alerts', 0, 'tool_upmon');

        $errors = monitor::run_health_checks();

        $this->assertIsArray($errors);
        $this->assertArrayNotHasKey('maintenance', $errors);

        // Clean up.
        $CFG->maintenance_enabled = false;
        unset_config('maintenance_start', 'tool_upmon');
    }

    /**
     * Test check_core_health returns true when database and cache work.
     */
    public function test_check_core_health_healthy(): void {
        $result = monitor::check_core_health();

        $this->assertTrue($result);
    }
    /**
     * Test check_core_health returns error when database fails.
     */
    public function test_check_core_health_db_failure(): void {
        global $DB;
        
        // Back up original DB.
        $orig_db = $DB;
        
        try {
            // Mock DB to throw exception.
            $dbmock = $this->createMock(\moodle_database::class);
            $dbmock->method('get_field_sql')->willThrowException(new \Exception('Simulated DB Down'));
            $DB = $dbmock;
            
            $result = monitor::check_core_health();
            
            $this->assertIsString($result);
            $this->assertStringContainsString('Database connection failed', $result);
            $this->assertStringContainsString('Simulated DB Down', $result);
            
        } finally {
            // Restore DB.
            $DB = $orig_db;
        }
    }
}
