@tool @tool_upmon
Feature: tool_upmon_check_endpoint
  In order to monitor site health
  As an external monitoring service
  I need to be able to access the health check endpoint

  Background:
    Given I log in as "admin"
    And the following config values are set as admin:
      | enable_cron_alerts       | 0  | tool_upmon |
      | enable_maintenance_alerts| 0  | tool_upmon |

  Scenario: Check endpoint returns success when plugin is enabled and site is healthy
    Given the following config values are set as admin:
      | enable | 1 | tool_upmon |
    And I am on site homepage
    When I visit "/admin/tool/upmon/check.php"
    Then I should see "upmon PASSES"

  Scenario: Check endpoint returns error when plugin is disabled
    Given the following config values are set as admin:
      | enable | 0 | tool_upmon |
    And I am on site homepage
    When I visit "/admin/tool/upmon/check.php"
    Then I should see "Uptime Monitor disabled"

  Scenario: Check endpoint with valid token allows access
    Given the following config values are set as admin:
      | enable      | 1         | tool_upmon |
      | check_token | abc123xyz | tool_upmon |
    And I am on site homepage
    When I visit "/admin/tool/upmon/check.php?token=abc123xyz"
    Then I should see "upmon PASSES"

  Scenario: Check endpoint with invalid token denies access
    Given the following config values are set as admin:
      | enable      | 1         | tool_upmon |
      | check_token | abc123xyz | tool_upmon |
    And I am on site homepage
    When I visit "/admin/tool/upmon/check.php?token=wrongtoken"
    Then I should see "Forbidden"

  Scenario: Check endpoint without token when required denies access
    Given the following config values are set as admin:
      | enable      | 1         | tool_upmon |
      | check_token | abc123xyz | tool_upmon |
    And I am on site homepage
    When I visit "/admin/tool/upmon/check.php"
    Then I should see "Forbidden"
