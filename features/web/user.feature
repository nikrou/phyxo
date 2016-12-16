Feature: User albums
  In order to access my albums
  As a user
  I need to be able to log into the website

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
    And albums:
      | name    |
      | album 1 |
      | album 2 |
    And images:
      | name    | album   |
      | photo 1 | album 1 |
      | photo 2 | album 2 |
    And user "user1" can access "album 1"
    And user "user1" cannot access "album 2"

  Scenario: I can see my photos
    Given I am logged in as "user1" with password "pass1"
    Then I should see "album 1"
    When I follow "album 1"
    And I follow "photo 1"
    Then I should see "photo 1"

  Scenario: I can see only protected albums with granted access
    Given I am logged in as "user1" with password "pass1"
    Then I should see "album 1"
    But I should not see "album 2"
    And I should not be allowed to go to album "album 2"
