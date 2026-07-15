Feature: AI Penpot agents surface and permissions
  As a site administrator
  I want the AI agents list and the permissions page to load cleanly
  So that I can confirm the module integrates with the Drupal AI framework

  # AI Penpot ships AI Agent function-call TOOLS (read Penpot design context so
  # the Drupal Canvas AI assistant can build from a design) that are surfaced to
  # the Drupal Canvas AI orchestrator. These scenarios assert the AI agents admin
  # surface loads with no PHP errors and that the module's own permissions are
  # registered - they never trigger a live LLM call or reach a Penpot instance,
  # so they pass in CI with no provider key configured. Asserting a specific tool
  # in the Tools Explorer needs the heavy explorer page and the full Canvas AI
  # stack, so it is left to live-CI tuning rather than the always-green lane.

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The AI agents configuration page loads with no PHP errors
    When I navigate to "/admin/config/ai/agents"
    Then the "drupal page heading" element should be visible
     And I the page should not have PHP errors

  Scenario: The AI Penpot administer permission is registered
    When I navigate to "/admin/people/permissions"
    Then I should see "Administer AI Penpot"
     And I the page should not have PHP errors

  Scenario: The Penpot design context permission is registered
    When I navigate to "/admin/people/permissions"
    Then I should see "Use Penpot design context"
     And I the page should not have PHP errors
