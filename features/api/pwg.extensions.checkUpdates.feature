@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  Scenario: Get Phyxo version
    Given I am authenticated for api as "user1" with password "pass1"
    And I send a GET request to "pwg.extensions.checkUpdates"
    Then the response code should be 200
    And the response is JSON