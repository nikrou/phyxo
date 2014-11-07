@api
Feature: API
  In order to get infos from my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | normal    |
      | admin1   | pass1    | webmaster |

  Scenario: get infos with normal user gives access denied
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.getInfos"
    Then the response code should be 401
    And the response has property "message" equals to "Access denied"

  Scenario: get infos from gallery
    Given I am authenticated for api as "admin1" with password "pass1"
    When I send a GET request to "pwg.getInfos"
    Then the response code should be 200
    And the response has property "result/infos"
