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
    And some images:
      | name    | album   |
      | photo 1 | album 1 |
      | photo 2 | album 2 |
    And user "user1" can access album "album 1"
    And user "user2" can access album "album 2"
    And user "user1" cannot access album "album 2"
    And user "user2" cannot access album "album 1"
    And a comment "a good comment" on "photo 2" by "user2"

  Scenario: See previous comment
    Given I am logged in as "user2" with password "pass2"
    When I follow "album 2"
    And I follow "photo 2"
    Then I should see "a good comment"

  Scenario: Add a comment
    Given I am logged in as "user1" with password "pass1"
    And config for "key_comment_valid_time" equals to "0"
    And config for "comments_email_mandatory" equals to "false"
    And config for "comments_validation" equals to "false"
    And config for "email_admin_on_comment" equals to "false"
    When I follow "album 1"
    And I follow "photo 1"
    And I add a comment :
      """
      What a good pics !
      """
    Then I should see "Your comment has been registered"
    And I should see "What a good pics !"

  # @see bug http://piwigo.org/bugs/view.php?id=2887
  Scenario: I cannot see private photos on user comments page
    Given I am logged in as "user1" with password "pass1"
    Given I am on homepage
    Then I should not be allowed to go to album "album 2"
    When I am on homepage
    And I follow "Comments"
    And I fill in "Author" with "user2"
    And I press "Filter and display"
    Then I should not see "a good comment"
