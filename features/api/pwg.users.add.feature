@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  Scenario: add a new user
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.users.getList"
    Then the response code should be 200
    And the response has property "result/users" with size 3
    When I send a POST request to "pwg.users.add" with values:
      | username  | user2 |
      | password  | pass2 |
      | pwg_token |       |
    Then I send a GET request to "pwg.users.getList"
    And the response has property "result/users" with size 4
    And the response has property "result/users/3/username" equals to "user2"
