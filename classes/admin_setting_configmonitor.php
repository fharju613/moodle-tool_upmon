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

defined('MOODLE_INTERNAL') || die();

/**
 * Custom admin setting for UptimeRobot monitor configuration.
 *
 * @package    tool_upmon
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_configmonitor extends \admin_setting {

    /**
     * Constructor.
     *
     * @param string $name unique name
     * @param string $visiblename localised name
     * @param string $description long localised description
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * Get the current setting value.
     *
     * Returns the current value, or empty string if not yet configured.
     *
     * @return string
     */
    public function get_setting() {
        $value = $this->config_read($this->name);
        return $value ?? '';
    }

    /**
     * Get the default setting value.
     *
     * Returns empty string (no monitor selected) as the default.
     *
     * @return string
     */
    public function get_defaultsetting() {
        return '';
    }

    /**
     * Build the check.php URL with optional security token.
     *
     * @param string $token Optional security token to append
     * @return string The full check URL
     */
    private function get_check_url(string $token = ''): string {
        global $CFG;
        $url = $CFG->wwwroot . '/admin/tool/upmon/check.php';
        if (!empty($token)) {
            $url .= '?token=' . $token;
        }
        return $url;
    }

    /**
     * Store new setting value.
     *
     * @param mixed $data string or array, must not be null
     * @return string empty string if ok, error message if error
     */
    public function write_setting($data) {
        global $CFG;

        // Clean monitor_id as INT if it's an existing monitor ID (not 'new' or empty).
        $monitor_id = $data['monitor_id'] ?? '';
        if ($monitor_id !== '' && $monitor_id !== 'new') {
            $monitor_id = clean_param($monitor_id, PARAM_INT);
        }
        
        // If nothing selected, clear config and return early (skip other validations).
        if (empty($monitor_id)) {
            $this->config_write($this->name, '');
            unset_config('monitor_type', 'tool_upmon');
            unset_config('friendly_name', 'tool_upmon');
            unset_config('heartbeat_url', 'tool_upmon');
            return '';
        }
        
        // Validate and clean friendly_name - required, must be alphanumext and max 250 chars.
        $friendly_name = $data['friendly_name'] ?? '';
        $friendly_name = clean_param($friendly_name, PARAM_ALPHANUMEXT);
        if (empty($friendly_name)) {
            return get_string('friendlynamerequired', 'tool_upmon');
        }
        if (strlen($friendly_name) > 250) {
            return get_string('friendlynametoolong', 'tool_upmon');
        }
        
        // Validate and clean check_token - must be alphanumeric only.
        $check_token = $data['check_token'] ?? '';
        $cleaned_token = clean_param($check_token, PARAM_ALPHANUM);
        if ($cleaned_token !== $check_token) {
            return get_string('invalidtoken', 'tool_upmon');
        }
        
        // Always save check token locally if provided (or cleared).
        // Only save if we are submitting the form (data is present).
        if (isset($data['check_token'])) {
             set_config('check_token', $cleaned_token, 'tool_upmon');
        }

        // Clean type parameter.
        $type = $data['type'] ?? uptimerobotapi::TYPE_KEYWORD;
        $type = clean_param($type, PARAM_ALPHA);
        if (!in_array($type, [uptimerobotapi::TYPE_KEYWORD, uptimerobotapi::TYPE_HEARTBEAT])) {
            return get_string('invalidtype', 'tool_upmon');
        }

        if ($monitor_id === 'new') {
            // Create new monitor via API.
            $url = $this->get_check_url($cleaned_token);
            
            $res = uptimerobotapi::create_monitor($friendly_name, $url, $type, uptimerobotapi::KEYWORD, 'ALERT_NOT_EXISTS');
            
            if ($res && isset($res['stat']) && $res['stat'] === 'ok') {
                $monitor = $res['monitor'];
                $this->config_write($this->name, $monitor['id']);
                set_config('monitor_type', strtolower($type), 'tool_upmon');
                set_config('friendly_name', $friendly_name, 'tool_upmon');
                
                if (strtoupper($type) === uptimerobotapi::TYPE_HEARTBEAT && !empty($monitor['url'])) {
                    set_config('heartbeat_url', uptimerobotapi::get_full_heartbeat_url($monitor), 'tool_upmon');
                } else {
                    unset_config('heartbeat_url', 'tool_upmon');
                }
                return '';
            } else {
                $error = $res['error']['message'] ?? 'Unknown error';
                return get_string('monitorcreationfailed', 'tool_upmon') . ': ' . $error;
            }
        } else if (!empty($monitor_id)) {
            // Link existing monitor.
            $this->config_write($this->name, $monitor_id);
            
            // Fetch monitor details.
            $m = uptimerobotapi::get_monitor($monitor_id);
            if ($m && isset($m['type'])) {
                $type_str = 'keyword';
                $monitor_type = strtoupper($m['type'] ?? '');
                $requested_type = strtoupper($type);
                
                $needs_update = false;
                $update_params = [];
                
                // Check if friendly name changed.
                $current_name = $m['friendlyName'] ?? $m['friendly_name'] ?? '';
                if (!empty($friendly_name) && $friendly_name !== $current_name) {
                    $update_params['friendlyName'] = $friendly_name;
                    $needs_update = true;
                }
                
                if ($monitor_type === uptimerobotapi::TYPE_HEARTBEAT) {
                    $type_str = 'heartbeat';
                    set_config('heartbeat_url', uptimerobotapi::get_full_heartbeat_url($m), 'tool_upmon');
                } else {
                    unset_config('heartbeat_url', 'tool_upmon');
                    
                    // Keyword monitor - update URL and keyword to match our check.php.
                    $expected_url = $this->get_check_url($cleaned_token);
                    
                    if (($m['url'] ?? '') !== $expected_url) {
                        $update_params['url'] = $expected_url;
                        $needs_update = true;
                    }
                    if (($m['keywordValue'] ?? '') !== uptimerobotapi::KEYWORD) {
                        $update_params['keywordValue'] = uptimerobotapi::KEYWORD;
                        $update_params['keywordType'] = 'ALERT_NOT_EXISTS';
                        $needs_update = true;
                    }
                }
                
                if ($needs_update) {
                    uptimerobotapi::edit_monitor($monitor_id, $update_params);
                }
                
                set_config('monitor_type', $type_str, 'tool_upmon');
                // Use the new name if provided, otherwise use the current name from API.
                set_config('friendly_name', $friendly_name ?: $current_name, 'tool_upmon');
            }
            return '';
        }
    }

    /**
     * Return XHTML for the setting.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $CFG, $SITE, $OUTPUT;

        $default = $this->get_defaultsetting();
        $current = $this->get_setting();

        // Check if API key is configured.
        $apikey = get_config('tool_upmon', 'apikey');
        if (empty($apikey)) {
            $html = \html_writer::div(
                get_string('noapikey', 'tool_upmon'),
                'alert alert-warning'
            );
            return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', $default, $query);
        }

        // Validate that the currently linked monitor still exists.
        if (!empty($current)) {
            $linked_monitor = uptimerobotapi::get_monitor((int)$current);
            if (!$linked_monitor) {
                // Monitor was deleted in UptimeRobot - clear local config.
                $this->config_write($this->name, '');
                unset_config('monitor_type', 'tool_upmon');
                unset_config('friendly_name', 'tool_upmon');
                unset_config('heartbeat_url', 'tool_upmon');
                $current = '';
            }
        }

        // Fetch account details to check if paid plan.
        $is_paid = false;
        $account = uptimerobotapi::get_account_details();
        if ($account && isset($account['stat']) && $account['stat'] === 'ok' && isset($account['account'])) {
            $is_paid = ($account['account']['monitor_interval'] == 1);
        }

        // Fetch monitors.
        $monitors = [];
        $matching_monitor_id = null;
        $response = uptimerobotapi::get_monitors();
        if ($response && isset($response['stat']) && $response['stat'] === 'ok' && !empty($response['monitors'])) {
            // Filter to only compatible monitor types (KEYWORD and HEARTBEAT).
            $all_monitors = $response['monitors'];
            foreach ($all_monitors as $m) {
                $type = strtoupper($m['type'] ?? '');
                if ($type === uptimerobotapi::TYPE_KEYWORD || $type === uptimerobotapi::TYPE_HEARTBEAT) {
                    $monitors[] = $m;
                }
            }
            
            // Find matching monitor - check if monitor URL starts with site URL.
            $siteurl = rtrim($CFG->wwwroot, '/');
            $checkurl = $siteurl . '/admin/tool/upmon/check.php';
            foreach ($monitors as $m) {
                $monitor_url = $m['url'] ?? '';
                // Remove query string for comparison.
                $monitor_url_base = strtok($monitor_url, '?');
                if ($monitor_url_base === $checkurl) {
                    $matching_monitor_id = $m['id'];
                    break;
                }
            }
        }

        // Build element IDs.
        $id = $this->get_id();
        $fullname = $this->get_full_name();

        // Build dropdown options.
        $options = [
            '' => get_string('selectmonitor', 'tool_upmon'),
            'new' => get_string('createnewmonitor', 'tool_upmon')
        ];
        foreach ($monitors as $m) {
            // Handle both camelCase (v3) and snake_case field names.
            $label = $m['friendlyName'] ?? $m['friendly_name'] ?? 'Monitor ' . $m['id'];
            $options[$m['id']] = $label;
        }

        // Current values.
        $selected_monitor = $current ?: ($matching_monitor_id ?: '');
        $current_friendly = get_config('tool_upmon', 'friendly_name') ?: $SITE->fullname;
        $current_type = get_config('tool_upmon', 'monitor_type');
        $current_type_val = ($current_type === 'heartbeat') ? 'HEARTBEAT' : 'KEYWORD';

        // Build HTML.
        $html = '';

        // Monitor dropdown - remove inline onchange, AMD module handles it.
        $select_attrs = ['id' => $id, 'class' => 'form-control'];
        $html .= \html_writer::start_div('form-group row mb-3');
        $html .= \html_writer::tag('label', get_string('monitor', 'tool_upmon'), ['for' => $id, 'class' => 'col-sm-3 col-form-label']);
        $html .= \html_writer::start_div('col-sm-9');
        $html .= \html_writer::select($options, $fullname . '[monitor_id]', $selected_monitor, false, $select_attrs);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        // Friendly name field.
        $html .= \html_writer::start_div('form-group row mb-3', ['id' => $id . '_friendlyname_row']);
        $html .= \html_writer::tag('label', get_string('friendlyname', 'tool_upmon'), ['for' => $id . '_friendlyname', 'class' => 'col-sm-3 col-form-label']);
        $html .= \html_writer::start_div('col-sm-9');
        $input_attrs = [
            'type' => 'text',
            'id' => $id . '_friendlyname',
            'name' => $fullname . '[friendly_name]',
            'value' => $current_friendly,
            'class' => 'form-control'
        ];
        // Only disable if no monitor is selected.
        if (empty($selected_monitor)) {
            $input_attrs['disabled'] = 'disabled';
        }
        $html .= \html_writer::empty_tag('input', $input_attrs);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        // Type radio buttons.
        $html .= \html_writer::start_div('form-group row mb-3', ['id' => $id . '_type_row']);
        $html .= \html_writer::tag('label', get_string('monitortype', 'tool_upmon'), ['class' => 'col-sm-3 col-form-label']);
        $html .= \html_writer::start_div('col-sm-9');
        
        // Keyword radio.
        $html .= \html_writer::start_div('form-check');
        $html .= \html_writer::empty_tag('input', [
            'type' => 'radio',
            'id' => $id . '_type_keyword',
            'name' => $fullname . '[type]',
            'value' => 'KEYWORD',
            'class' => 'form-check-input',
            'checked' => ($current_type_val === 'KEYWORD') ? 'checked' : null,
            'disabled' => 'disabled'
        ]);
        $html .= \html_writer::tag('label', get_string('method_keyword', 'tool_upmon'), ['for' => $id . '_type_keyword', 'class' => 'form-check-label']);
        $html .= \html_writer::end_div();
        
        // Heartbeat radio.
        $heartbeat_label = get_string('method_heartbeat', 'tool_upmon');
        if (!$is_paid) {
            $upgradelink = \html_writer::link(uptimerobotapi::AFFILIATE_URL, get_string('upgradetopaid', 'tool_upmon'), ['target' => '_blank']);
            $heartbeat_label .= ' ' . \html_writer::span('(' . get_string('pushpaidonly', 'tool_upmon') . ' ' . $upgradelink . ')', 'text-warning small');
        }
        $html .= \html_writer::start_div('form-check');
        $html .= \html_writer::empty_tag('input', [
            'type' => 'radio',
            'id' => $id . '_type_heartbeat',
            'name' => $fullname . '[type]',
            'value' => 'HEARTBEAT',
            'class' => 'form-check-input',
            'checked' => ($current_type_val === 'HEARTBEAT') ? 'checked' : null,
            'disabled' => 'disabled'
        ]);
        $html .= \html_writer::tag('label', $heartbeat_label, ['for' => $id . '_type_heartbeat', 'class' => 'form-check-label']);
        $html .= \html_writer::end_div();
        
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();
        
        // Hidden field to track original type (for type change detection).
        $html .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'id' => $id . '_original_type',
            'name' => $fullname . '[original_type]',
            'value' => $current_type_val
        ]);

        // Security Token field.
        $token_val = get_config('tool_upmon', 'check_token');
        $token_val = ($token_val === false) ? '' : $token_val;
        $html .= \html_writer::start_div('form-group row mb-3');
        $html .= \html_writer::tag('label', get_string('check_token', 'tool_upmon'), ['for' => 'id_s_tool_upmon_check_token', 'class' => 'col-sm-3 col-form-label']);
        $html .= \html_writer::start_div('col-sm-9');
        $html .= \html_writer::empty_tag('input', [
            'type' => 'text',
            'id' => 'id_s_tool_upmon_check_token',
            'name' => $fullname . '[check_token]',
            'value' => $token_val,
            'class' => 'form-control',
            'data-original-value' => $token_val // Initial state for restore logic
        ]);
        $html .= \html_writer::tag('small', get_string('check_token_desc', 'tool_upmon'), ['class' => 'form-text text-muted']);
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        // Current monitor info if linked.
        if (!empty($current)) {
            $curr_type = get_config('tool_upmon', 'monitor_type');
            $html .= \html_writer::start_div('alert alert-info');
            $html .= \html_writer::tag('strong', get_string('currentmonitor', 'tool_upmon') . ': ');
            $html .= s($current);
            $html .= \html_writer::end_div();
        }

        // Initialize AMD module for JavaScript functionality.
        // Only pass minimal data to avoid exceeding js_call_amd size limits.
        $monitors_for_js = [];
        foreach ($monitors as $m) {
            $monitors_for_js[] = [
                'id' => $m['id'],
                'friendlyName' => $m['friendlyName'] ?? $m['friendly_name'] ?? '',
                'type' => strtoupper($m['type'] ?? 'KEYWORD'),
            ];
        }
        
        global $PAGE;
        $PAGE->requires->js_call_amd(
            'tool_upmon/admin_setting_monitor',
            'init',
            [
                $id,
                $monitors_for_js,
                $SITE->fullname,
                get_string('typechangeinfo', 'tool_upmon')
            ]
        );

        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', $default, $query);
    }
}
