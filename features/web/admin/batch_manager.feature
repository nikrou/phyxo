Feature: Batch manager
  In order to manage the gallery
  As an admin
  I need to be manage several images at the same time

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

    And some albums:
      | name      | comment               | status  |
      | album 1   | album 1 description   | public  |
      | album 2   | album 2 description   | public  |
      | album 3   | album 3 description   | public  |

    And some images:
      | name    | album     |
      | photo 1 | album 1   |
      | photo 2 | album 2   |

    And some images:
      | name    |
      | photo 3 |
      | photo 4 |
      | photo 5 |

  Scenario: Associate images to an album
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/albums"
    Then I should see "one photo" for album "album 1"
    Then I should see "no photos" for album "album 3"
    And I am on "admin/batch/global"
    Then I select "With no album" from "filter_prefilter"
    And I press "Refresh photo set"
    Then I should see 3 ".thumbnails > .thumbnail" elements
    # @TODO: refactoring in admin/theme/template/batch_manager_global.html.twig so action works without javascript
    # Then I select "photo 3" in thumbnails
    # And I select "photo 4" in thumbnails
    # And I select "photo 5" in thumbnails
    # Then I select "Associate to album" from "selectAction"
    # Then I should see "on the 3 selected photos"
    # And I press "Apply action"
    # When I go to "admin/albums"
    # Then I should see "3 photos" for album "album 3"
