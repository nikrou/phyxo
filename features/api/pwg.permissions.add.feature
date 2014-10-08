@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |
    Then save "user_id1"
    Given a user:
      | username | password | status |
      | user2    | pass2    | normal |
    Then save "user_id2"
    And albums:
      | name    | status  |
      | album 1 | private |
    Then save "album_id1"
    And albums:
      | name    | status  |
      | album 2 | private |
    Then save "album_id2"
    And user "user2" can access "album 2"
    And a group:
      | name   |
      | group1 |
    Then save "group_id"
    And users "SAVED:user_id1" belong to group "SAVED:group_id"

  Scenario: give permissions for an album
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.permissions.add" with values:
      | cat_id    | SAVED:album_id1 |
      | user_id   | SAVED:user_id1  |
      | pwg_token |                 |
    When I send a GET request to "pwg.permissions.getList" with values:
      | cat_id | SAVED:album_id1 |
    Then the response has property "result/categories" with size 1
    Then the response has property "result/categories/0/users" with size 1
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id1"

  Scenario: append permissions for an album
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.permissions.add" with values:
      | cat_id    | SAVED:album_id2 |
      | user_id   | SAVED:user_id1  |
      | pwg_token |                 |
    When I send a GET request to "pwg.permissions.getList" with values:
      | cat_id | SAVED:album_id2 |
    Then the response has property "result/categories" with size 1
    Then the response has property "result/categories/0/users" with size 2
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id2"
    Then the response has property "result/categories/0/users/1" equals to "SAVED:user_id1"

  Scenario: update permissions for same user on same album
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.permissions.add" with values:
      | cat_id    | SAVED:album_id2 |
      | user_id   | SAVED:user_id2  |
      | pwg_token |                 |
    When I send a GET request to "pwg.permissions.getList" with values:
      | cat_id | SAVED:album_id2 |
    Then the response has property "result/categories" with size 1
    Then the response has property "result/categories/0/users" with size 1
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id2"

  Scenario: add permissions for a group for an album
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.permissions.add" with values:
      | cat_id    | SAVED:album_id2 |
      | group_id  | SAVED:group_id  |
      | pwg_token |                 |
    Then the response has property "result/categories" with size 1
    Then the response has property "result/categories/0/users" with size 1
    Then the response has property "result/categories/0/users_indirect" with size 1
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id2"
    Then the response has property "result/categories/0/users_indirect/0" equals to "SAVED:user_id1"

