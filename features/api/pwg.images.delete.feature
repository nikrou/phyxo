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
      | name    |
      | album 1 |
    And images:
      | name    | album   | file                     |
      | photo 1 | album 1 | features/media/img_1.png |
      | photo 2 | album 1 | features/media/img_2.png |
    Then save "image_id"
    And user "user1" can access "album 1"

  Scenario: add an image to a category
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.delete" with values:
      | image_id  | SAVED:image_id |
      | pwg_token |                |
    Then the response code should be 200
    When I send a POST request to "pwg.images.exist" with values:
      | md5sum_list | ba76685694786b04eb77002248dad3c0 |
    Then the response code should be 200
    And the response has no property "result/ba76685694786b04eb77002248dad3c0"
