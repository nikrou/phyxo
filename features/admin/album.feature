Feature: Create new access
  In order share my albums
  As a webmaster
  I need to be able to create new user

  Background: init
    Given a user:
      | username | password | status    |
      | user1    | pass1    | webmaster |

  @javascript
  Scenario: Create new album
    Given I am logged in as "user1" with password "pass1"
    When I go to "admin/index.php?page=photos_add"
    And I follow "create a new album"
    Then I wait for message "infos" to appear
    # wait for modal to appear instead.
    Then I should see "create a new album" in the "#cboxWrapper" element
    And I fill in "category_name" with "My ALbum"
    And I press "Create"
    Then I wait for message "infos" to appear
    Then I should not see "create a new album" in the "#cboxWrapper" element
    And the "#albumSelection" element should contain "My Album"
