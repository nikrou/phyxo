Feature: Comment
  In order to add comments to the gallery
  As a user
  I need to be able to navigate through the gallery by date

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
      | user2    | pass2    | normal |
    And some albums:
      | name    | status  |
      | album 1 | private |
      | album 2 | private |
      | album 3 | private |
    And some images:
      | name    | album   | date_creation       |
      | photo 1 | album 1 | 2019-04-02 11:00:00 |
      | photo 2 | album 2 | 2019-05-01 13:00:00 |
      | photo 3 | album 2 | 2019-05-10 13:00:00 |
      | photo 4 | album 2 | 2019-05-10 14:00:00 |
      | photo 5 | album 3 | 2020-04-01 14:00:00 |

    And user "user1" can access album "album 1"
    And user "user1" can access album "album 2"
    And user "user1" cannot access album "album 3"
    And user "user2" can access album "album 1"
    And user "user2" can access album "album 2"
    And user "user2" can access album "album 3"

  Scenario: See calendar
    Given I am logged in as "user1" with password "pass1"
    When I follow "Calendar"
    Then I should see 1 calendar thumbnail
    And I should see "2019"
    But I should not see "2020"
    When I follow "2019"
    Then I should see "2019 - 4 photos"
    Then the calendar thumbnail "Apr" should contains 1 image
    And the calendar thumbnail "May" should contains 3 images

  Scenario: See calendar - not forbidden albums
    Given I am logged in as "user2" with password "pass2"
    When I follow "Calendar"
    Then I should see 2 calendar thumbnails
    And I should see "2019"
    But I should see "2020"
    When I follow "2020"
    Then I should see "2020 - one photo"
    Then the calendar thumbnail "Apr" should contains 1 image

  Scenario: See calendar - by month
    Given I am logged in as "user1" with password "pass1"
    When I follow "Calendar"
    When I follow "2019"
    And I follow "May"
    Then I should see "May 2019 - 3 photos"
    Then there's 1 image for day "1"
    Then there's 2 images for day "10"

  Scenario: See calendar - by day
    Given I am logged in as "user1" with password "pass1"
    When I follow "Calendar"
    When I follow "2019"
    And I follow "May"
    And I click calendar thumbnail "10"
    Then I should see "10 May 2019 - 2 photos"

