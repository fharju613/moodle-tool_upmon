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
 * Tests for the admin_setting_configmonitor class.
 *
 * @package    tool_upmon
 * @category   test
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(admin_setting_configmonitor::class)]
final class admin_setting_configmonitor_test extends \advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test write_setting: Clear configuration.
     */
    public function test_write_setting_clear(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');
        set_config('monitor_id', 123, 'tool_upmon');

        $setting = new admin_setting_configmonitor('tool_upmon/monitor_id', 'Monitor', 'Desc');
        
        // Pass empty data to clear.
        $result = $setting->write_setting(['monitor_id' => '']);
        
        $this->assertEmpty($result); // Empty string means success.
        $this->assertEmpty(get_config('tool_upmon', 'monitor_id'));
        $this->assertEmpty(get_config('tool_upmon', 'monitor_type'));
    }

    /**
     * Test write_setting: Create new monitor.
     */
    public function test_write_setting_create_new(): void {
        global $CFG;
        set_config('apikey', 'test-api-key', 'tool_upmon');
        $CFG->wwwroot = 'https://moodle.example.com';

        // Mock API response for create.
        // uptimerobotapi::create_monitor expects POST request
        $curl = $this->createMock(\curl::class);
        $mock_create_response = json_encode(['id' => 999, 'friendly_name' => 'New Monitor', 'type' => 'KEYWORD', 'url' => 'http://example.com']);
        $curl->method('post')->willReturn($mock_create_response);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);
        \core\di::set(\curl::class, $curl);

        $setting = new admin_setting_configmonitor('tool_upmon/monitor_id', 'Monitor', 'Desc');
        
        $data = [
            'monitor_id' => 'new',
            'friendly_name' => 'New Monitor',
            'type' => 'KEYWORD',
            'check_token' => 'abc'
        ];
        
        $result = $setting->write_setting($data);
        
        $this->assertEmpty($result);
        $this->assertEquals(999, get_config('tool_upmon', 'monitor_id'));
        $this->assertEquals('keyword', get_config('tool_upmon', 'monitor_type'));
        $this->assertEquals('abc', get_config('tool_upmon', 'check_token'));
    }

    /**
     * Test write_setting: Link existing monitor.
     */
    public function test_write_setting_link_existing(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        // Mock API response for get_monitor.
        $curl = $this->createMock(\curl::class);
        // get_monitor calls GET request.
        $mock_get_response = json_encode(['id' => 555, 'friendly_name' => 'Existing Monitor', 'type' => 'HEARTBEAT', 'url' => 'token555']);
        $curl->method('get')->willReturn($mock_get_response);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);
        \core\di::set(\curl::class, $curl);

        $setting = new admin_setting_configmonitor('tool_upmon/monitor_id', 'Monitor', 'Desc');
        
        $data = [
            'monitor_id' => 555,
            'friendly_name' => 'Existing Monitor',
            'type' => 'HEARTBEAT'
        ];
        
        $result = $setting->write_setting($data);
        
        $this->assertEmpty($result);
        $this->assertEquals(555, get_config('tool_upmon', 'monitor_id'));
        $this->assertEquals('heartbeat', get_config('tool_upmon', 'monitor_type'));
        // Verify full heartbeat URL construction
        $this->assertEquals('https://heartbeat.uptimerobot.com/m555-token555', get_config('tool_upmon', 'heartbeat_url'));
    }
}
