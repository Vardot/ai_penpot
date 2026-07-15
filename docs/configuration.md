# Configuration

All settings live at **Configuration → AI → AI Penpot**
(`/admin/config/ai/penpot`), behind the *Administer AI Penpot* permission.

## Penpot connection

| Setting | Description |
| --- | --- |
| **Penpot instance URL** | The base URL of your Penpot instance — `https://design.penpot.app` for Penpot Cloud, or your own address for a self-hosted instance. The module calls `<URL>/api/rpc/command/…`. |
| **Penpot access token (Key)** | The [Key](https://www.drupal.org/project/key) that holds your Penpot access token. |
| **Default Penpot file id** | The UUID in a `penpot.app/#/workspace/…/<fileId>` or `/view/<fileId>` URL, used when a tool is called without one. Leave empty to require a file id or link each time. |

!!! warning "The instance URL is validated"
    The secret token is sent to the instance host in the `Authorization` header
    on **every** request, so a hostile or mistyped URL could exfiltrate it (or
    trigger SSRF). Any value that is not a plain `http(s)` URL with a host is
    rejected — on the form and again in the client.

## Test connection

**Test Penpot connection** probes the API with the **saved** URL, token and
default file id, and reports how many pages the file has plus the colours and
typography styles on its first page. Save your changes first if you just picked
a different Key.

A failed probe surfaces a concise reason, e.g. `Penpot page request failed:
401 authentication-required`.

## AI guidance (optional)

When the [ai_context](https://www.drupal.org/project/ai_context) module is
present, installing AI Penpot seeds editable **AI Context** items from config —
*Penpot Build Rules* and *Penpot Accessibility Rules* — that Penpot-aware AI
agents read when building from a design. Edit them at **Configuration → AI →
Context**, or change the defaults in `ai_penpot.settings`
(`build_rules`, `accessibility_rules`, `mapping_governance`). Without
`ai_context`, this is a no-op.
