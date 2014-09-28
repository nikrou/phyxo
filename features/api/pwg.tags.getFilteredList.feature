@api
Feature: API
  In order to manage my gallery
  As a developer
  I need to be able to see tags

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |

    And albums:
      | name    | status |
      | album 1 | public |
      | album 2 | public |

    And images:
      | name    | album   | tags        |
      | photo 1 | album 1 | tag1        |
      | photo 2 | album 1 | tag2        |
      | photo 3 | album 2 | [tag1,tag2] |
      | photo 4 | album 2 | [tag1,tag3] |

    And tags:
      | name        |
      | another tag |
      | subtag2     |

  Scenario: get all tags
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.tags.getFilteredList"
    Then the response code should be 200
    And the response is JSON
    And the response has property "result/tags"
    And the response has property "result/tags" with size 5
    # alpha sorted
    And the response has property "result/tags/0/name" equals to "another tag"
    And the response has property "result/tags/1/name" equals to "subtag2"
    And the response has property "result/tags/2/name" equals to "tag1"
    And the response has property "result/tags/3/name" equals to "tag2"
    And the response has property "result/tags/4/name" equals to "tag3"

  Scenario: searching for tags
    Given I am authenticated for api as "user1" with password "pass1"
    When I send a GET request to "pwg.tags.getFilteredList" with values:
      | q | tag2 |
    Then the response code should be 200
    And the response has property "result/tags" with size 2
