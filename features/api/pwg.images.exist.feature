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
      | name    | album   |
      | photo 1 | album 1 |
    Then save "image_id"

  Scenario: add an image to a category
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.images.exist" with values:
      | md5sum_list | 91d77ceb2814758cbd5ee991f51b7ecd |
    Then the response code should be 200
    And the response has property "result/91d77ceb2814758cbd5ee991f51b7ecd" equals to "SAVED:image_id"


