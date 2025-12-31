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

/**
 * Scheduled task for heartbeat monitoring.
 *
 * This task runs periodically and, if the site is healthy (cron running and
 * not in extended maintenance), pings the UptimeRobot heartbeat URL to confirm
 * the site is operational. If the heartbeat URL is not pinged, UptimeRobot
 * will mark the monitor as down after the configured timeout.
 *
 * @package    tool_upmon
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_heartbeat extends \core\task\scheduled_task {

    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksendheartbeat', 'tool_upmon');
    }

    /**
     * Execute the heartbeat check.
     *
     * Runs health checks and pings the UptimeRobot heartbeat URL if all checks pass.
     */
    public function execute(): void {
        // Check if plugin is enabled.
        if (!get_config('tool_upmon', 'enable')) {
            mtrace('Uptime Monitor plugin is disabled.');
            return;
        }

        // Check if monitor type is set to heartbeat.
        $monitortype = get_config('tool_upmon', 'monitor_type');
        if ($monitortype !== 'heartbeat') {
            mtrace('Uptime Monitor: configured for ' . (!empty($monitortype) ? $monitortype : 'unknown') . ' monitoring. Skipping heartbeat ping.');
            return;
        }

        // Get heartbeat URL - required for this task to do anything useful.
        $heartbeaturl = get_config('tool_upmon', 'heartbeat_url');
        if (empty($heartbeaturl)) {
            mtrace('Uptime Monitor: No heartbeat URL configured. This task only works with Heartbeat (Push) monitors.');
            return;
        }

        // Run health checks using shared method.
        $errors = \tool_upmon\monitor::run_health_checks();

        if (empty($errors)) {
            mtrace("All health checks passed. Pinging heartbeat URL...");
            $this->ping_heartbeat($heartbeaturl);
        } else {
            foreach ($errors as $type => $message) {
                mtrace("Health check failed - {$type}: {$message}");
            }
            mtrace("Skipping heartbeat ping - UptimeRobot will detect the outage.");
        }
    }

    /**
     * Ping the UptimeRobot heartbeat URL.
     *
     * @param string $url The heartbeat URL to ping
     */
    private function ping_heartbeat(string $url): void {
        // Use Moodle's DI container to get curl client (allows mocking in tests).
        $curl = \core\di::get(\curl::class);
        $curl->setopt(['CURLOPT_TIMEOUT' => 30]);

        $response = $curl->get($url);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if ($curl->get_errno()) {
            mtrace("Heartbeat ping failed: " . $curl->error);
        } else if ($httpcode >= 200 && $httpcode < 300) {
            mtrace("Heartbeat ping successful (HTTP $httpcode).");
        } else {
            mtrace("Heartbeat ping returned unexpected status: HTTP $httpcode");
        }
    }
}
