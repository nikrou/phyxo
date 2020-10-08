Feature: Tags
  In order to manage the gallery
  As an admin
  I need to be able to manage tags

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  Scenario: Manage Who can add tags
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/tags/permissons"
    Then I select "webmaster" from "Who can add tags?"
    And I press "Submit"
    Then the option "webmaster" from "Who can add tags?" is selected

  Scenario: Manage Who can add tags and allow nobody
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/tags/permissons"
    Then I select "webmaster" from "Who can add tags?"
    And I press "Submit"
    Then I select "" from "Who can add tags?"
    And I press "Submit"
    Then the option "" from "Who can add tags?" is selected

  Scenario: Manage Who can delete tags
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/tags/permissons"
    Then I select "admin" from "Who can delete related tags?"
    And I press "Submit"
    Then the option "admin" from "Who can delete related tags?" is selected

  Scenario: Manage Who can deleted tags and allow nobody
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/tags/permissons"
    Then I select "webmaster" from "Who can delete related tags?"
    And I press "Submit"
    Then I select "" from "Who can delete related tags?"
    And I press "Submit"
    Then the option "" from "Who can delete related tags?" is selected

  Scenario Outline: Manage options
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/tags/permissons"
    Then I check "<label>"
    And I press "Submit"
    Then the checkbox "<label>" should be checked
    Then I uncheck "<label>"
    And I press "Submit"
    Then the checkbox "<label>" should not be checked

    Examples:
      | label                                                 |
      | Only add existing tags                                |
      | Moderate added tags                                   |
      | Moderate deleted tags                                 |
      | Show added pending tags to the user who add them      |
      | Show deleted pending tags to the user who delete them |
