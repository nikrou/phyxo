Feature: Tags
  In order to manage the gallery
  As an admin
  I need to be able to configure groups

  Background: init
    Given some users:
      | username | password | status    |
      | user1    | pass1    | webmaster |
      | user2    | pass2    | webmaster |
      | user3    | pass3    | webmaster |
      | user4    | pass4    | webmaster |

    And some groups:
      | name   | users       |
      | group1 | user1       |
      | group2 | user1,user2 |
      | group3 | user2,user3 |
      | group4 |             |

    And some albums:
      | name      | parent  | status  |
      | album 1   |         | private |
      | album 1.1 | album 1 | private |
      | album 2   |         | public  |
      | album 2.1 | album 2 | public  |
      | album 2.2 | album 2 | public  |
      | album 3   |         | private |
      | album 3.1 | album 3 | private |
      | album 3.2 | album 3 | private |
      | album 4   |         | public  |

    And group "group4" can access album "album 3.1"

  Scenario: See group members
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/groups"
    Then the group "group1" should have members "user1"
    And the group "group2" should have members "user1,user2"
    And the group "group3" should have members "user2,user3"
    And the group "group4" should have members ""

  Scenario: See album permissions for group
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/groups"
    And I follow group "group1" permissions
    Then the select "cat-true" should contain:
    """
    """
    And the select "cat-false" should contain:
    """
    album 1
    album 1 / album 1.1
    album 3
    album 3 / album 3.1
    album 3 / album 3.2
    """

  Scenario: Authorize album for group
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/groups"
    And I follow group "group1" permissions
    Then I select "album 1" from "cat-false"
    And I press "trueify"
    Then the select "cat-true" should contain:
    """
    album 1
    """
    And the select "cat-false" should contain:
    """
    album 1 / album 1.1
    album 3
    album 3 / album 3.1
    album 3 / album 3.2
    """

  Scenario: Authorize sub-album for group
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/groups"
    And I follow group "group1" permissions
    Then I select "album 1 / album 1.1" from "cat-false"
    And I press "trueify"
    Then the select "cat-true" should contain:
    """
    album 1
    album 1 / album 1.1
    """
    And the select "cat-false" should contain:
    """
    album 3
    album 3 / album 3.1
    album 3 / album 3.2
    """

  Scenario: Unauthorize sub-album for group
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/groups"
    And I follow group "group4" permissions
    Then the select "cat-true" should contain:
    """
    album 3 / album 3.1
    """
    And the select "cat-false" should contain:
    """
    album 1
    album 1 / album 1.1
    album 3
    album 3 / album 3.2
    """
    Then I select "album 3 / album 3.1" from "cat-true"
    And I press "falsify"
    Then the select "cat-true" should contain:
    """
    """
    And the select "cat-false" should contain:
    """
    album 1
    album 1 / album 1.1
    album 3
    album 3 / album 3.1
    album 3 / album 3.2
    """

  Scenario: Unauthorize album for group and sub-album are unauthorized too
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/groups"
    And I follow group "group4" permissions
    Then the select "cat-true" should contain:
    """
    album 3 / album 3.1
    """
    And the select "cat-false" should contain:
    """
    album 1
    album 1 / album 1.1
    album 3
    album 3 / album 3.2
    """
    Then I select "album 3" from "cat-true"
    And I press "falsify"
    Then the select "cat-true" should contain:
    """
    """
    And the select "cat-false" should contain:
    """
    album 1
    album 1 / album 1.1
    album 3
    album 3 / album 3.1
    album 3 / album 3.2
    """
