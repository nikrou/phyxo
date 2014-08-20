Feature: User sessions
  In order to access my albums
  As a site visitor
  I need to be abble to log into the website

  Scenario: Login
    Given a user:
      | username | user1  |
      | password | pass1  |
      | status   | normal |
    Given I am on homepage
    And I am logged in as "user1" with password "pass1"
    Then I should be allowed to go to a protected page

  Scenario: Logout
    Given a user:
      | username | user1  |
      | password | pass1  |
      | status   | normal |
    Given I am logged in as "user1" with password "pass1"
    Then I should be allowed to go to a protected page
    When I follow "Logout"
    Then I should not be allowed to go to a protected page

  Scenario: Checking remember me
    Given a user:
      | username | user1  |
      | password | pass1  |
      | status   | normal |
    Given I am logged in as "user1" with password "pass1" and auto login
    When I restart my browser
    Then I should be allowed to go to a protected page
