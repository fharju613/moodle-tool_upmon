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
 * Strings for component 'tool_upmon'.
 *
 * @package    tool_upmon
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Uptime Monitor';
$string['privacy:metadata'] = 'The Uptime Monitor plugin does not store any personal data.';
$string['enable'] = 'Enable Uptime Monitor';
$string['apikey'] = 'UptimeRobot API Key';
$string['apikey_desc'] = 'Enter your UptimeRobot API key. You can get this from your UptimeRobot account settings under "Integrations & API > Main API key".';
$string['getapikey'] = 'Get your free API key from UptimeRobot';
$string['alertconfig'] = 'Alert Configuration';
$string['cronmonitoring'] = 'Cron Monitoring';
$string['enable_cron_alerts'] = 'Enable Cron Alerts';
$string['cron_threshold'] = 'Cron Alert Threshold (minutes)';
$string['cron_threshold_desc'] = 'Fail if cron hasn\'t run for this many minutes (default: 60)';
$string['maintenancemonitoring'] = 'Maintenance Mode Monitoring';
$string['enable_maintenance_alerts'] = 'Enable Maintenance Mode Alerts';
$string['maintenance_threshold'] = 'Maintenance Mode Threshold (minutes)';
$string['maintenance_threshold_desc'] = 'Fail if site remains in maintenance for this many minutes (default: 60)';
$string['noapikey'] = 'No API Key configured. Please configure it in the settings.';
$string['tasksendheartbeat'] = 'Send UptimeRobot Heartbeat';
$string['managemonitor'] = 'Manage UptimeRobot Monitor';
$string['selectmonitor'] = 'Select an existing monitor';
$string['createnewmonitor'] = 'Create a new monitor...';
$string['monitor'] = 'Monitor';
$string['friendlyname'] = 'Friendly Name';
$string['monitortype'] = 'Monitor Type';
$string['method_keyword'] = 'Keyword (Poll)';
$string['method_heartbeat'] = 'Heartbeat (Push)';
$string['pushpaidonly'] = 'Heartbeat (Push) monitoring requires a paid UptimeRobot plan.';
$string['upgradetopaid'] = 'Upgrade to a paid plan to unlock Push monitoring';
$string['check_token'] = 'Security Token';
$string['check_token_desc'] = 'Optional security token for the health check endpoint. If set, the check.php URL must include ?token=YOUR_TOKEN to access it. Leave empty for open access.';
$string['currentmonitor'] = 'Currently Linked Monitor ID';
$string['typechangeinfo'] = 'You cannot change the type of an existing monitor. Please create a new monitor instead.';
$string['monitorcreationfailed'] = 'Failed to create monitor';
$string['invalidtoken'] = 'Security token must contain only letters and numbers (alphanumeric).';
$string['friendlynametoolong'] = 'Friendly name must be 250 characters or less.';
$string['friendlynamerequired'] = 'Friendly name is required.';
