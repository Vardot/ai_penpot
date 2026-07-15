Feature: AI Penpot module - the single configuration page
  As a site builder
  I want one settings page to point the module at my Penpot instance and file
  So that the Drupal Canvas AI assistant can read my designs

  # AI Penpot ships exactly one config page at /admin/config/ai/penpot with a
  # "Penpot connection" group (the instance URL, the access-token Key and the
  # default file id) and a "Test connection" group with a "Test Penpot
  # connection" action. Everything else runs through the Drupal Canvas AI
  # assistant, so there is deliberately no builder, nodes or layout admin page.

  Background:
    Given I am a logged in user with the "Webmaster" user
     And I navigate to "/admin/config/ai/penpot"

  Scenario: The settings form is reachable for administrators
    Then the "ai penpot settings form" element should be visible
     And the "drupal page heading" element should contain text "AI Penpot"
     And I the page should not have PHP errors

  Scenario: The page exposes the Penpot connection group
    Then the "ai penpot settings connection details" element should be visible
     And the "ai penpot settings test details" element should be visible

  Scenario: The page exposes the instance URL, access-token Key and default file fields
    Then the "ai penpot settings base url" element should be visible
     And the "ai penpot settings token key" element should be visible
     And the "ai penpot settings default file id" element should be visible
     And I should see a "Penpot instance URL" field
     And I should see a "Penpot access token" field
     And I should see a "Default Penpot file id" field

  Scenario: The page exposes the Test connection and Save actions
    Then I should see the button "Test Penpot connection"
     And I should see the button "Save configuration"

  Scenario: Saving the form persists the configuration with no errors
    When I press "Save configuration"
    Then I should see "The configuration options have been saved."
     And the "ai penpot settings form errors" element should have a count of 0
     And I the page should not have PHP errors

  Scenario: The settings page meets basic accessibility
    Then every form field should have an accessible label
     And the page should have no serious accessibility violations
