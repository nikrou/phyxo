Feature: My favorites pictures
  In order to show easily my favorites pictures
  As a user
  I need to be able to save my favorites pictures

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | normal    |
      | user2    | pass2    | webmaster |
    And an album:
      | name    |
      | album 1 |
      | album 2 |
    And some images:
      | name    | album   |
      | photo 1 | album 1 |
      | photo 2 | album 1 |
      | photo 3 | album 2 |
    And user "user1" can access album "album 1"

  Scenario: Add picture to favorites
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    When I follow "album 1"
    And I follow "photo 1"
    And I follow "Add this photo to your favorites"
    Then I go to the homepage
    When I follow "Your favorites"
    Then I should see photo "photo 1"

  Scenario: Remove a picture from favorites
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    When I follow "album 1"
    And I follow "photo 1"
    And I follow "Add this photo to your favorites"
    Then I follow "album 1"
    And I follow "photo 2"
    And I follow "Add this photo to your favorites"
    When I follow "Your favorites"
    Then I should see photo "photo 1"
    Then I should see photo "photo 2"

    And I follow "photo 2"
    And I follow "Delete this photo from your favorites"
    When I follow "Your favorites"
    Then I should not see photo "photo 2"

  Scenario: Remove all pictures from favorites
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    When I follow "album 1"
    And I follow "photo 1"
    And I follow "Add this photo to your favorites"
    Then I follow "album 1"
    And I follow "photo 2"
    And I follow "Add this photo to your favorites"
    When I follow "Your favorites"
    And I follow "Delete all photos from your favorites"
    Then I should not see photo "photo 1"
    Then I should not see photo "photo 2"

  Scenario: Image in album not allowed should not be displayed in favorites
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 1"
    And I follow "Add this photo to your favorites"
    Then I follow "album 2"
    And I follow "photo 3"
    And I follow "Add this photo to your favorites"
    Then I should see photo "photo 1"
    Then I should see photo "photo 3"

    # make album 2 private
    Given I am logged in as "user2" with password "pass2"
    And I go to "admin/albums"
    And I follow album "album 2" Edit
    And I follow "Permissions"
    Then I select "private" from "status"
    And I press "Save Settings"
    Given I am logged in as "user1" with password "pass1"
    When I follow "Your favorites"
    Then I should see photo "photo 1"
    But I should not see photo "photo 3"

