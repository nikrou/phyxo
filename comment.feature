Feature: Comment
  In order to add comments to the gallery
  As a user
  I need to be abble to add comments to picture

  Scenario: init
    Given a user:
      | username | user1  |
      | password | pass1  |
      | status   | normal |
    And an album:
      | name | album 1|
    And an image:
      | name  | photo 1 |
      | album | album 1 |
    And "user1" can access "album 1"
    And a comment "a good comment" on "photo 1" by "user1"


  @slow
  Scenario: Add a comment
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 1"
    And I add a comment :
      """
      What a good pics !
      """
    Then I should see "Your comment has been registered"
