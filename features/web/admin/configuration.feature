Feature: Tags
  In order to manage the gallery
  As an admin
  I need to be able to configure some parameters

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  Scenario: Manage main configuration
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/configuration"
    When I fill in "Gallery title" with "Another Phyxo gallery!"
    When I fill in "Page banner" with "Welcome to my photo gallery"
    When I select "monday" from "Week starts on"
    And I press "Save Settings"
    Then the "Gallery title" field should contain "Another Phyxo gallery!"
    Then the "Page banner" field should contain "Welcome to my photo gallery"
    Then the option "monday" from "Week starts on" is selected


