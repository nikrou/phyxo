Feature: User mail notification
  In order to invite users
  As a webmaster
  I need to be able to subscribe users to new content

  Background: init
    Given a user:
      | username | password | status    | mail_address    |
      | user1    | pass1    | webmaster | user1@phyxo.net |
      | user2    | pass2    | admin     | user2@phyxo.net |
      | user3    | pass3    | normal    | user3@phyxo.net |
      | user4    | pass4    | normal    |                 |

  Scenario: Manage configuration
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/notification"
    When I fill in "Send mail as" with "My name"
    When I fill in "Complementary mail content" with "Some content after news"
    When I check "Send mail on HTML format"
    When I check "Add detailed content"
    When I check "Include display of recent photos grouped by dates"
    And I press "Submit"
    Then the "Send mail as" field should contain "My name"
    Then the "Complementary mail content" field should contain "Some content after news"
    Then the checkbox "Send mail on HTML format" should be checked
    Then the checkbox "Add detailed content" should be checked
    Then the checkbox "Include display of recent photos grouped by dates" should be checked

  Scenario: Admin cannot send user notification
    Given I am logged in as "user2" with password "pass2"
    And I am on "admin/notification"
    Then I should not see link to "Send"

  Scenario: See subscriptions
    Given I am logged in as "user1" with password "pass1"
    And I am on "admin/notification/subscribe"
    Then the select "Subscribed" should contain:
      """
      """
    Then the select "Unsubscribed" should contain:
      """
      user1[user1@phyxo.net]
      user2[user2@phyxo.net]
      user3[user3@phyxo.net]
      """

# Scenario: Manage subscriptions
#   Given I am logged in as "user1" with password "pass1"
#   And I am on "admin/notification/subscribe"
#   When I select "user2[user2@phyxo.net]" from "Unsubscribed"
#   And I press "trueify"
#   Then the select "Subscribed" should contain:
#   """
#   user2[user2@phyxo.net]
#   """
#   And the select "Unsubscribed" should contain:
#   """
#   user1[user1@phyxo.net]
#   user3[user3@phyxo.net]
#   """


