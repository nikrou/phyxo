@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to update tags to an image

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |

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
      | name    | album   |
      | photo 1 | album 1 |
    Then save "image_id"

  Scenario: add a tag to an image not tagged
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_id |
      | tags     | SAVED:tag_id   |
    Then the response code should be 200
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_id |
    Then the response code should be 200
    And the response has property "result/tags" with size 1
    And the response has property "result/tags/0/name" equals to "tag1"

  Scenario: add a tag
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
    And the response has property "result/tags/1/name" equals to "tag4"

  Scenario: cannot add a tag if not allowed
    And I am authenticated for api as "user1" with password "pass1"
    And config for "tags_permission_add" equals to ""
    And config for "tags_permission_delete" equals to ""
    When I send a POST request to "pwg.images.setRelatedTags" with values:
      | image_id | SAVED:image_id       |
      | tags     | [SAVED:tag_id,tag4 ] |
    Then the response code should be 403
    And the response has property "message" equals to "You are not allowed to add nor delete tags"
