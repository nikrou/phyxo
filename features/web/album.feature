Feature: Album
  In order to discover the gallery
  As a user
  I need to be able to navigate through albums and sub-albums

  Background: init
    Given a user:
      | username | password | status |
      | user1    | pass1    | normal |

    And some albums:
      | name      | parent  | comment               |status  |
      | album 1   |         | album 1 description   | public  |
      | album 1.1 | album 1 | album 1.1 description | public  |
      | album 2   |         | album 2 description   | public  |
      | album 2.1 | album 2 | album 2.1 description | public  |
      | album 2.2 | album 2 | album 2.2 description | public  |
      | album 3   |         | album 3 private       | private |
      | album 4   |         | album 4 empty         | public  |


    And some images:
      | name        | album     |
      | photo 1.1   | album 1   |
      | photo 1.1.1 | album 1.1 |
      | photo 2.1   | album 2   |
      | photo 2.2   | album 2   |
      | photo 2.1.1 | album 2.1 |
      | photo 2.1.2 | album 2.1 |
      | photo 2.1.3 | album 2.1 |
      | photo 2.2.1 | album 2.2 |
      | photo 2.2.2 | album 2.2 |
      | photo 2.2.3 | album 2.2 |
      | photo 2.2.4 | album 2.2 |
      | photo 3.1   | album 3   |
      | photo 3.2   | album 3   |

  Scenario: See public albums which contains photos
    Given I am logged in as "user1" with password "pass1"
    And I should see link "album 1"
    And I should see link "album 2"
    But I should not see link "album 3"
    And I should not see link "album 4"

  Scenario: See public albums description and number of images
    Given I am logged in as "user1" with password "pass1"
    And I am on the homepage
    Then I should see "album 1 description" for "album 1" description
    And I should see "album 2 description" for "album 2" description
    And I should see "one photo, one photo in one sub-album" for "album 1" number of images
    And I should see "2 photos, 7 photos in 2 sub-albums" for "album 2" number of images

  Scenario: See public albums description and number of images in sub-album
    Given I am logged in as "user1" with password "pass1"
    And I follow "album 2"
    Then I should see "album 2.1 description" for "album 2.1" description
    And I should see "album 2.2 description" for "album 2.2" description
    And I should see photo "photo 2.1"
    And I should see photo "photo 2.2"

