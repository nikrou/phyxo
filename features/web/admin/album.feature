@admin
Feature: Create new access
  In order share my albums
  As a webmaster
  I need to be able to create new user

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  @javascript
  Scenario: Create new album
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/index.php?page=albums"
    And I follow "create a new album"
    And I fill in "Album name" with "My ALbum"
    And I press "Create"
    Then I should see "Virtual album added"
