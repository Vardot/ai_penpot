@ai-penpot @admin @security
Feature: AI Penpot settings validation
  As a security-conscious site owner
  I want the Penpot instance URL to be validated as a full http(s) URL
  So that a mistyped or hostile base (which every request sends the secret token
  to) is rejected before it is ever saved

  # The token is sent to the configured base on every Penpot RPC call, so the
  # settings form rejects anything that is not a plain http(s) URL with a host
  # (no scheme-less host, no file://, ...) in validateForm(). These scenarios
  # exercise BOTH sides of that guard - a bad value is refused with the module's
  # own error message, a good value saves cleanly - without ever reaching a live
  # Penpot instance. Native browser URL validation is switched off so the bad
  # value reaches the server-side check under test (not the browser's).

  Background:
    Given I am a logged in user with the "Webmaster" user
     And I navigate to "/admin/config/ai/penpot"
     And browser validation for the form "form[data-drupal-selector='ai-penpot-settings']" is disabled

  Scenario: A malformed instance URL is not saved
    When I fill in "Penpot instance URL" with "not-a-url"
     And I press "Save configuration"
    Then I should not see "The configuration options have been saved."
     And I the page should not have PHP errors

  Scenario: A non-http scheme is rejected with the module's own error
    When I fill in "Penpot instance URL" with "ftp://design.penpot.app"
     And I press "Save configuration"
    Then I should see "The Penpot instance URL must be a full http(s) URL"
     And I should not see "The configuration options have been saved."
     And I the page should not have PHP errors

  Scenario: A valid https instance URL saves cleanly
    When I fill in "Penpot instance URL" with "https://design.penpot.app"
     And I press "Save configuration"
    Then I should see "The configuration options have been saved."
     And the "ai penpot settings form errors" element should have a count of 0
     And I the page should not have PHP errors
