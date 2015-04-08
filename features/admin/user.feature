Feature: Create new access
  In order share my albums
  As a webmaster
  I need to be able to create new user

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  # @see bug https://github.com/nikrou/phyxo/issues/1
  @javascript
  Scenario: Create new user
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin.php?page=user_list"
    And I follow "Add a user"
    And I fill in "username" with "John Doe"
    And I fill in "email" with "john.doe@phyxo.net"
    And I press "Submit"
    Then I wait for message "infos" to appear
    And I should see "User John Doe added"
    And I should see "John Doe" in the "#userList tr:nth-child(4) td:nth-child(1)" element
    And I should see "User" in the "#userList tr:nth-child(4) td:nth-child(2)" element
    And I should see "john.doe@phyxo.net" in the "#userList tr:nth-child(4) td:nth-child(3)" element
