@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to upload images

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  Scenario: add a tag
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.tags.add" with values:
      | name | A new tag |
    Then the response code should be 200
    And the response has property "result/info" equals to 'Tag "A new tag" has been added'

  Scenario: add an existing tag
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a POST request to "pwg.tags.add" with values:
      | name | A new tag |
    Then the response code should be 200
    When I send a POST request to "pwg.tags.add" with values:
      | name | A new tag |
    Then the response code should be 500
    And the response has property "message" equals to 'Tag "A new tag" already exists'


