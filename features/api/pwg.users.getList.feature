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

  Scenario: get users
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.users.getList"
    Then the response code should be 200
    And the response has property "result/users" with size 3
    And the response has property "result/users/1/username" equals to "guest"
    And the response has property "result/users/1/level" equals to "0"
    And the response has property "result/users/2/username" equals to "user1"
    And the response has property "result/users/2/level" equals to "8"

  Scenario: get users with selected params
    Given a user:
      | username | password | status    |
      | user2    | pass2    | normal |
    Then save "user_id2"
    And a group:
      | name   |
      | group1 |
    Then save "group_id1"
    And a group:
      | name   |
      | group2 |
    Then save "group_id2"
    And users "SAVED:user_id1" belong to group "SAVED:group_id1"
    And users "SAVED:user_id1" belong to group "SAVED:group_id2"
    And users "SAVED:user_id2" belong to group "SAVED:group_id2"
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.users.getList" with values:
      | display | registration_date,groups |
    Then the response code should be 200
    And the response has property "result/users/0" with size 2
    And the response has property "result/users/1" with size 2
    And the response has property "result/users/2" with size 3
    And the response has property "result/users/0/registration_date"
    And the response has property "result/users/1/registration_date"
    And the response has property "result/users/2/registration_date"
    And the response has no property "result/users/0/groups"
    And the response has no property "result/users/1/groups"
    And the response has property "result/users/2/groups" equals to array "[SAVED:group_id1, SAVED:group_id2]"
    And the response has property "result/users/3/groups" equals to array "[SAVED:group_id2]"
