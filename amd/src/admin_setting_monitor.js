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
 * AMD module for monitor configuration admin setting.
 *
 * @module     tool_upmon/admin_setting_monitor
 * @copyright  2025 Frederick Harju <fharju@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialize the monitor configuration UI.
 *
 * @param {string} selectId - The ID of the monitor select dropdown
 * @param {Array} monitors - Array of monitor objects from UptimeRobot
 * @param {string} defaultName - Default site name for new monitors
 * @param {string} typeChangeInfo - Warning message for type change attempts
 */
export const init = (selectId, monitors, defaultName, typeChangeInfo) => {
    /**
     * Update form fields based on selected monitor.
     *
     * @param {boolean} setDefaultRadio - Whether to set default radio selection
     */
    const updateFields = (setDefaultRadio = true) => {
        const select = document.getElementById(selectId);
        const friendlyInput = document.getElementById(selectId + '_friendlyname');
        const typeKeyword = document.getElementById(selectId + '_type_keyword');
        const typeHeartbeat = document.getElementById(selectId + '_type_heartbeat');
        const tokenInput = document.getElementById('id_s_tool_upmon_check_token');

        if (!select || !friendlyInput || !typeKeyword || !typeHeartbeat) {
            return;
        }

        const val = select.value;
        let isHeartbeat = false;

        if (val === '') {
            // Nothing selected - disable fields.
            friendlyInput.disabled = true;
            friendlyInput.value = '';
            typeKeyword.disabled = true;
            typeHeartbeat.disabled = true;

            if (tokenInput) {
                if (!tokenInput.hasAttribute('data-original-value')) {
                    tokenInput.setAttribute('data-original-value', tokenInput.value);
                }
                tokenInput.disabled = true;
                tokenInput.value = '';
            }
        } else if (val === 'new') {
            // Create new - enable fields.
            friendlyInput.disabled = false;
            if (setDefaultRadio) {
                friendlyInput.value = defaultName;
            }
            typeKeyword.disabled = false;
            typeHeartbeat.disabled = false;
            if (setDefaultRadio) {
                typeKeyword.checked = true;
            }

            if (tokenInput) {
                tokenInput.disabled = false;
                if (tokenInput.hasAttribute('data-original-value')) {
                    tokenInput.value = tokenInput.getAttribute('data-original-value');
                }
            }
        } else {
            // Existing monitor - populate from data.
            friendlyInput.disabled = false;
            typeKeyword.disabled = false;
            typeHeartbeat.disabled = false;

            for (let i = 0; i < monitors.length; i++) {
                if (monitors[i].id == val) {
                    friendlyInput.value = monitors[i].friendlyName || monitors[i].friendly_name || '';
                    if (monitors[i].type === 'HEARTBEAT') {
                        typeHeartbeat.checked = true;
                        isHeartbeat = true;
                    } else {
                        typeKeyword.checked = true;
                    }
                    break;
                }
            }
        }

        // Handle token disable/blanking for Heartbeat.
        if (typeHeartbeat.checked) {
            isHeartbeat = true;
        }

        if (tokenInput) {
            if (isHeartbeat) {
                if (!tokenInput.hasAttribute('data-original-value')) {
                    tokenInput.setAttribute('data-original-value', tokenInput.value);
                }
                tokenInput.disabled = true;
                tokenInput.value = '';
            } else if (val !== '') {
                tokenInput.disabled = false;
                if (tokenInput.hasAttribute('data-original-value')) {
                    tokenInput.value = tokenInput.getAttribute('data-original-value');
                }
            }
        }
    };

    /**
     * Handle monitor type radio button change.
     *
     * @param {Event} event - The change event
     */
    const handleTypeChange = (event) => {
        const select = document.getElementById(selectId);
        const typeKeyword = document.getElementById(selectId + '_type_keyword');
        const typeHeartbeat = document.getElementById(selectId + '_type_heartbeat');
        const tokenInput = document.getElementById('id_s_tool_upmon_check_token');

        if (select.value && select.value !== 'new') {
            // Existing monitor - type cannot be changed.
            const selectedMonitorId = select.value;
            let originalMonitorType = '';

            for (let i = 0; i < monitors.length; i++) {
                if (monitors[i].id == selectedMonitorId) {
                    originalMonitorType = monitors[i].type;
                    break;
                }
            }

            const newType = typeKeyword.checked ? 'KEYWORD' : 'HEARTBEAT';

            if (originalMonitorType && newType !== originalMonitorType) {
                // Show warning and revert.
                window.alert(typeChangeInfo);

                if (originalMonitorType === 'HEARTBEAT') {
                    typeHeartbeat.checked = true;
                } else {
                    typeKeyword.checked = true;
                }

                event.preventDefault();
                return false;
            }
        } else if (select.value === 'new') {
            // New monitor - user is allowed to change type.
            const isHeartbeat = typeHeartbeat.checked;

            if (tokenInput) {
                if (isHeartbeat) {
                    if (!tokenInput.hasAttribute('data-original-value')) {
                        tokenInput.setAttribute('data-original-value', tokenInput.value);
                    }
                    tokenInput.disabled = true;
                    tokenInput.value = '';
                } else {
                    tokenInput.disabled = false;
                    if (tokenInput.hasAttribute('data-original-value')) {
                        tokenInput.value = tokenInput.getAttribute('data-original-value');
                    }
                }
            }
            return;
        }
    };

    // Get DOM elements.
    const select = document.getElementById(selectId);
    const typeKeyword = document.getElementById(selectId + '_type_keyword');
    const typeHeartbeat = document.getElementById(selectId + '_type_heartbeat');

    if (!select) {
        return;
    }

    // Attach event listeners.
    select.addEventListener('change', () => updateFields(true));

    if (typeKeyword) {
        typeKeyword.addEventListener('change', handleTypeChange);
    }
    if (typeHeartbeat) {
        typeHeartbeat.addEventListener('change', handleTypeChange);
    }

    // Initialize fields on load.
    updateFields(true);
};
