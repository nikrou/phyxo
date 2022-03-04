Feature: Album
  In order to manage the gallery
  As an admin
  I need to be able to configure albums

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

    And some albums:
      | name    | parent | comment             | status |
      | album 1 |        | album 1 description | public |
      | album 2 |        | album 2 description | public |

    And some images:
      | name    | album   |
      | photo 1 | album 1 |
      | photo 2 | album 2 |

  Scenario: Move album in sub-album
    Given I am logged in as "user1" with password "pass1"
    And I should see link "album 1" in ".main-content"
    And I should see link "album 2" in ".main-content"

    When I go to "admin/albums/move"
    Then the select "Virtual albums to move" should contain:
      """
      album 1
      album 2
      """
    When  I select "album 2" from "Virtual albums to move"
    And I select "album 1" from "New parent album"
    And I press "Submit"
    When I go to the homepage
    And I should see link "album 1" in ".main-content"
    And I should not see link "album 2"
    When I follow "album 1"
    And I should see link "album 2"

  Scenario: Delete album
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/albums"
    Then I should see 2 ".album" elements
    When I follow "delete album" for album "album 1"
    Then I should see 1 ".album" elements
