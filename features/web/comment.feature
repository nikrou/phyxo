Feature: Comment
  In order to add comments to the gallery
  As a user
  I need to be able to add comments to picture

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
      | name    | album   |
      | photo 1 | album 1 |
      | photo 2 | album 2 |
      | photo 3 | album 3 |
    And user "user1" can access album "album 1"
    And user "user1" can access album "album 3"
    And user "user2" can access album "album 2"
    And user "user1" cannot access album "album 2"
    And user "user2" cannot access album "album 1"
    And a comment "a good comment" on "photo 2" by "user2"

  Scenario: See previous comment
    Given I am logged in as "user2" with password "pass2"
    When I follow "album 2"
    And I follow "photo 2"
    Then I should see "a good comment"

  # @TODO: add more scenarios with variations for config keys
  Scenario: Add a comment
    Given I am logged in as "user1" with password "pass1"
    And config for "key_comment_valid_time" of type "integer" equals to "0"
    And config for "comments_email_mandatory" of type "boolean" equals to "false"
    And config for "comments_validation" of type "boolean" equals to "false"
    And config for "email_admin_on_comment" of type "boolean" equals to "false"
    When I follow "album 1"
    And I follow "photo 1"
    And I add a comment :
      """
      What a good pics !
      """
    Then I should see "Your comment has been registered"
    And I should see "What a good pics !"

  # @TODO: add more scenarios with variations for config keys
  Scenario: Add a comment when comments need admins validation
    Given I am logged in as "user1" with password "pass1"
    And config for "key_comment_valid_time" of type "integer" equals to "0"
    And config for "comments_email_mandatory" of type "boolean" equals to "false"
    And config for "comments_validation" of type "boolean" equals to "true"
    And config for "email_admin_on_comment_validation" of type "boolean" equals to "false"
    When I follow "album 1"
    And I follow "photo 1"
    And I add a comment :
      """
      What a good pics !
      """
    Then I should see "Your comment has been registered"
    And I should see "An administrator must authorize your comment before it is visible"
    And I should not see "What a good pics !"

  # @see bug http://piwigo.org/bugs/view.php?id=2887
  Scenario: I cannot see private photos on user comments page
    Given I am logged in as "user1" with password "pass1"
    Given I am on homepage
    Then I should not be allowed to go to album "album 2"
    When I am on homepage
    And I follow "Comments"
    # And I fill in "Author" with "user2"
    # And I press "Filter and display"
    Then I should not see "a good comment"

  Scenario: I search comments in album with no comments
    Given I am logged in as "user1" with password "pass1"
    Given I am on homepage
    And I follow "comments"
    When I select "album 3" from "Album"
    And I press "Filter and display"
    Then I should see "No comments for that search"

  Scenario: I search comments in album with some comments
    Given I am logged in as "user2" with password "pass2"
    Given I am on homepage
    And I follow "comments"
    When I select "album 2" from "Album"
    And I press "Filter and display"
    Then I should see "a good comment"

