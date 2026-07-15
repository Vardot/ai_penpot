# AI Penpot

[![Drupal.org project](https://img.shields.io/badge/Drupal.org-AI%20Penpot-0678BE?logo=drupal)](https://www.drupal.org/project/ai_penpot)

Turn a [Penpot](https://penpot.app) design into a Drupal Canvas page built from your theme
components — via the Canvas AI assistant. AI Penpot reads live Penpot design context (colours,
typography, the real text content and a shape outline) through the Penpot API and exposes it as
**AI Agent tools**, so you can paste a Penpot link and have the Drupal Canvas AI assistant build from
the real design. Config-driven and theme-agnostic. It is the Penpot counterpart of
[AI Figma](https://www.drupal.org/project/ai_figma).

- **Project (canonical):** https://www.drupal.org/project/ai_penpot
- **Code (canonical):** https://git.drupalcode.org/project/ai_penpot
- **GitHub mirror:** https://github.com/Vardot/ai_penpot

## Requirements

- Drupal 11 with the [AI](https://www.drupal.org/project/ai) and AI Agents modules, plus
  [Key](https://www.drupal.org/project/key) and
  [Easy Encryption](https://www.drupal.org/project/easy_encryption).
- A Penpot instance (Penpot Cloud `https://design.penpot.app`, or self-hosted).
- A Penpot **access token** — create one at *Your account → Access tokens*. Self-hosted instances must
  enable the `enable-access-tokens` flag (`PENPOT_FLAGS`).

## Setup

1. Enable the module: `drush en ai_penpot`.
2. Go to **Configuration → AI → AI Penpot** (`/admin/config/ai/penpot`).
3. Set the **Penpot instance URL**, paste your access token into the **Penpot access token** Key
   (`/admin/config/system/keys`), and optionally set a **default file id**.
4. Click **Test Penpot connection** to confirm.

## AI Agent tools

- **Penpot: List Design Pages** (`ai_penpot:list_design_pages`) — lists every page of a Penpot file.
- **Penpot: Read Design Context** (`ai_penpot:design_context`) — reads one page's colours, typography,
  verbatim text and outline from a pasted Penpot link.

Both are gated on the *Use Penpot design context* permission.

## Maintainers

- [Vardot](https://www.drupal.org/vardot)
