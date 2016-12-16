Feature: Identification
  In order to authenticate in the gallery
  As a user
  I need to be able to access identification page

  Scenario: No error message on identification page for guest even if guest_access is false
    When config for "guest_access" equals to "false" of type "boolean"
    And I go to a protected page
    Then I should not see "You are not authorised to access the requested page"
