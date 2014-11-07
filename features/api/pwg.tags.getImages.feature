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
      | name    | album   | tags         |
      | photo 1 | album 1 | tag1         |
      | photo 2 | album 2 | tag2         |
      | photo 3 | album 2 | [tag2, tag3] |

    Scenario: find an image by a tag
      Given I am authenticated for api as "user1" with password "pass1"
      When I send a POST request to "pwg.tags.getImages" with values:
        | tag_name | tag1 |
      Then the response code should be 200
      And the response has property "result/images" with size 1
      And the response has property "result/images/0/name" equals to "photo 1"

    Scenario: get images by a tag
      Given I am authenticated for api as "user1" with password "pass1"
      When I send a POST request to "pwg.tags.getImages" with values:
        | tag_name | tag2      |
      Then the response code should be 200
      And the response has property "result/images" with size 2
      And the response has property "result/images/0/name" equals to "photo 2"
      And the response has property "result/images/1/name" equals to "photo 3"

    Scenario: get images by a tag and sort them
      Given I am authenticated for api as "user1" with password "pass1"
      When I send a POST request to "pwg.tags.getImages" with values:
        | tag_name | tag2      |
        | order    | file desc |
      Then the response code should be 200
      And the response has property "result/images" with size 2
      And the response has property "result/images/0/name" equals to "photo 2"
      And the response has property "result/images/1/name" equals to "photo 3"
