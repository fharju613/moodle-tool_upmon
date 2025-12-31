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
 * UptimeRobot API v3 integration class
 *
 * @package    tool_upmon
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uptimerobotapi {

    /** @var string UptimeRobot API v3 URL */
    const API_URL = 'https://api.uptimerobot.com/v3/';
    
    /** @var string UptimeRobot affiliate signup URL - DO NOT MODIFY */
    const AFFILIATE_URL = 'https://uptimerobot.com/?red=evlo';
    
    /** @var string Keyword for keyword monitors - must match check.php output */
    const KEYWORD = 'upmon PASSES';

    /** @var string Monitor type for keyword monitors */
    const TYPE_KEYWORD = 'KEYWORD';
    
    /** @var string Monitor type for heartbeat monitors */
    const TYPE_HEARTBEAT = 'HEARTBEAT';
    
    /**
     * Helper to construct the full heartbeat URL.
     * The full URL format is: https://heartbeat.uptimerobot.com/m{MONITOR_ID}-{TOKEN}
     *
     * @param array $monitor The monitor array from API
     * @return string The full heartbeat URL
     */
    public static function get_full_heartbeat_url(array $monitor): string {
        $url = $monitor['url'] ?? '';
        if (empty($url)) {
            return '';
        }
        
        // If it already looks like a URL, return it.
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        // Otherwise construct it.
        return 'https://heartbeat.uptimerobot.com/m' . $monitor['id'] . '-' . $url;
    }

    /**
     * Get the API key from settings
     *
     * @return string
     */
    private static function get_api_key(): string {
        return get_config('tool_upmon', 'apikey') ?: '';
    }

    /**
     * Make a request to the UptimeRobot API v3
     *
     * @param string $method HTTP method (GET, POST, PATCH, DELETE)
     * @param string $endpoint API endpoint (e.g., 'monitors', 'monitors/123')
     * @param array $params Request parameters (sent as JSON body for POST/PATCH, query params for GET)
     * @return array|false
     */
    private static function request(string $method, string $endpoint, array $params = []) {
        $apikey = self::get_api_key();
        if (empty($apikey)) {
            return false;
        }

        // Use Moodle's DI container to get curl client (allows mocking in tests).
        $curl = \core\di::get(\curl::class);
        
        // Set HTTP Bearer token authentication and headers.
        $curl->setHeader([
            'Authorization: Bearer ' . $apikey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $url = self::API_URL . $endpoint;
        
        // Execute request based on method.
        switch (strtoupper($method)) {
            case 'GET':
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                }
                $response = $curl->get($url);
                break;
            case 'POST':
                $response = $curl->post($url, json_encode($params));
                break;
            case 'PATCH':
                $response = $curl->post($url, json_encode($params), ['CURLOPT_CUSTOMREQUEST' => 'PATCH']);
                break;
            case 'DELETE':
                $response = $curl->delete($url);
                break;
            default:
                return ['error' => ['message' => 'Invalid HTTP method: ' . $method]];
        }

        if ($curl->get_errno()) {
            return ['error' => ['message' => 'cURL Error: ' . $curl->error]];
        }

        $data = json_decode($response ?? '', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => ['message' => 'Invalid JSON response from API']];
        }

        return $data;
    }

    /**
     * Get monitors
     *
     * @param array $params Optional query parameters
     * @return array|false
     */
    public static function get_monitors(array $params = []) {
        $result = self::request('GET', 'monitors', $params);
        
        // Normalize v3 response to match expected format.
        if ($result && isset($result['data'])) {
            return [
                'stat' => 'ok',
                'monitors' => $result['data']
            ];
        }
        if ($result && isset($result['error'])) {
            return ['stat' => 'fail', 'error' => $result['error']];
        }
        return $result;
    }

    /**
     * Get account details
     *
     * @return array|false
     */
    public static function get_account_details() {
        $result = self::request('GET', 'account');
        
        // Normalize v3 response.
        if ($result && isset($result['data'])) {
            return [
                'stat' => 'ok',
                'account' => $result['data']
            ];
        }
        if ($result && isset($result['error'])) {
            return ['stat' => 'fail', 'error' => $result['error']];
        }
        return $result;
    }

    /**
     * Get a single monitor
     *
     * @param int $id
     * @return array|false
     */
    public static function get_monitor(int $id) {
        $result = self::request('GET', 'monitors/' . $id);
        
        // V3 API returns single monitor directly (not wrapped in 'data').
        if ($result && isset($result['id'])) {
            return $result;
        }
        // Handle wrapped response just in case.
        if ($result && isset($result['data'])) {
            return $result['data'];
        }
        return false;
    }

    /**
     * Create a new monitor
     *
     * @param string $friendlyname
     * @param string $url
     * @param string $type Monitor type (KEYWORD, HEARTBEAT)
     * @param string $keywordValue Required for keyword monitoring
     * @param string $keywordType Required for keyword monitoring (ALERT_NOT_EXISTS = alert when missing)
     * @return array|false
     */
    public static function create_monitor(string $friendlyname, string $url, string $type, 
                                          string $keywordValue = '', string $keywordType = 'ALERT_NOT_EXISTS') {
        $params = [
            'friendlyName' => $friendlyname,
            'type' => strtoupper($type),
            'interval' => 300,  // Check every 5 minutes.
            'timeout' => 30,    // 30 second timeout.
            'gracePeriod' => 0  // No grace period.
        ];
        
        if (strtoupper($type) === self::TYPE_KEYWORD) {
            // Keyword monitors need URL and keyword settings.
            $params['url'] = $url;
            $params['keywordValue'] = $keywordValue;
            $params['keywordType'] = $keywordType;
            $params['keywordCaseType'] = 'CaseSensitive';
            $params['httpMethodType'] = 'GET';
        }
        // For HEARTBEAT monitors, UptimeRobot generates the URL - we don't pass one.
        
        $result = self::request('POST', 'monitors', $params);
        
        // Normalize v3 response - v3 returns monitor directly, not in 'data' key for POST.
        if ($result && isset($result['id'])) {
            return [
                'stat' => 'ok',
                'monitor' => $result
            ];
        }
        // Handle error responses.
        // UptimeRobot API v3 returns errors like: {'code': '009-005', 'message': 'details'}
        // or with legacy format: {'error': 'ERROR_TYPE', 'message': 'details'}.
        if ($result && isset($result['code']) && isset($result['message'])) {
            // API v3 error format with code and message.
            $message = is_array($result['message']) ? implode(', ', $result['message']) : $result['message'];
            return ['stat' => 'fail', 'error' => ['message' => $message]];
        }
        if ($result && isset($result['error'])) {
            // Legacy error format.
            $message = $result['error'];
            if (isset($result['message']) && is_array($result['message'])) {
                $message = implode(', ', $result['message']);
            } else if (isset($result['message'])) {
                $message = $result['message'];
            }
            return ['stat' => 'fail', 'error' => ['message' => $message]];
        }
        return $result;
    }

    /**
     * Edit a monitor
     *
     * @param int $id
     * @param array $params Parameters to update (url, keywordValue, keywordType, friendlyName, etc.)
     * @return array|false
     */
    public static function edit_monitor(int $id, array $params = []) {
        $result = self::request('PATCH', 'monitors/' . $id, $params);
        
        // V3 API returns updated monitor directly (not wrapped in 'data').
        if ($result && isset($result['id'])) {
            return [
                'stat' => 'ok',
                'monitor' => $result
            ];
        }
        // Handle wrapped response just in case.
        if ($result && isset($result['data'])) {
            return [
                'stat' => 'ok',
                'monitor' => $result['data']
            ];
        }
        if ($result && isset($result['error'])) {
            return ['stat' => 'fail', 'error' => $result['error']];
        }
        return $result;
    }

    /**
     * Delete a monitor
     *
     * @param int $id
     * @return array|false
     */
    public static function delete_monitor(int $id) {
        $result = self::request('DELETE', 'monitors/' . $id);
        
        // Normalize v3 response.
        if ($result && !isset($result['error'])) {
            return ['stat' => 'ok'];
        }
        if ($result && isset($result['error'])) {
            return ['stat' => 'fail', 'error' => $result['error']];
        }
        return $result;
    }
}
