Feature: My favorites pictures
  In order to show easily my favorites pictures
  As a user
  I need to be able to save my favorites pictures

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
    And an album:
      | name    |
      | album 1 |
    And an image:
      | name    | album   |
      | photo 1 | album 1 |
    And user "user1" can access "album 1"

  Scenario: Add picture to favorites
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    When I follow "album 1"
    And I follow "photo 1"
    And I follow "add this photo to your favorites"
    Then I go to the homepage
    When I follow "Your favorites"
    Then I should see "photo 1"
    And I should see "Favorites [1]"
