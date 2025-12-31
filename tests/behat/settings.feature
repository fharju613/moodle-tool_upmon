@tool @tool_upmon
Feature: tool_upmon_settings
  In order to configure uptime monitoring
  As an admin
  I need to be able to access and modify the plugin settings

  Background:
    Given I log in as "admin"

  @javascript
  Scenario: Access the uptime monitor settings page
    Given I navigate to "Plugins > Admin tools > Uptime Monitor" in site administration
    Then I should see "Uptime Monitor"
    And I should see "Enable Uptime Monitor"
    And I should see "UptimeRobot API Key"

  @javascript
  Scenario: Enable and disable the plugin
    Given I navigate to "Plugins > Admin tools > Uptime Monitor" in site administration
    When I set the field "Enable Uptime Monitor" to "1"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I set the field "Enable Uptime Monitor" to "0"
    And I press "Save changes"
    Then I should see "Changes saved"

  @javascript
  Scenario: Configure cron monitoring settings
    Given I navigate to "Plugins > Admin tools > Uptime Monitor" in site administration
    When I set the following fields to these values:
      | Enable Cron Alerts                 | 1  |
      | Cron Alert Threshold (minutes)     | 33 |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Cron Alert Threshold (minutes)" matches value "33"

  @javascript
  Scenario: Configure maintenance monitoring settings
    Given I navigate to "Plugins > Admin tools > Uptime Monitor" in site administration
    When I set the following fields to these values:
      | Enable Maintenance Mode Alerts           | 1   |
      | Maintenance Mode Threshold (minutes)     | 120 |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Maintenance Mode Threshold (minutes)" matches value "120"
