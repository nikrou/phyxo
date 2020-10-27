Feature: Tag
  In order to find image in the gallery
  As a user
  I need to be able to find them by tags

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | normal    |
      | user2    | pass2    | webmaster |
      | user3    | pass3    | normal    |
    And some albums:
      | name    |
      | album 1 |
      | album 2 |
    And some images:
      | file                     | name    | album   | tags    |
      | features/media/img_1.jpg | photo 1 | album 1 | tag 1      |
      | features/media/img_2.jpg | photo 2 | album 1 | tag 2      |
      | features/media/img_3.jpg | photo 3 | album 2 | [tag 1,tag 2] |
      | features/media/img_4.jpg | photo 4 | album 2 | [tag 1,tag 3] |
      | features/media/img_5.jpg | photo 5 | album 2 | tag 5      |
    And user "user1" can access album "album 1"
    And user "user1" can access album "album 2"
    And user "user2" can access album "album 1"
    And user "user2" can access album "album 2"
    And user "user3" can access album "album 2"

  Scenario: Find image by a tag
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Tags"
    And I follow "tag 1"
    Then I should see "one tag tag 1"
    And I should see photo "photo 1"
    And I should not see photo "photo 2"
    And I should see photo "photo 3"
    And I should see photo "photo 4"

  Scenario: Find image by two tag
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Tags"
    And I follow "tag 1"
    Then I should see "one tag tag 1"
    Then I should see photo "photo 1"
    And I should not see photo "photo 2"
    And I should see photo "photo 3"
    And I should see photo "photo 4"
    When I follow "tag 3"
    Then I should see "2 tags tag 1 + tag 3"
    Then I should not see photo "photo 1"
    And I should not see photo "photo 2"
    And I should not see photo "photo 3"
    But I should see photo "photo 4"

  # permissions
  Scenario: Allow user to add tag
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 1"
    Then I should not be able to edit tags
    When config for "tags_permission_add" equals to "normal"
    And I reload the page
    Then I should be able to edit tags

  Scenario: Add tags can be allowed but not for all statuses
    And config for "tags_permission_add" equals to "webmaster"
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 1"
    Then I should not be able to edit tags

    Given I am logged in as "user2" with password "pass2"
    When I follow "album 1"
    And I follow "photo 1"
    Then I should be able to edit tags

  Scenario: Show tags mark as added (but not validated) for creator only, when show_pending_added_tags is true
    Given I am logged in as "user1" with password "pass1"
    And config for "show_pending_added_tags" of type "boolean" equals to "true"
    And I add tag "tag 4" on photo "photo 5" by user "user1" not validated
    When I follow "album 2"
    And I follow "photo 5"
    Then I should see tag "tag 4"

    # Other users cannot see pending added tags
    Given I am logged in as "user3" with password "pass3"
    When I follow "album 2"
    And I follow "photo 5"
    Then I should not see tag "tag 4"

  Scenario: Don't show tags mark as added but not validated, when show_pending_added_tags is false
    Given I am logged in as "user1" with password "pass1"
    And config for "show_pending_added_tags" of type "boolean" equals to "false"
    And I add tag "tag 4" on photo "photo 5" by user "user1" not validated
    When I follow "album 2"
    And I follow "photo 5"
    Then I should not see tag "tag 4"

    # Other users cannot see pending added tags
    Given I am logged in as "user3" with password "pass3"
    When I follow "album 2"
    And I follow "photo 5"
    Then I should not see tag "tag 4"

  Scenario: Show tags mark as deleted but not validated, when show_pending_deleted_tags is true
    Given I am logged in as "user1" with password "pass1"
    And config for "publish_tags_immediately" of type "boolean" equals to "false"
    And config for "show_pending_deleted_tags" of type "boolean" equals to "true"
    And I remove tag "tag 5" on photo "photo 5" by user "user1" not validated
    When I follow "album 2"
    And I follow "photo 5"
    Then I should see tag "tag 5"

  Scenario: Don't show tags mark as deleted but not validated, when show_pending_deleted_tags is false
    Given I am logged in as "user1" with password "pass1"
    And config for "publish_tags_immediately" of type "boolean" equals to "false"
    And config for "show_pending_deleted_tags" of type "boolean" equals to "false"
    And I remove tag "tag 5" on photo "photo 5" by user "user1" not validated
    When I follow "album 2"
    And I follow "photo 5"
    Then I should see tag "tag 5"
