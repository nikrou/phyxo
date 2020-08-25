Feature: User albums
  In order to access my albums
  As a user
  I need to be able to log into the website

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
      | user2    | pass2    | normal |
    And some albums:
      | name    | status  |
      | album 1 | private |
      | album 2 | private |
      | album 3 | public  |

    And some images:
      | name    | album   |
      | photo 1 | album 1 |
      | photo 2 | album 2 |
      | photo 3 | album 3 |
    And user "user1" can access album "album 1"
    And user "user1" cannot access album "album 2"
    And user "user2" can access album "album 2"

  Scenario: I can see my photos
    Given I am logged in as "user1" with password "pass1"
    Then I should see "album 1"
    When I follow "album 1"
    Then I should see photo "photo 1"

  Scenario: I can see only protected albums with granted access
    Given I am logged in as "user1" with password "pass1"
    Then I should see "album 1"
    But I should not see "album 2"
    And I should not be allowed to go to album "album 2"

    When I am logged in as "user2" with password "pass2"
    Then I should see "album 2"
    And I follow "album 2"
    Then the response status code should be 200
    But I should not see "album 1"
    And I should not be allowed to go to album "album 1"

  Scenario: I can see public albums
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 3"
    Then I should see photo "photo 3"

  Scenario: Guest can see only public albums and only if guest access is allowed
    When config for "guest_access" of type "boolean" equals to "true"
    And I am on homepage
    Then I should see "album 3"
    But I should not see "album 1"
    And I should not see "album 2"

  Scenario: Guest can see public albums if guest access is forbidden
    When config for "guest_access" of type "boolean" equals to "false"
    And I am on homepage
    Then I should not see "album 3"
