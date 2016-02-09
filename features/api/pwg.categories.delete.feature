@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |
    And albums:
      | name    |
      | album 1 |

  Scenario: add a category
    Given I am authenticated for api as "user1" with password "pass1"
    Given an album:
      | name    |
      | album 2 |
    Then save "album_id"
    When I send a GET request to "pwg.categories.getAdminList"
    Then the response code should be 200
    And the response has property "result/categories" with size 2
    And the response has property "result/categories/0/name" equals to "album 1"
    And the response has property "result/categories/1/name" equals to "album 2"
    # And now delete an album
    When I send a POST request to "pwg.categories.delete" with values:
      | category_id | SAVED:album_id |
      | pwg_token   |                |
    Then the response code should be 200
    And I send a GET request to "pwg.categories.getAdminList"
    Then the response code should be 200
    And the response has property "result/categories" with size 1
    And the response has property "result/categories/0/name" equals to "album 1"
