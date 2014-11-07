@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  Scenario: add a category
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.categories.add" with values:
      | name | album 1 |
    Then the response code should be 200
    And the response has property "result/info" equals to "Virtual album added"
    When I send a GET request to "pwg.categories.getAdminList"
    And the response has property "result/categories" with size 1
    And the response has property "result/categories/0/name" equals to "album 1"
