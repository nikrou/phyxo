Feature: User sessions
  In order to access my albums
  As a site visitor
  I need to be able to log into the website

  Background: init
    Given a user:
      | username | password | mail_address    | status    | activation_key   | activation_key_expire |
      | admin    | admin    | admin@phyxo.net | webmaster | admin-secret-key | 2021-12-30 19:10:00   |
      | user1    | pass1    | user1@phyxo.net | normal    | user1-secret-key | 2021-12-30 19:10:00   |
      | user2    | pass1    |                 | normal    | user2-secret-key | 2021-12-30 19:10:00   |

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

  Scenario: Forgot password - invalid username
    Given I am on "/password"
    Then I should see "Forgot your password?"
    When I fill in "Username or email" with "dummy"
    And I press "Change my password"
    Then I should see "Invalid username or email"

  Scenario: Forgot password - no email
    Given I am on "/password"
    Then I should see "Forgot your password?"
    When I fill in "Username or email" with "user2"
    And I press "Change my password"
    Then I should see "User \"user2\" has no email address, password reset is not possible"

  Scenario: Forgot password
    Given I am on "/password"
    Then I should see "Forgot your password?"
    When I fill in "Username or email" with "user1"
    And I press "Change my password"
    Then I should see "Check your email for the confirmation link"

  Scenario: Reset password - incorrect activation key
    Given I am on "/password/dummy-key"
    Then I should see "Activation key does not exist"

  Scenario: Reset password
    Given I am on "/password/user1-secret-key"
    Then I should see "Forgot your password?"
    When I fill in "New password" with "my-pass"
    And I fill in "Confirm password" with "my-pass"
    And I press "Submit"
    Then I should see "Your password has been updated"

