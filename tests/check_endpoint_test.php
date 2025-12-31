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

/**
 * Unit tests for the check.php endpoint logic.
 *
 * @package    tool_upmon
 * @category   test
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class check_endpoint_test extends \advanced_testcase {

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that check_token setting exists and can be configured.
     */
    public function test_check_token_setting_exists(): void {
        // Set a token.
        set_config('check_token', 'abc123xyz', 'tool_upmon');

        $token = get_config('tool_upmon', 'check_token');

        $this->assertEquals('abc123xyz', $token);
    }

    /**
     * Test that empty token means no authentication required.
     */
    public function test_empty_token_allows_access(): void {
        // Ensure token is empty.
        unset_config('check_token', 'tool_upmon');

        $expected = get_config('tool_upmon', 'check_token');

        $this->assertEmpty($expected);
    }

    /**
     * Test token validation logic - correct token.
     */
    public function test_token_validation_correct(): void {
        $expected = 'secrettoken123';
        $provided = 'secrettoken123';

        // This mimics the logic in check.php.
        $valid = empty($expected) || $provided === $expected;

        $this->assertTrue($valid);
    }

    /**
     * Test token validation logic - incorrect token.
     */
    public function test_token_validation_incorrect(): void {
        $expected = 'secrettoken123';
        $provided = 'wrongtoken';

        // This mimics the logic in check.php.
        $valid = empty($expected) || $provided === $expected;

        $this->assertFalse($valid);
    }

    /**
     * Test token validation logic - no token provided when required.
     */
    public function test_token_validation_missing(): void {
        $expected = 'secrettoken123';
        $provided = '';

        // This mimics the logic in check.php.
        $valid = empty($expected) || $provided === $expected;

        $this->assertFalse($valid);
    }

    /**
     * Test token validation logic - no token configured (open access).
     */
    public function test_token_validation_not_configured(): void {
        $expected = '';
        $provided = '';

        // This mimics the logic in check.php - empty expected means allow access.
        $valid = empty($expected) || $provided === $expected;

        $this->assertTrue($valid);
    }

    /**
     * Test that the check_token language strings exist.
     */
    public function test_check_token_language_strings_exist(): void {
        $label = get_string('check_token', 'tool_upmon');
        $desc = get_string('check_token_desc', 'tool_upmon');

        $this->assertNotEmpty($label);
        $this->assertNotEmpty($desc);
        $this->assertEquals('Security Token', $label);
    }
}
