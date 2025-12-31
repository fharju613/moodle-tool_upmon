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
 * Tests for the uptimerobotapi class (API v3).
 *
 * @package    tool_upmon
 * @category   test
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(uptimerobotapi::class)]
final class uptimerobotapi_test extends \advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test get_monitors returns false when no API key is configured.
     */
    public function test_get_monitors_no_api_key(): void {
        // Ensure no API key is set.
        unset_config('apikey', 'tool_upmon');

        $result = uptimerobotapi::get_monitors();

        $this->assertFalse($result);
    }

    /**
     * Test get_monitors with successful API v3 response.
     */
    public function test_get_monitors_success(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        // v3 API returns data in 'data' key.
        $mockresponse = json_encode([
            'data' => [
                ['id' => 123, 'friendly_name' => 'Test Monitor', 'status' => 'UP', 'type' => 'KEYWORD'],
                ['id' => 456, 'friendly_name' => 'Another Monitor', 'status' => 'UP', 'type' => 'HTTP'],
            ],
        ]);

        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn($mockresponse);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::get_monitors();

        $this->assertIsArray($result);
        $this->assertEquals('ok', $result['stat']);
        $this->assertCount(2, $result['monitors']);
        $this->assertEquals('Test Monitor', $result['monitors'][0]['friendly_name']);
    }

    /**
     * Test get_monitors handles cURL error.
     */
    public function test_get_monitors_curl_error(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn('');
        $curl->method('get_errno')->willReturn(28); // CURLE_OPERATION_TIMEDOUT
        $curl->error = 'Connection timed out';
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::get_monitors();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('cURL Error', $result['error']['message']);
    }

    /**
     * Test get_monitors handles invalid JSON response.
     */
    public function test_get_monitors_invalid_json(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn('This is not valid JSON');
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::get_monitors();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid JSON', $result['error']['message']);
    }

    /**
     * Test get_monitors handles API error response.
     */
    public function test_get_monitors_api_error(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        $mockresponse = json_encode([
            'error' => [
                'type' => 'invalid_parameter',
                'message' => 'api_key is invalid',
            ],
        ]);

        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn($mockresponse);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::get_monitors();

        $this->assertIsArray($result);
        $this->assertEquals('fail', $result['stat']);
        $this->assertEquals('api_key is invalid', $result['error']['message']);
    }

    /**
     * Test get_account_details with successful response.
     */
    public function test_get_account_details_success(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        // v3 API returns data in 'data' key.
        $mockresponse = json_encode([
            'data' => [
                'email' => 'test@example.com',
                'monitor_limit' => 50,
                'monitor_interval' => 5,
            ],
        ]);

        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn($mockresponse);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::get_account_details();

        $this->assertIsArray($result);
        $this->assertEquals('ok', $result['stat']);
        $this->assertEquals('test@example.com', $result['account']['email']);
    }

    /**
     * Test get_monitor returns single monitor.
     */
    public function test_get_monitor_success(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        // v3 API for single monitor returns data directly.
        $mockresponse = json_encode([
            'data' => ['id' => 123, 'friendly_name' => 'Test Monitor', 'status' => 'UP', 'type' => 'KEYWORD'],
        ]);

        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn($mockresponse);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::get_monitor(123);

        $this->assertIsArray($result);
        $this->assertEquals(123, $result['id']);
        $this->assertEquals('Test Monitor', $result['friendly_name']);
    }

    /**
     * Test get_monitor returns false when monitor not found.
     */
    public function test_get_monitor_not_found(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        $mockresponse = json_encode([
            'error' => ['message' => 'Monitor not found'],
        ]);

        $curl = $this->createMock(\curl::class);
        $curl->method('get')->willReturn($mockresponse);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::get_monitor(999);

        $this->assertFalse($result);
    }

    /**
     * Test create_monitor with KEYWORD type.
     */
    public function test_create_monitor_keyword(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        // v3 API returns monitor data directly for POST.
        $mockresponse = json_encode(['id' => 790, 'friendly_name' => 'Keyword Monitor', 'type' => 'KEYWORD']);

        $curl = $this->createMock(\curl::class);
        $curl->method('post')->willReturn($mockresponse);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::create_monitor('Keyword Monitor', 'https://example.com/check.php', 
                                                  uptimerobotapi::TYPE_KEYWORD, 'upmon PASSES', 'ALERT_EXISTS');

        $this->assertIsArray($result);
        $this->assertEquals('ok', $result['stat']);
        $this->assertEquals(790, $result['monitor']['id']);
    }

    /**
     * Test delete_monitor.
     */
    public function test_delete_monitor(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        $mockresponse = json_encode(['id' => 123]);

        $curl = $this->createMock(\curl::class);
        $curl->method('delete')->willReturn($mockresponse);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::delete_monitor(123);

        $this->assertIsArray($result);
        $this->assertEquals('ok', $result['stat']);
    }

    /**
     * Test edit_monitor.
     */
    public function test_edit_monitor(): void {
        set_config('apikey', 'test-api-key', 'tool_upmon');

        // v3 API returns updated monitor in 'data' key.
        $mockresponse = json_encode([
            'data' => ['id' => 123, 'friendly_name' => 'Updated Name'],
        ]);

        $curl = $this->createMock(\curl::class);
        $curl->method('post')->willReturn($mockresponse);
        $curl->method('setopt')->willReturn(true);
        $curl->method('get_errno')->willReturn(0);
        $curl->method('setHeader')->willReturn(true);

        \core\di::set(\curl::class, $curl);

        $result = uptimerobotapi::edit_monitor(123, ['friendly_name' => 'Updated Name']);

        $this->assertIsArray($result);
        $this->assertEquals('ok', $result['stat']);
        $this->assertEquals('Updated Name', $result['monitor']['friendly_name']);
    }

    /**
     * Test get_full_heartbeat_url.
     */
    public function test_get_full_heartbeat_url(): void {
        // Test with full URL (already correct).
        $monitor = ['id' => 123, 'url' => 'https://heartbeat.uptimerobot.com/m123-token'];
        $this->assertEquals('https://heartbeat.uptimerobot.com/m123-token', uptimerobotapi::get_full_heartbeat_url($monitor));

        // Test with token only (needs construction).
        $monitor = ['id' => 456, 'url' => 'token123'];
        $this->assertEquals('https://heartbeat.uptimerobot.com/m456-token123', uptimerobotapi::get_full_heartbeat_url($monitor));

        // Test with empty URL.
        $monitor = ['id' => 789, 'url' => ''];
        $this->assertEquals('', uptimerobotapi::get_full_heartbeat_url($monitor));
    }

    /**
     * Test type constants are defined correctly.
     */
    public function test_type_constants(): void {
        $this->assertEquals('KEYWORD', uptimerobotapi::TYPE_KEYWORD);
        $this->assertEquals('HEARTBEAT', uptimerobotapi::TYPE_HEARTBEAT);
    }
}
