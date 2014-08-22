Feature: Tag
  In order to find image in the gallery
  As a user
  I need to be able to find them by tags

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
    And albums:
      | name    |
      | album 1 |
      | album 2 |
    And images:
      | file                     | name    | album   | tags    |
      | features/media/img_1.png | photo 1 | album 1 | t1      |
      | features/media/img_2.png | photo 2 | album 1 | t2      |
      | features/media/img_3.png | photo 3 | album 2 | [t1,t2] |
      | features/media/img_4.png | photo 4 | album 2 | [t1,t3] |
    And "user1" can access "album 1"
    And "user1" can access "album 2"

  Scenario: Find image by a tag
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Tags"
    And I follow "t1"
    Then I should see "Tag t1 [3]"
    And I should see "photo 1"
    And I should see "photo 3"
    And I should see "photo 4"

  Scenario: Find image by two tag
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Tags"
    And I follow "t1"
    Then I should see "Tag t1 [3]"
    Then I should see "photo 1"
    And I should see "photo 3"
    And I should see "photo 4"
    When I follow "t3"
    Then I should see "Tags t1 + t3 [1]"
    Then I should not see "photo 1"
    And I should not see "photo 3"
