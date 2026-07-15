@ai-penpot @admin
Feature: AI Penpot field help and menu discoverability
  As a site builder configuring the module for the first time
  I want each field to explain what to paste and where to find it
  So that I can connect the module to Penpot without reading the code

  # The one settings page carries the module's entire self-documentation: the
  # instance URL help names the RPC path the module calls, the token help points
  # at Penpot's "Access tokens" screen and the self-hosted enable-access-tokens
  # flag, and the default-file help explains the workspace/view UUID. These
  # scenarios assert that guidance renders, that the settings page is reachable
  # from the AI configuration menu, and that there is exactly ONE config page -
  # no separate builder/layout admin pages (everything else runs through the
  # Drupal Canvas AI assistant).

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The instance URL help names the Penpot RPC command path
    When I navigate to "/admin/config/ai/penpot"
    Then I should see a "Penpot instance URL" field
     And I should see "/api/rpc/command"
     And I the page should not have PHP errors

  Scenario: The access-token help points at Penpot access tokens
    When I navigate to "/admin/config/ai/penpot"
    Then I should see a "Penpot access token" field
     And I should see "Access tokens"
     And I should see "enable-access-tokens"

  Scenario: The default file help explains the workspace/view UUID
    When I navigate to "/admin/config/ai/penpot"
    Then I should see a "Default Penpot file id" field
     And I should see "workspace"

  Scenario: The AI Penpot link appears under the AI configuration group
    When I navigate to "/admin/config/ai"
    Then the "ai penpot admin menu link" element should be visible
     And I should see "AI Penpot"
     And I the page should not have PHP errors

  Scenario: There is exactly one AI Penpot config page - no separate builder page
    When I navigate to "/admin/config/ai/penpot/builder"
    Then the "ai penpot settings form" element should have a count of 0

  Scenario: There is no separate AI Penpot layout page
    When I navigate to "/admin/config/ai/penpot/layout"
    Then the "ai penpot settings form" element should have a count of 0
