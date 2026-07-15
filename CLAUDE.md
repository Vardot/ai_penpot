# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`ai_penpot` — a Drupal 11 module (`drupal/ai_penpot`, maintained by Vardot). Reads live Penpot design
context via the Penpot RPC API from server-side PHP and exposes it as **AI Agent tools** so the Drupal
Canvas AI assistant can build Canvas pages from a real Penpot design — paste a Penpot link, the system
reads it. Config-driven, theme- and framework-agnostic. It is the Penpot counterpart of `ai_figma`
(same shape, same conventions).

Scope split: this module ships **only the basic Penpot connection** (client + settings + Key) plus two
read-only AI tools (list pages, read design context). The Canvas-AI build orchestrator / assistant
tools belong in a **separate `varbase_ai_penpot`** module that depends on this one. Do not add
build/assistant logic here.

## Commands

A Drupal module — installed into a Drupal 11 site (Core / Drupal CMS / Varbase 11), not run standalone.

```bash
ddev drush en ai_penpot -y && ddev drush cr

# PHP coding standards (Drupal + DrupalPractice), matching CI
phpcs -n --standard=Drupal,DrupalPractice \
  --extensions=php,module,inc,install,profile,theme,yml \
  --ignore="*/node_modules/*,*/vendor/*,*/tests/fixtures/*" .

vendor/bin/phpunit -c web/core/phpunit.xml.dist path/to/module/tests/src/Unit
composer validate --no-check-all --no-check-publish
```

## Architecture

**`PenpotContextClient`** (`src/PenpotContextClient.php`, service `ai_penpot.client`) — the whole Penpot
integration. Key responsibilities:
- **Token**: resolved only from the **Key module** (`getToken()`), never an env var; encrypted at rest
  via `easy_encryption`. Sent as `Authorization: Token <token>`.
- **Base URL**: `getBaseUrl()` validates the admin-configured `penpot_base_url` (SSRF / token-exfil
  defence — http(s) + host only), and every request goes to `<base>/api/rpc/command/<command>`.
- **URL parsing**: `parsePenpotUrl()` (static) extracts a `file_id` and `page_id` (UUIDs) from Penpot
  `/#/workspace/<team>/<file>?page-id=…`, `/#/workspace?file-id=…&page-id=…`, `/#/view/<file>…` links,
  or a bare file UUID; handles `@`-prefixed links.
- **Fetch + summarize**: `fetchFile()` (get-file → page index only, objects are pointer-stored),
  `fetchPage()` (get-page → a page's full flat `objects` map; resolves a page *name* to an id, or
  falls back to the first page), `listDesignPages()`, `resolvePageIdByName()`, `summarizeTokens()`
  (colours from `fills[].fillColor`, typography + verbatim text from the text `content` tree, a shape
  outline). `extractText()`/`firstTextStyle()` walk the Penpot rich-text tree.

**AI Agent tools** in `src/Plugin/AiFunctionCall/` use the `#[FunctionCall]` attribute (`drupal/ai`),
group `information_tools`, gated on `use ai penpot design context` (or `administer ai agents` / `use
Drupal Canvas AI`):
- `PenpotListDesignPages` (`ai_penpot:list_design_pages`) — every page + id, to plan one Canvas page
  per design page.
- `PenpotDesignContext` (`ai_penpot:design_context`) — read ONE page's colours / typography / verbatim
  texts / outline. This is the "paste a link → read from it" tool.

**`AiPenpotInstaller`** (`src/AiPenpotInstaller.php`, service `ai_penpot.installer`, aliased to its FQCN
so `#[Hook]` classes autowire it). `hook_install` calls `installPenpotKey()` (empty `penpot` Key,
`easy_encrypted` provider when available, else `config`) and `seedContextItems()` (writes the
build/accessibility/governance rules as `ai_context_item` entities — no-op when `ai_context` is absent;
note ai_context caps *global* items at 3).

**Config is the source of truth.** `config/install/ai_penpot.settings.yml` (schema in `config/schema/`)
holds `penpot_base_url`, `penpot_token_key`, `default_file_id`, and the `build_rules` /
`accessibility_rules` / `mapping_governance` prompt text. To change build behaviour, edit config (or the
seeded AI Context items), not PHP.

## Penpot API facts (self-host, grounded on penpot.ddev.site / Penpot 2.16.2)

- **RPC over HTTP**, same origin: `POST <base>/api/rpc/command/<command>`. Send
  `Accept: application/json` to get plain JSON instead of Penpot's default Transit encoding.
- **JSON keys are camelCased**: get-file's `data.pagesIndex` (Transit form is `pages-index`). The client
  reads `pagesIndex` with a `pages-index` fallback.
- **Auth**: `Authorization: Token <access-token>`. Access tokens are created in Penpot at *Your account
  → Access tokens*. **Self-host must enable the `enable-access-tokens` flag** (`PENPOT_FLAGS`) or the
  token is silently ignored (requests resolve to Anonymous). Cookie login
  (`login-with-password`) is the browser session path, not used by this module.
- `get-file {id}` → `{name, data:{pages:[ids], pagesIndex:{id→{name}}, …}}` (page objects NOT inlined —
  pointer-stored). `get-page {file-id, page-id}` → `{id, name, objects:{shapeId→shape}}` (flat map).
- Shape: `type` (frame/group/rect/text/path/circle/image/bool/svg-raw), `name`, `x/y/width/height`,
  `fills:[{fillColor:"#hex", fillOpacity}]` (already hex — no rgba float conversion), `appliedTokens`;
  text `content` = root → paragraph-set → paragraph → runs (`fontFamily/fontSize/fontWeight/text`).

## Conventions

- `declare(strict_types=1);` on every PHP file; typed properties + constructor promotion.
- PHPCS `Drupal,DrupalPractice` (CI-enforced). `core_version_requirement: ^11.2`; PHP `>=8.3`.
- Rule/prompt text belongs in config or AI Context items, keyed by intent; keep it generic and
  theme-agnostic (no Bootstrap/Varbase assumptions here).
