@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |
    And an album:
      | name    |
      | album 1 |
    Then save "album_id"
    And an album:
      | name         |
      | album parent |
    Then save "parent_id"

  Scenario: move a category
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.categories.move" with values:
      | category_id | SAVED:album_id  |
      | parent      | SAVED:parent_id |
      | pwg_token   |                 |
    Then the response code should be 200
    When I send a GET request to "pwg.categories.getAdminList"
    And the response has property "result/categories" with size 2
    And the response has property "result/categories/0/name" equals to "album parent"
    And the response has property "result/categories/1/name" equals to "album 1"
    And the response has property "result/categories/1/uppercats" equals to "SAVED:parent_id,SAVED:album_id"

