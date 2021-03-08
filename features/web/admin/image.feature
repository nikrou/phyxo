Feature: Image
  In order to manage the gallery
  As an admin
  I need to be manage images

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

    And some albums:
      | name      | parent  | comment               | status  |
      | album 1   |         | album 1 description   | public  |
      | album 2   |         | album 2 description   | public  |

    And some images:
      | name    | album     |
      | photo 1 | album 1   |
      | photo 2 | album 2   |

  Scenario: Delete an image
    Given I am logged in as "user1" with password "pass1"
    When I am on homepage
    When I follow "album 1"
    And I follow "photo 1"
    And I want to edit "photo 1"
    Then linked albums "associate[]" should be album "album 1"
    And I should see "Visited 1 times"
    When I press "delete photo"
    Then I should see "Photo deleted"
