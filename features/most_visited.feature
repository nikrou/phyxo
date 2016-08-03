Feature: See most visited photos
  In order to see interesting pictures
  As a visitor
  I want to see the most visited ones

  Background: init
     Given some users:
      | username | password | status |
      | user1    | pass1    | normal |
      | user2    | pass2    | normal |
    And an album:
      | name    |
      | album 1 |
    And images:
      | name    |  album  |
      | photo 1 | album 1 |
      | photo 2 | album 1 |
    And user "user1" can access "album 1"
    And user "user2" can access "album 1"

  Scenario: Rate a picture
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 1"
    And I follow "photo 2"
    Given I am logged in as "user2" with password "pass2"
    When I follow "album 1"
    And I follow "photo 1"
    And I go to the homepage
    And I follow "Most visited"
    Then print last response
