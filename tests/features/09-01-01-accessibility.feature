@ai-penpot @admin @a11y
Feature: AI Penpot settings page accessibility
  As an administrator using assistive technology
  I want the one settings page to be a well-structured, labelled document
  So that the AI Penpot surface meets WCAG 2.1 AA basics

  # The settings page is the module's only rendered surface, so this is where
  # accessibility matters. These scenarios assert the structural landmarks and
  # heading contract (a single h1, a main + navigation landmark, a page title),
  # the labelled-form contract, and that the page introduces no JavaScript
  # errors of its own. They complement the "no serious accessibility violations"
  # (axe-core) scenario in 03-01-01-settings-page.feature. All run with no live
  # LLM and no reachable Penpot instance.

  Background:
    Given I am a logged in user with the "Webmaster" user
     And I navigate to "/admin/config/ai/penpot"

  Scenario: The settings page is a labelled form
    Then the "ai penpot settings form" element should be visible
     And every form field should have an accessible label

  Scenario: The settings page exposes the standard landmarks
    Then the page should have a main landmark
     And the page should have a navigation landmark

  Scenario: The settings page has a single, titled heading structure
    Then the page should have a title
     And the page should have exactly one h1

  Scenario: The settings page produces no JavaScript errors
    Then there should be no JavaScript errors
