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
    And albums:
      | name    | status  |
      | album 3 | private |
    Then save "album_id3"
    And a group:
      | name   |
      | group1 |
    Then save "group_id"
    And users "SAVED:user_id1,SAVED:user_id2" belong to group "SAVED:group_id"
    And user "user1" can access "album 1"
    And user "user1" can access "album 3"
    And user "user2" can access "album 2"
    And user "user2" can access "album 3"
    And group "group1" can access "album 2"

  Scenario: list permissions
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.permissions.getList"
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id1"

  Scenario: multiple permissions filter by category
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.permissions.getList" with values:
      | cat_id | SAVED:album_id2 |
    Then the response has property "result/categories/0/users" with size 1
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id2"

  Scenario: multiple permissions filter by category
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.permissions.getList" with values:
      | cat_id | SAVED:album_id3 |
    Then the response has property "result/categories" with size 1
    Then the response has property "result/categories/0/users" with size 2
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id1"
    Then the response has property "result/categories/0/users/1" equals to "SAVED:user_id2"

  Scenario: multiple permissions filter by user
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.permissions.getList" with values:
      | user_id | SAVED:user_id2 |
    Then the response has property "result/categories" with size 2
    Then the response has property "result/categories/0/users" with size 2
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id1"
    Then the response has property "result/categories/0/users/1" equals to "SAVED:user_id2"
    Then the response has property "result/categories/1/users/0" equals to "SAVED:user_id2"

  Scenario: access album by group
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.permissions.getList" with values:
      | cat_id | SAVED:album_id2 |
    Then the response has property "result/categories/0/users" with size 1
    Then the response has property "result/categories/0/users/0" equals to "SAVED:user_id2"
    Then the response has property "result/categories/0/users_indirect" with size 2
    Then the response has property "result/categories/0/users_indirect/0" equals to "SAVED:user_id1"
    Then the response has property "result/categories/0/users_indirect/1" equals to "SAVED:user_id2"
    Then the response has property "result/categories/0/groups/0" equals to "SAVED:group_id"
