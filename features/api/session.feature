Feature: Album
  In order to discover the gallery
  As a user
  I need to be able to navigate through albums and sub-albums

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |

  Scenario: Authenticate
    Given I send a "POST" request to "pwg.session.login" with values:
      | username | password |
      | user1    | pass1    |
    Then the response status code should be 200
    And the response is JSON
    And the response body contains JSON:
      """
      {"stat":"ok","result":true}
      """

  Scenario: Bad authentication
    Given I send a "POST" request to "pwg.session.login" with values:
      | username | password     |
      | user1    | bad_password |
    And the response is JSON
    Then the response status code should be 200
    And the response has property "stat" equals to "fail"
    And the response has property "message" equals to "invalid credentials"
    And the response has property "err" equals to 401

  Scenario: See phyxo info
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a "GET" request to "pwg.getVersion"
    Then the response status code should be 200
    And the response is JSON
    And the response has property "stat" equals to "ok"
    And the response has property "result" equals to PHYXO_VERSION
