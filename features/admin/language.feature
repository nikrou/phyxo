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
    Then I should see 69 ".state.state-active .languageBox" elements
    And I should see 0 ".state.state-inactive .languageBox" elements

  Scenario: Activate some languages
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/index.php?page=languages"
    Then I click on the ".state.state-active .languageBox:nth-child(2) a.deactivate" element
    Then I should see 68 ".state.state-active .languageBox" elements
    And I should see 1 ".state.state-inactive .languageBox" elements

  Scenario: Add new languages
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/index.php?page=languages&section=new"
    Then I should see 1 ".languages tbody .language" elements
