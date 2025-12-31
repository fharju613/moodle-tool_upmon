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
 * Plugin settings
 *
 * @package    tool_upmon
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_upmon', get_string('pluginname', 'tool_upmon'));
    $ADMIN->add('tools', $settings);

    // Enable/disable plugin.
    $settings->add(new admin_setting_configcheckbox(
        'tool_upmon/enable',
        new lang_string('enable', 'tool_upmon'),
        '',
        0
    ));

    // UptimeRobot API Key setting.
    $apikey_desc = new lang_string('apikey_desc', 'tool_upmon');
    $apikey_link = html_writer::link(\tool_upmon\uptimerobotapi::AFFILIATE_URL, get_string('getapikey', 'tool_upmon'), ['target' => '_blank']);
    
    $settings->add(new admin_setting_configpasswordunmask(
        'tool_upmon/apikey',
        new lang_string('apikey', 'tool_upmon'),
        $apikey_desc . '<br>' . $apikey_link,
        ''
    ));

    // Monitor Configuration - custom setting with dropdown, friendly name, and type.
    $settings->add(new \tool_upmon\admin_setting_configmonitor(
        'tool_upmon/monitor_id',
        get_string('managemonitor', 'tool_upmon'),
        ''
    ));



    // Alert Configuration Header.
    $settings->add(new admin_setting_heading(
        'tool_upmon/alertconfig',
        new lang_string('alertconfig', 'tool_upmon'),
        ''
    ));

    // Cron Monitoring settings.
    $settings->add(new admin_setting_heading(
        'tool_upmon/cronmonitoring',
        new lang_string('cronmonitoring', 'tool_upmon'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_upmon/enable_cron_alerts',
        new lang_string('enable_cron_alerts', 'tool_upmon'),
        '',
        0
    ));

    $settings->add(new admin_setting_configtext(
        'tool_upmon/cron_threshold',
        new lang_string('cron_threshold', 'tool_upmon'),
        new lang_string('cron_threshold_desc', 'tool_upmon'),
        60,
        PARAM_INT
    ));

    // Maintenance Mode Monitoring settings.
    $settings->add(new admin_setting_heading(
        'tool_upmon/maintenancemonitoring',
        new lang_string('maintenancemonitoring', 'tool_upmon'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tool_upmon/enable_maintenance_alerts',
        new lang_string('enable_maintenance_alerts', 'tool_upmon'),
        '',
        0
    ));

    $settings->add(new admin_setting_configtext(
        'tool_upmon/maintenance_threshold',
        new lang_string('maintenance_threshold', 'tool_upmon'),
        new lang_string('maintenance_threshold_desc', 'tool_upmon'),
        60,
        PARAM_INT
    ));
}
