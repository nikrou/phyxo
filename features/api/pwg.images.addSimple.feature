@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to upload images

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |
    And an album:
      | name   |
      | album 1|
    Then save "category_id"
    And user "user1" can access "album 1"

  Scenario: add an image to a category
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.addSimple" with values:
      | name       | photo 1                  |
      | category   | SAVED:category_id        |
      | FILE:image | features/media/img_1.png |
    Then the response code should be 200

  Scenario: Add an image with infos
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.addSimple" with values:
      | name        | A nice title              |
      | category    | SAVED:category_id         |
      | level       | 2                         |
      | tags        | My first tag, Another tag |
      | FILE:image  | features/media/img_1.png  |
    Then save "image_id" from property "result/image_id"
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_id |
    Then the response has property "result/id" equals to "SAVED:image_id"
    Then the response has property "result/name" equals to "A nice title"
    Then the response has property "result/categories/0/id" equals to "SAVED:category_id"
    Then the response has property "result/categories/0/name" equals to "album 1"
    Then the response has property "result/tags" with size 2
    Then the response has property "result/tags/0/name" equals to "Another tag"
    Then the response has property "result/tags/1/name" equals to "My first tag"

  Scenario: Add an image with a tag containing a comma
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.addSimple" with values:
      | name       | A very nice title              |
      | category   | SAVED:category_id              |
      | tags       | My first tag, My second\, tag |
      | FILE:image | features/media/img_2.png       |
    Then save "image_id" from property "result/image_id"
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_id |
    Then the response has property "result/name" equals to "A very nice title"
    Then the response has property "result/tags/0/name" equals to "My first tag"
    Then the response has property "result/tags/1/name" equals to "My second, tag"

  Scenario: Update an image
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.addSimple" with values:
      | name       | An another title         |
      | category   | SAVED:category_id              |
      | FILE:image | features/media/img_3.png |
    Then save "image_id" from property "result/image_id"
    When I send a POST request to "pwg.images.addSimple" with values:
      | name       | A very nice title        |
      | image_id   | SAVED:image_id           |
      | FILE:image | features/media/img_4.png |
    When I send a POST request to "pwg.images.getInfo" with values:
      | image_id | SAVED:image_id |
    Then the response has property "result/name" equals to "A very nice title"
    Then the response has property "result/file" equals to "img_4.png"
