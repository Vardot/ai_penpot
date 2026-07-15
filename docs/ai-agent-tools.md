# AI Agent tools

The module registers two AI Agent tools (`ai` / `ai_agents` function calls),
both in the `information_tools` group and gated on the *Use Penpot design
context* permission (`use ai penpot design context`), or `administer ai agents`
/ `use Drupal Canvas AI`.

## Penpot: List Design Pages

| | |
| --- | --- |
| **Plugin id** | `ai_penpot:list_design_pages` |
| **Function name** | `ai_penpot_list_design_pages` |

### Inputs

Both are optional:

| Input | Description |
| --- | --- |
| `penpot_url` | A Penpot link — the file id is extracted from it. |
| `file_id` | A Penpot file id (UUID). |

When neither is given, the tool falls back to the **Default Penpot file id**
from [configuration](configuration.md). A link can be plain or `@`-prefixed.

### Output

Every page of the file, in order, each with its page id — so an AI agent can
plan **one Canvas page per design page** (and skip style-guide / foundation
pages such as Colors, Typography, Icons). Returned as YAML, for example:

```yaml
penpot_file_id: 234bafb6-6381-801d-8008-4ebf012d6ac6
design_pages:
  - page_id: 234bafb6-6381-801d-8008-4ebf012d6ac7
    name: Colors
  - page_id: b09ed0ac-489d-80e7-8008-4ed50d1b0d23
    name: Alert
```

## Penpot: Read Design Context

| | |
| --- | --- |
| **Plugin id** | `ai_penpot:design_context` |
| **Function name** | `ai_penpot_design_context` |

### Inputs

All optional:

| Input | Description |
| --- | --- |
| `penpot_url` | A Penpot workspace/view link — the file id and (when present) the page id are extracted from it. |
| `file_id` | A Penpot file id (UUID). |
| `page` | A page id (UUID) or a page name. Empty uses the page id from the link, or the file's first page. |

### Output

One page's design context — its colours, typography, the real text content
(verbatim) and a shape outline — as YAML, for example:

```yaml
penpot_file_id: 234bafb6-6381-801d-8008-4ebf012d6ac6
page:
  id: b09ed0ac-489d-80e7-8008-4ed50d1b0d23
  name: Alert
colors:
  '#FFFFFF': Root Frame
  '#d8eafc': Rectangle
typography:
  'sourcesanspro 400 16px': 'This is a danger alert.'
content_texts:
  - text: 'This is a danger alert.'
    name: 'This is a danger alert.'
    size: 16
outline:
  - 'frame: Alert'
```

### Use it with the Canvas AI assistant

Add both tools to the Canvas AI assistant's (or an orchestrator's) tool list,
then ask it — with a Penpot link — to build from the design. The assistant lists
the pages, reads each page's context, and builds one Canvas page at a time from
the real colours and text, mapping the colours onto the active theme's design
tokens.
