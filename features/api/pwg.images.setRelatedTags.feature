@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to update tags to an image

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
    Then save "user_id"
    And config for "tags_permission_add" equals to "normal"
    And config for "tags_permission_delete" equals to "normal"

    And albums:
      | name    | status |
      | album 1 | public |

    And tags:
      | name |
      | tag1 |
    Then save "tag_id"

    And images:
      | name    | album   | tags  |
      | photo 1 | album 1 | a tag |
    Then save "image_tagged"
    And images:
      | name    | album   |
      | photo 2 | album 1 |
    Then save "image_not_tagged"

  Scenario: add an existing tag to an image not tagged
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_not_tagged |
      | tags     | SAVED:tag_id           |
    Then the response code should be 200
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_not_tagged |
    Then the response code should be 200
    And the response has property "result/tags" with size 1
    And the response has property "result/tags/0/name" equals to "tag1"
    And the response has property "result/tags/0/validated" equals to "true" of type boolean

  Scenario: add a new tag to an image not tagged
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_not_tagged |
      | tags     | a new tag              |
    Then the response code should be 200
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_not_tagged |
    Then the response code should be 200
    And the response has property "result/tags" with size 1
    And the response has property "result/tags/0/name" equals to "a new tag"
    And the response has property "result/tags/0/validated" equals to "true" of type boolean

  Scenario: add a tag to an already tagged image
    Given tags:
      | name |
      | tag2 |
    Then save "tag_id"
    Given images:
      | name    | album   | tags        |
      | photo 2 | album 1 | [tag2,tag3] |
    Then save "image_id"
    And I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_id       |
      | tags     | [SAVED:tag_id,tag4 ] |
    Then the response code should be 200
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_id |
    Then the response code should be 200
    And the response has property "result/tags" with size 2
    And the response has property "result/tags/0/name" equals to "tag2"
    And the response has property "result/tags/0/validated" equals to "true" of type boolean
    And the response has property "result/tags/1/name" equals to "tag4"
    And the response has property "result/tags/1/validated" equals to "true" of type boolean

  Scenario: remove a tag from an image
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_tagged |
    Then the response code should be 200
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_tagged |
    Then the response has property "result/tags" with size 0

  Scenario: cannot add a tag if not allowed
    Given I am authenticated for api as "user1" with password "pass1"
    And config for "tags_permission_add" equals to ""
    And config for "tags_permission_delete" equals to ""
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_not_tagged |
      | tags     | [SAVED:tag_id,tag4 ]   |
    Then the response code should be 403
    And the response has property "message" equals to "You are not allowed to add nor delete tags"

  Scenario: mark tag as not validated, and to be added, if user not allowed to publish immediately
    Given I am authenticated for api as "user1" with password "pass1"
    And config for "tags_permission_add" equals to "normal"
    And config for "publish_tags_immediately" equals to "0"
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_not_tagged |
      | tags     | SAVED:tag_id           |
    Then the response code should be 200
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_not_tagged |
    Then the response has property "result/tags" with size 0

  Scenario: mark tag as not validated, and to be deleted, if user not allowed to delete immediately
    Given I am authenticated for api as "user1" with password "pass1"
    And config for "tags_permission_delete" equals to "normal"
    And config for "delete_tags_immediately" equals to "0"
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_tagged |
    Then the response code should be 200
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_tagged |
    Then the response has property "result/tags/0/validated" equals to "false" of type boolean
    Then the response has property "result/tags/0/created_by" equals to "SAVED:user_id"
    Then the response has property "result/tags/0/status" equals to "0"
