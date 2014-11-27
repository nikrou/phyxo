Feature: Tag
  In order to find image in the gallery
  As a user
  I need to be able to find them by tags

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | normal    |
      | user2    | pass2    | webmaster |
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
      | features/media/img_5.png | photo 5 | album 2 | t5      |
    Then save "image_id"
    And user "user1" can access "album 1"
    And user "user1" can access "album 2"
    And user "user2" can access "album 1"
    And user "user2" can access "album 2"

  Scenario: Find image by a tag
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Tags"
    And I follow "t1"
    Then I should see text matching "Tag.*t1.*[3]"
    And I should see "photo 1"
    And I should see "photo 3"
    And I should see "photo 4"

  Scenario: Find image by two tag
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    And I follow "Tags"
    And I follow "t1"
    Then I should see text matching "Tag.*t1.*[3]"
    Then I should see "photo 1"
    And I should see "photo 3"
    And I should see "photo 4"
    When I follow "t3"
    Then I should see text matching "Tags.*t1.*\+.*t3.*[1]"
    Then I should not see "photo 1"
    And I should not see "photo 3"

  # permissions
  Scenario: Allow user to add tag
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 1"
    Then I should not see an ".edit-tags" element
    When config for "tags_permission_add" equals to "normal"
    And I reload the page
    Then I should see "Tags" in the ".edit-tags" element

  Scenario: Add tags can be allowed but not for a status
    Given I am logged in as "user1" with password "pass1"
    When I follow "album 1"
    And I follow "photo 1"
    When config for "tags_permission_add" equals to "webmaster"
    Then I should not see an ".edit-tags" element
    Given I am logged in as "user2" with password "pass2"
    When I follow "album 1"
    And I follow "photo 1"
    Then I should see an ".edit-tags" element

  Scenario: Don't show tags mark as added but not validated
    Given I am logged in as "user1" with password "pass1"
    And add tag "tag 4" on "SAVED:image_id" not validated
    When I follow "album 2"
    And I follow "photo 5"
    Then the "#Tags" element should not contain "tag 4"

  Scenario: Show tags mark as deleted but not validated
    Given I am logged in as "user1" with password "pass1"
    And remove tag "t5" on "SAVED:image_id" not validated
    When I follow "album 2"
    And I follow "photo 5"
    # Then print last response
    Then the "#Tags" element should contain "t5"
