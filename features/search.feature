Feature: Searching for images
  In order to show easily pictures
  As a user
  I need to be able to find them by criterions

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
    And albums:
      | name    |
      | album 1 |
      | album 2 |
    And images:
      | file                     | name    | album   | date_creation       |
      | features/media/img_5.png | photo 5 | album 1 | 2013-04-12 11:00:00 |
      | features/media/img_6.png | photo 6 | album 2 | 2013-04-11 13:00:00 |
      | features/media/img_7.png | photo 7 | album 2 | 2013-04-11 14:00:00 |
    And "user1" can access "album 1"
    And "user1" can access "album 2"

  Scenario: search by name
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    And I fill in "search_allwords" with "photo 5"
    And I press "Submit"
    Then I should see "photo 5"

  Scenario: search by name with wildcard
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    And I fill in "search_allwords" with "photo ?"
    And I press "Submit"
    Then I should see "photo 5"
    Then I should see "photo 6"
    And I should see "photo 7"

  Scenario: search by album
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    And I select "album 1" from "categories"
    And I press "Submit"
    Then I should see "photo 5"
    But I should not see "photo 6"
