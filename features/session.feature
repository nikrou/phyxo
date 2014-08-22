Feature: User sessions
  In order to access my albums
  As a site visitor
  I need to be able to log into the website

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |

  Scenario: Login
    Given I am on homepage
    And I am logged in as "user1" with password "pass1"
    Then I should be allowed to go to a protected page

  Scenario: Logout
    Given I am logged in as "user1" with password "pass1"
    Then I should be allowed to go to a protected page
    When I follow "Logout"
    Then I should not be allowed to go to a protected page

  Scenario: Checking remember me
    Given I am logged in as "user1" with password "pass1" and auto login
    When I restart my browser
    Then I should be allowed to go to a protected page
