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
      | name    | status  |
      | album 1 | public  |
      | album 2 | private |
      | album 3 | public |

  Scenario: retrieve categories
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.categories.getAdminList"
    Then the response code should be 200
    And the response has property "result/categories" with size 3
    And the response has property "result/categories/0/name" equals to "album 1"
    And the response has property "result/categories/1/name" equals to "album 2"
    And the response has property "result/categories/2/name" equals to "album 3"
