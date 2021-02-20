Feature: Metatadas
  In order to manage the gallery
  As an admin
  I need to be able to manage metatadas of iamges

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

    And an album:
      | name      | status  |
      | album 1   | public  |

    And an image:
      | name    | album   | file                             |
      | photo 1 | album 1 | features/media/with-metadata.jpg |

  Scenario: Synchronize metadatas
    Given I am logged in as "user1" with password "pass1"
    And I want to edit "photo 1"
    Then the "date_creation" field date should contain "now"
    Then linked albums "associate[]" should be album "album 1"
    Then I follow "Synchronize metadata"

    # exiftool -S  features/media/with-metadata.jpg| grep '^Subject
    Then tags "tags[]" should be "volcan,montagne,Auvergne"

    # exiftool -S  ~/Images/faune/2019101519093614854702-724691bf.jpg| grep DateCreated
    Then the "date_creation" field should contain "2013-08-18"
