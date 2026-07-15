# AI Penpot

Read a Penpot design's context from server-side PHP and hand it to Drupal's AI
Agent tools, so the Drupal Canvas AI assistant can work from the real design —
its colours, typography, structure, and text — instead of a screenshot. Paste a
Penpot link and the assistant reads it.

The module is deliberately small. It provides:

- the **Penpot connection** — a stored, read-only access token and a settings
  page, and
- two **AI Agent tools** that list a file's design pages and read one page's
  design context.

Under the hood it talks to the Penpot API (`<instance>/api/rpc/command/…`)
directly from PHP — the same design data the Penpot MCP server surfaces —
using a stored access token, with no interactive login. It is the Penpot
counterpart of [AI Figma](https://www.drupal.org/project/ai_figma).

## How it fits together

```
Penpot RPC API  ──▶  PenpotContextClient (PHP)  ──▶  AI Agent tools
   (design)            token from the Key module      "Penpot: List Design Pages"
                                                       "Penpot: Read Design Context"
                                                            │
                                                            ▼
                                              Drupal Canvas AI assistant
```

- The **token** lives in the [Key](https://www.drupal.org/project/key) module,
  encrypted at rest via
  [easy_encryption](https://www.drupal.org/project/easy_encryption).
- `PenpotContextClient` fetches and summarizes the design (colours, typography,
  shape outline, and the real text).
- The **AI Agent tools** expose that to `ai` / `ai_agents`, so an AI assistant
  can plan pages from the design and build each section from the real values.

## Next steps

- [Installation](installation.md) — install the module and connect a token.
- [Configuration](configuration.md) — the settings page, instance URL, token
  and default file.
- [AI Agent tools](ai-agent-tools.md) — the `ai_penpot:list_design_pages` and
  `ai_penpot:design_context` tool reference.

## Requirements

- Drupal **^11.2**
- `ai` (Drupal AI) and `ai_agents`
- `key` and `easy_encryption`
- A Penpot instance (Penpot Cloud or self-hosted) and a Penpot access token
