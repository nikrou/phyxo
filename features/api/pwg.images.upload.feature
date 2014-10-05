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
    And images:
      | name    | album   |
      | photo 1 | album 1 |

  Scenario: upload an image to a category
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.upload" with values:
      | name      | photo 2                  |
      | category  | SAVED:category_id        |
      | pwg_token |                          |
      | FILE:file | features/media/img_2.png |
    Then the response code should be 200
    And the response has property "result/name" equals to "photo 2"
    And the response has property "result/category/label" equals to "album 1"
    And the response has property "result/category/nb_photos" equals to "2"



