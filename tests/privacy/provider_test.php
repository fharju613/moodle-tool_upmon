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

namespace tool_upmon\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\tests\provider_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the privacy provider.
 *
 * @package    tool_upmon
 * @category   test
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {

    /**
     * Test that the provider implements null_provider.
     */
    public function test_null_provider(): void {
        $this->assertInstanceOf(
            \core_privacy\local\metadata\null_provider::class,
            new provider()
        );
    }

    /**
     * Test that get_reason returns the correct language string identifier.
     */
    public function test_get_reason(): void {
        $this->assertEquals('privacy:metadata', provider::get_reason());
    }

    /**
     * Test that the privacy:metadata string exists.
     */
    public function test_privacy_metadata_string_exists(): void {
        $reason = get_string(provider::get_reason(), 'tool_upmon');
        $this->assertNotEmpty($reason);
    }
}
