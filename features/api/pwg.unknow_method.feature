@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |

  Scenario: Get Phyxo version
    Given I am authenticated for api as "user1" with password "pass1"
    And I send a GET request to "pwg.getDummyMethod"
    Then the response code should be 501
    And the response is JSON
    And the response has property "message" equals to "Method name is not valid"
