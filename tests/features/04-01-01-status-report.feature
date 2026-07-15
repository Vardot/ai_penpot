Feature: AI Penpot module status report
  As a site administrator
  I want the status report to report the AI Penpot module state
  So that I can see at a glance whether the Penpot connection is configured

  # hook_requirements() registers an "AI Penpot" line on the status report:
  # "Penpot connection configured" (OK) when a base URL and a Key token are set,
  # or "No Penpot instance URL configured" / "No Penpot token configured" (a
  # warning, not an error) otherwise. Either way the "AI Penpot" title renders
  # and the page must be free of PHP errors, so this scenario asserts the stable
  # parts and never couples to the connection state of a given environment.

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The status report lists the AI Penpot requirement with no PHP errors
    When I navigate to "/admin/reports/status"
    Then the "drupal page heading" element should contain text "Status report"
     And I should see "AI Penpot"
     And I the page should not have PHP errors
