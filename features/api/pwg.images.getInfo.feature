@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to get info for an image

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |

    And albums:
      | name    | status |
      | album 1 | public |

    And images:
      | name    | album   | tags           |
      | photo 1 | album 1 | [ tag1, tag2 ] |
    Then save "image_id"

  Scenario: get info for an image
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_id |
    Then the response code should be 200
    And the response has property "result/tags" with size 2
    And the response has property "result/tags/0/name" equals to "tag1"
    And the response has property "result/tags/1/name" equals to "tag2"
    And the response has property "result/name" equals to "photo 1"
    And the response has property "result/categories/0/name" equals to "album 1"
