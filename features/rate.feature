Feature: Rate photos
  In order to share my favorites pictures
  As a visitor
  I want to rate pictures

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
    And "user1" can access "album 1"
    And "user2" can access "album 1"

  Scenario: Rate a picture
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 1"
    And I clik rate button 3
    Then I should see text matching "3(\.00?)? \(1 rate\)"

  Scenario: Multiples rates for the same picture
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 2"
    And I clik rate button 1
    Then I should see text matching "1(\.00?)? \(1 rate\)"
    Given I am logged in as "user2" with password "pass2"
    When I follow "album 1"
    And I follow "photo 2"
    And I clik rate button 4
    Then I should see text matching "2\.50? \(2 rates\)"
