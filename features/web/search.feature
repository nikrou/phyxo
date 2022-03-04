Feature: Searching for images
  In order to show easily pictures
  As a user
  I need to be able to find them by criterions

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
      | user2    | pass2    | normal |
    And an album:
      | name    | status  |
      | album 1 | public  |
      | album 2 | public  |
      | album 3 | private |
    And some images:
      | file                     | name    | album   | author  | date_creation       | tags          |
      | features/media/img_1.jpg | photo 1 | album 1 | author1 | 2019-04-02 11:00:00 | [tag 1,tag 2] |
      | features/media/img_2.jpg | photo 2 | album 2 | author2 | 2019-05-01 13:00:00 | tag 1         |
      | features/media/img_3.jpg | photo 3 | album 2 | author3 | 2019-06-11 14:00:00 |               |
      | features/media/img_4.jpg | photo 4 | album 3 | author1 | 2020-04-01 14:00:00 | [tag 3,tag 2] |
      | features/media/img_5.jpg | photo 5 | album 2 | author2 | 2020-04-03 14:00:00 | tag 2         |
    And user "user1" can access album "album 1"
    And user "user1" can access album "album 2"
    And user "user2" can access album "album 3"

  Scenario: search by name
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    And I fill in "search_allwords" with "photo 1"
    And I press "Submit"
    Then I should see photo "photo 1"
    But I should not see photo "photo 2"
    And I should not see photo "photo 4"

  Scenario: search by name with wildcard
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    And I fill in "search_allwords" with "photo ?"
    And I press "Submit"
    Then I should see photo "photo 1"
    Then I should see photo "photo 2"
    And I should see photo "photo 3"
    And I should not see photo "photo 4"

  Scenario: search by album
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    And I select "album 1" from "categories"
    And I press "Submit"
    Then I should see photo "photo 1"
    But I should not see photo "photo 2"

  Scenario: count photo once even in severals albums
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    Then the select "authors" should contain:
      """
      author1 (one photo)
      author2 (2 photos)
      author3 (one photo)
      """
    When I select "author2" from "authors"
    And I press "Submit"
    Then I should see photo "photo 2"
    Then I should see photo "photo 5"
    But I should not see photo "photo 1"

  Scenario: search by tag
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    Then the select "tags" should contain:
      """
      tag 1 (2 photos)
      tag 2 (2 photos)
      """
    When I select "tag 2" from "tags"
    And I press "Submit"
    Then I should see photo "photo 1"
    Then I should see photo "photo 5"
    But I should not see photo "photo 3"
    And I should not see photo "photo 4"

  Scenario: search by tag with private album
    Given I am logged in as "user2" with password "pass2"
    When I am on homepage
    And I follow "Search"
    Then the select "tags" should contain:
      """
      tag 1 (2 photos)
      tag 2 (3 photos)
      tag 3 (one photo)
      """
    When I select "tag 2" from "tags"
    And I press "Submit"
    Then I should see photo "photo 1"
    And I should see photo "photo 5"
    And I should see photo "photo 4"

  Scenario: search by creation date - only start date
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    Then I select "date_creation" from "date_type"
    And I select "1" from "start_day"
    And I select "July" from "start_month"
    And I fill in "start_year" with "2019"
    And I press "Submit"
    Then I should see photo "photo 5"
    But I should not see photo "photo 3"
    And I should not see photo "photo 4"

  Scenario: search by creation date - only end date
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    Then I select "date_creation" from "date_type"
    And I select "1" from "end_day"
    And I select "June" from "end_month"
    And I fill in "end_year" with "2019"
    And I press "Submit"
    Then I should see photo "photo 1"
    And I should see photo "photo 2"
    But I should not see photo "photo 3"
    And I should not see photo "photo 4"

  Scenario: search by creation date - start date and end date
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Search"
    Then I select "date_creation" from "date_type"
    And I select "1" from "end_day"
    And I select "June" from "end_month"
    And I fill in "end_year" with "2019"
    And I select "1" from "end_day"
    And I select "June" from "end_month"
    And I fill in "end_year" with "2020"
    And I press "Submit"
    Then I should see photo "photo 3"
    And I should see photo "photo 2"
    And I should not see photo "photo 4"
