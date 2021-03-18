Feature: Image
  In order to discover the gallery
  As a user
  I need to be able to show images in differents size

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |

    And an album:
      | name      | status  |
      | album 1   | public  |

    And an image:
      | name        | album     |
      | photo 1.1   | album 1   |


  Scenario: See images
    Given I am logged in as "user1" with password "pass1"
    And I follow "album 1"
    And I follow "photo 1"
    And I follow image of type "small"
    Then the response status code should be 200



