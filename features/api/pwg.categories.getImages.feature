@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to use the api

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |
    And an album:
      | name   |
      | album 1|
    And images:
      | name    | album   |
      | photo 1 | album 1 |
      | photo 2 | album 1 |
      | photo 3 | album 1 |
      | photo 4 | album 1 |
    And "user1" can access "album 1"

  Scenario: Get images in album
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.categories.getImages" with values:
      | album | album 1 |
    Then the response code should be 200
    And the response is JSON
    And the response has property "result/images"
    And the response has property "result/images" with size 4
    And the response has property "result/images/0/name" equals to "photo 1"
    And the response has property "result/images/1/name" equals to "photo 2"
    And the response has property "result/images/2/name" equals to "photo 3"
    And the response has property "result/images/3/name" equals to "photo 4"
