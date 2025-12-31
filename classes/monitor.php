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
 * Monitor class for health checks
 *
 * @package    tool_upmon
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class monitor {

    /**
     * Check if cron is healthy by checking last cron start time.
     * Uses the official Moodle cron timestamp from tool_task config.
     *
     * @return bool|string True if healthy, error message otherwise
     */
    public static function check_cron_health(): bool|string {
        // Get the last cron start time set by core cron runner.
        $lastcronstart = get_config('tool_task', 'lastcronstart');
        
        if (!$lastcronstart) {
            return "Cron has never run on this site.";
        }

        $threshold = (int) get_config('tool_upmon', 'cron_threshold') ?: 60;
        $minutessince = (time() - $lastcronstart) / 60;

        if ($minutessince > $threshold) {
            return "Cron has not run for " . round($minutessince) . " minutes (Threshold: {$threshold} mins).";
        }

        return true;
    }


    /**
     * Check if site is in maintenance mode (either via config or CLI file).
     *
     * @return bool True if site is in maintenance mode
     */
    public static function is_maintenance_mode(): bool {
        global $CFG;
        
        // Check config-based maintenance mode.
        if (!empty($CFG->maintenance_enabled)) {
            return true;
        }
        
        // Check CLI maintenance file.
        if (file_exists($CFG->dataroot . '/climaintenance.html')) {
            return true;
        }
        
        return false;
    }

    /**
     * Reset maintenance tracker when site exits maintenance mode.
     */
    public static function check_maintenance_reset(): void {
        if (!self::is_maintenance_mode()) {
            unset_config('maintenance_start', 'tool_upmon');
        }
    }
    
    /**
     * Check if maintenance mode threshold has been exceeded.
     * Only returns an error if site is in maintenance AND threshold is exceeded.
     *
     * @return bool|string True if OK (not in maintenance or under threshold), error message if threshold exceeded
     */
    public static function check_maintenance_threshold_exceeded(): bool|string {
        if (!self::is_maintenance_mode()) {
            return true;
        }

        // Track when maintenance mode started.
        $start = get_config('tool_upmon', 'maintenance_start');
        if (!$start) {
            set_config('maintenance_start', time(), 'tool_upmon');
            $start = time();
        }

        $threshold = (int) get_config('tool_upmon', 'maintenance_threshold') ?: 60;
        $minutessince = (time() - $start) / 60;

        if ($minutessince > $threshold) {
            return "Site has been in maintenance mode for " . round($minutessince) . " minutes (Threshold: {$threshold} mins).";
        }

        // Under threshold - considered OK.
        return true;
    }
    
    /**
     * Run all enabled health checks.
     *
     * @return array Array of error messages. Empty array means all checks passed.
     */
    public static function run_health_checks(): array {
        $errors = [];
        
        // Update maintenance tracker.
        self::check_maintenance_reset();
        
        // Always check core Moodle health (database, cache).
        $result = self::check_core_health();
        if ($result !== true) {
            $errors['core'] = $result;
            // If core is unhealthy, skip other checks.
            return $errors;
        }
        
        // Check cron health if enabled.
        if (get_config('tool_upmon', 'enable_cron_alerts')) {
            $result = self::check_cron_health();
            if ($result !== true) {
                $errors['cron'] = $result;
            }
        }
        
        // Check maintenance threshold if enabled.
        if (get_config('tool_upmon', 'enable_maintenance_alerts')) {
            $result = self::check_maintenance_threshold_exceeded();
            if ($result !== true) {
                $errors['maintenance'] = $result;
            }
        }
        
        return $errors;
    }
    
    /**
     * Check core Moodle health - database connectivity and cache functionality.
     *
     * @return bool|string True if healthy, error message otherwise
     */
    public static function check_core_health(): bool|string {
        global $DB;
        
        // Check database connectivity.
        try {
            $result = $DB->get_field_sql('SELECT 1');
            if ($result != 1) {
                return "Database returned unexpected result.";
            }
        } catch (\Exception $e) {
            return "Database connection failed: " . $e->getMessage();
        }
        
        // Check cache functionality.
        try {
            $cache = \cache::make('core', 'config');
            $testkey = 'tool_upmon_healthcheck_' . time();
            $testvalue = random_string(16);
            
            $cache->set($testkey, $testvalue);
            $retrieved = $cache->get($testkey);
            $cache->delete($testkey);
            
            if ($retrieved !== $testvalue) {
                return "Cache read/write test failed.";
            }
        } catch (\Exception $e) {
            return "Cache system error: " . $e->getMessage();
        }
        
        return true;
    }
}
