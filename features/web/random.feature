Feature: My favorites pictures
  In order to discover the gallery
  As a user
  I need to be able to see random pictures

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
    And an album:
      | name    |
      | album 1 |
    And some images:
      | name    | album   |
      | photo 1 | album 1 |
      | photo 2 | album 1 |
    And user "user1" can access album "album 1"

  Scenario: Add picture to favorites
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Random photos"
    Then I should see "Random photos"
    # @TODO: need to add something useful
