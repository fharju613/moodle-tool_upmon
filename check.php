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

/**
 * Health check endpoint for UptimeRobot polling.
 *
 * @package    tool_upmon
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../../config.php');

// Check if plugin is enabled.
if (!get_config('tool_upmon', 'enable')) {
    http_response_code(404);
    die('Uptime Monitor disabled');
}

// Check optional security token.
$expected_token = get_config('tool_upmon', 'check_token');
if (!empty($expected_token)) {
    $provided_token = optional_param('token', '', PARAM_ALPHANUM);
    if ($provided_token !== $expected_token) {
        http_response_code(403);
        die('Forbidden');
    }
}

// Run health checks.
$errors = \tool_upmon\monitor::run_health_checks();

if (!empty($errors)) {
    // Return first error with appropriate HTTP code.
    $code = isset($errors['maintenance']) ? 503 : 500;
    http_response_code($code);
    die(implode('; ', $errors));
}

http_response_code(200);
echo \tool_upmon\uptimerobotapi::KEYWORD;
