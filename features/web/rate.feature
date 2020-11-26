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
    And some images:
      | name    |  album  |
      | photo 1 | album 1 |
      | photo 2 | album 1 |
    And user "user1" can access album "album 1"
    And user "user2" can access album "album 1"

  Scenario: Rate a picture
    Given I am logged in as "user1" with password "pass1"
    And config for "rate" of type "boolean" equals to "true"
    When I follow "album 1"
    And I follow "photo 1"
    And I select "3" from "rating"
    Then I press "Rate"
    Then I should see "one rate"

  Scenario: Multiples rates for the same picture
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 2"
    And I select "1" from "rating"
    Then I press "Rate"
    Then I should see text matching "1 \(one rate\)"
    Given I am logged in as "user2" with password "pass2"
    When I follow "album 1"
    And I follow "photo 2"
    Then I press "Rate"
    Then I should see text matching "0.5 \(2 rates\)"
