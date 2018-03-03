@admin
Feature: Manage languages
  In order to see my albums in my favorite language
  As a webmaster
  I need to be able to manage languages

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  Scenario: See languages available
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/index.php?page=languages"
    Then I should see 2 "active" languages
    And I should see 0 "inactive" languages

  Scenario: Activate some languages
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/index.php?page=languages"
    Then I click on deactivate "Français" language
    Then I should see 1 active languages
    And I should see 1 inactive languages

  Scenario: Add new languages
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/index.php?page=languages&section=new"
    Then I should see 0 ".languages tbody .language" elements
