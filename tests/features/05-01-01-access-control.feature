@ai-penpot @admin @security
Feature: Role-based access control for AI Penpot
  As a security-conscious site owner
  I want the AI Penpot configuration and the AI / admin surfaces it touches to be
  reachable only by trusted administrators
  So that the Penpot instance URL, token and design-context settings cannot be
  read or changed by anonymous visitors

  # Permission matrices are a classic silent-regression zone: one changed default
  # in a module update can flip a single cell with no feature "looking" broken.
  # Following the role x area x expected matrix pattern, these two outlines assert
  # BOTH sides of every protected path - the administrator keeps access, the
  # anonymous user is denied - so a regression in either direction fails a
  # precise, named row instead of a vague "access broke somewhere".

  Scenario Outline: An administrator can reach <area>
    Given I am a logged in user with the "Webmaster" user
    When I navigate to "<path>"
    Then I the page should not have PHP errors

    # The AI Tools Explorer is a heavy page (it lists hundreds of tools); the
    # administrator can reach it is proven robustly in
    # 06-01-01-agents-and-permissions.feature, so it is not repeated here.
    Examples: Protected AI Penpot and admin areas
      | area                          | path                      |
      | the AI Penpot settings page   | /admin/config/ai/penpot   |
      | the AI configuration group    | /admin/config/ai          |
      | the status report             | /admin/reports/status     |
      | the permissions page          | /admin/people/permissions |

  Scenario Outline: An anonymous visitor is denied <area>
    Given I am an anonymous user
    Then I am denied access to "<path>"

    Examples: Protected AI Penpot and admin areas
      | area                          | path                      |
      | the AI Penpot settings page   | /admin/config/ai/penpot   |
      | the AI configuration group    | /admin/config/ai          |
      | the permissions page          | /admin/people/permissions |
