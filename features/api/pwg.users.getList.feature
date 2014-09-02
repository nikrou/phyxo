@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  Scenario: get users
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.users.getList"
    Then the response code should be 200
    And the response has property "result/users" with size 3
    And the response has property "result/users/1/username" equals to "guest"
    And the response has property "result/users/1/level" equals to "0"
    And the response has property "result/users/2/username" equals to "user1"
    And the response has property "result/users/2/level" equals to "8"
