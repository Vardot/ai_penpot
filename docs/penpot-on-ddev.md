# Penpot on DDEV → Drupal AI + Drupal Canvas

Run **Penpot self-hosted inside DDEV** at `https://penpot.ddev.site`, design in it, and connect it to a
**separate DDEV Drupal site** (Drupal Core, Drupal CMS, or Varbase) running **Drupal AI** and **Drupal
Canvas**, so the Canvas AI assistant can build pages from a real Penpot design. `penpot.ddev.site` is
used throughout as the example instance hostname — substitute your own.

## Two ways to connect Penpot to an AI agent

There are two distinct integrations — pick by who needs the design and when:

| | **AI Penpot module** (headless) | **Penpot MCP** (browser plugin) |
|---|---|---|
| Runs where | Server-side PHP in the Drupal site | A local Node server + a browser tab |
| Auth | A stored **access token** (Key module) | The **active browser session** |
| Direction | **Reads** the design (colours, type, text, outline) | **Reads and writes** the live design |
| Best for | The **Drupal Canvas AI** assistant building pages from a pasted Penpot link | A **design agent** (Claude Code CLI) authoring/editing the Penpot design itself |
| Needs a browser tab open | No | Yes (holds the bridge) |
| Reproducible in CI / cron | Yes | No |

For "paste a Penpot link into Drupal Canvas AI and build from it", use the **AI Penpot module**
([drupal.org/project/ai_penpot](https://www.drupal.org/project/ai_penpot)) — section 4. Use the **MCP**
(section 3) when an agent should draw in Penpot.

---

## 1. Run Penpot self-hosted in DDEV

Goal: run the full Penpot 2.16 stack **inside the DDEV space**, not with the standalone
`docker-compose.yaml`. DDEV owns the router + TLS; its `web` (nginx) container reverse-proxies to the
Penpot frontend container.

```
Browser ──https──▶ ddev-router (TLS) ──▶ ddev-penpot-web (nginx reverse proxy)
                                              │  proxy_pass http://penpot-frontend:8080
                                              ▼
   penpot-frontend ──▶ penpot-backend ──▶ penpot-postgres (Postgres 15)
        │                    └──────────▶ penpot-valkey   (Redis/Valkey)
        └──▶ penpot-exporter        penpot-mailcatch (SMTP catcher)
```

All Penpot services run as **DDEV custom compose services** on the project's `default` network, so they
resolve each other by service name (`penpot-frontend`, `penpot-backend`, …).

### Files that make it work

| File | Role |
|------|------|
| `.ddev/config.yaml` | Project `penpot`, type `php`, nginx-fpm. |
| `.ddev/docker-compose.penpot.yaml` | All Penpot services (frontend, backend, exporter, postgres:15, valkey, mailcatch) + volumes. |
| `.ddev/nginx_full/nginx-site.conf` | Reverse-proxy `/` → `penpot-frontend:8080` (WebSocket upgrade, 400M body, long timeouts). |
| `docker-compose.yaml` (repo root) | **Reference only** — upstream Penpot compose, not used by DDEV. |

### `PENPOT_FLAGS` (set on BOTH frontend and backend)

The flags decide what the instance can do. For an AI-integrated dev instance use all of these:

```yaml
environment:
  PENPOT_FLAGS: disable-email-verification enable-smtp enable-prepl-server disable-secure-session-cookies enable-registration enable-plugins enable-access-tokens
  PENPOT_PUBLIC_URI: https://penpot.ddev.site
```

- `enable-registration` + `disable-email-verification` → registering logs you straight in.
- `enable-plugins` → required for the **Penpot MCP** (section 3).
- `enable-access-tokens` → required for the **AI Penpot module** (section 4). Without it, a token is
  silently ignored (requests resolve to *Anonymous*, not a 401).

### Start from scratch

```bash
mkdir penpot && cd penpot
# once: init the DDEV project
ddev config --project-name=penpot --project-type=php --docroot="" --disable-settings-management
# add .ddev/docker-compose.penpot.yaml + .ddev/nginx_full/nginx-site.conf (in the repo)
ddev start          # first run pulls the Penpot images (~1–2 min)
ddev launch         # or browse https://penpot.ddev.site
```

Register a user at `https://penpot.ddev.site/#/auth/register` — you land on the dashboard immediately.

### Everyday commands

```bash
ddev start | stop | restart      # restart applies changes to the compose / nginx files
ddev describe                    # URLs + service status
docker logs ddev-penpot-penpot-backend --tail 30
```

### Gotchas

- **"port 1080 is already in use"** on `ddev start` = a leftover standalone stack holds the
  mailcatcher port:
  ```bash
  docker ps -a --format '{{.Names}}' | grep '^penpot-penpot-' | xargs -r docker rm -f
  ddev start
  ```
- **Benign console errors** on the register page (not bugs): `/css/ui.css` 404 and a pre-auth
  `get-enabled-flags` 401.

---

## 2. Design locally in Penpot

- Register/log in, create a file, and design. Publish a **shared library** (Assets → Libraries →
  Publish) so components are reusable — needs ≥1 classic asset (tokens alone warn "empty library").
- **Design-system naming**: name components and layers meaningfully (`Button`, `Button / Primary`,
  `Card`, `Alert`, `Colors / Palette`, text layers `Label`) — never `Component 1` / `Rectangle`. The
  AI reads these names, so good names produce better builds.
- **Spacing / alignment discipline**: use token paddings, equal vertical rhythm, and centre single-line
  control text vertically (multi-line body stays top-anchored). Off-midline glyphs read as "not done".
- **Design tokens**: Penpot's token sets (colour, dimension, typography) map cleanly onto a Drupal
  theme's CSS custom properties later; keep one active token set per design system.

---

## 3. Activate the Penpot MCP (for design agents)

Connect Claude Code CLI to the live design so an agent can read and modify it. Penpot ships an official
MCP (docs: <https://help.penpot.app/mcp/>) in two modes:

- **Remote** — `https://<domain>/mcp/stream?userToken=KEY`; needs *Your account → Integrations → MCP
  Server*, only in Penpot builds **newer than 2.16.2**.
- **Local** — `@penpot/mcp`, a browser plugin + a local server. **2.16.2 has no in-app MCP toggle**, so
  use Local mode; it works against any plugins-enabled Penpot including a DDEV instance.

```
 new `claude` terminal ─HTTP(MCP)→ server http://localhost:4401/mcp   (node:22 container, host network)
        │  WebSocket bridge ws://localhost:4402
        ▼
 Penpot MCP Plugin (in the browser tab) ─Plugin API→ https://penpot.ddev.site (the live file)
```

Two processes must stay up: **(1)** the MCP server container, **(2)** a **browser tab** on the Penpot
site with the plugin **OPEN** (it holds the `ws://localhost:4402` bridge). Close the tab → tool calls
can no longer touch the design.

### Steps

1. **Enable plugins** — `enable-plugins` in `PENPOT_FLAGS` (both services), `ddev restart`. Verify:
   `docker exec ddev-penpot-penpot-frontend sh -c 'echo $PENPOT_FLAGS'`.

2. **Run the MCP server** in a `node:22` container on the host network (the host's own Node may be v20;
   the container supplies v22). `npx @penpot/mcp@stable` bootstraps a pnpm monorepo — two traps:
   host Node 20 fails (`node:sqlite`), and pnpm 11 aborts on the supply-chain gate
   (`ERR_PNPM_IGNORED_BUILDS`). Working recipe:

   ```bash
   SD=~/penpot-mcp; mkdir -p "$SD" && cd "$SD"
   npm pack @penpot/mcp@stable && tar xzf penpot-mcp-*.tgz      # → ./package
   cat >> package/pnpm-workspace.yaml <<'YAML'

   onlyBuiltDependencies:
     - esbuild
     - sharp
   YAML
   docker rm -f penpot-mcp-server 2>/dev/null
   docker run -d --name penpot-mcp-server --network host \
     -e CI=false -v "$SD/package:/pkg" -w /pkg node:22 \
     sh -c 'corepack enable; corepack prepare pnpm@11.13.0 --activate; \
            pnpm rebuild esbuild sharp; pnpm -r run build; pnpm -r --parallel run start'
   sleep 40 && docker logs penpot-mcp-server 2>&1 | tail -15
   ```

   Endpoints: MCP `http://localhost:4401/mcp`, SSE `/sse`, plugin UI/manifest
   `http://localhost:4400/manifest.json`, WS bridge `ws://localhost:4402`, REPL `:4403`.
   Tools: `execute_code`, `high_level_overview`, `penpot_api_info`, `export_shape`, `import_image`.

3. **Register in Claude Code** (user scope → every new terminal gets it):
   ```bash
   claude mcp add --scope user --transport http penpot http://localhost:4401/mcp
   claude mcp list | grep penpot        # → ✔ Connected
   ```

4. **Load the plugin in the browser** (the bridge). In the Penpot editor tab: **Ctrl+Alt+P** →
   paste `http://localhost:4400/manifest.json` → **INSTALL** → **Allow** → **OPEN** →
   **CONNECT MCP SERVER**. The server log prints `PluginBridge: New WebSocket connection established`.
   Leave the tab open.

5. **Version-mismatch warning** (cosmetic): the published `@penpot/mcp` jumps 2.15.4 → 2.17.0 (no
   2.16.x), so it never matches Penpot 2.16.2. To silence it, set the unpacked `package.json`
   `version` to your Penpot's exact version (e.g. `2.16.2`) before building — `MCP_VERSION` is read
   from the root `package.json` — then rebuild and reconnect.

6. **Use it** — open a **new** Claude Code terminal (sessions opened before registration won't see the
   tools). Read `high_level_overview` first, then `execute_code`.

### MCP troubleshooting

| Symptom | Fix |
|---|---|
| `No such built-in module: node:sqlite` | Ran on host Node 20 — use the `node:22` container. |
| `ERR_PNPM_IGNORED_BUILDS` | Missing `onlyBuiltDependencies` block, or skipped `pnpm rebuild esbuild sharp`. |
| Plugins dialog missing (Ctrl+Alt+P does nothing) | `enable-plugins` not set, or no `ddev restart`. |
| Manifest won't load | Server not running / not on `--network host`; `curl http://localhost:4400/manifest.json`. |
| Tool calls do nothing | No browser tab has the plugin **OPEN** — the `ws://localhost:4402` bridge is down. |

---

## 4. Integrate with a DDEV Drupal site (Core / CMS / Varbase) + Drupal AI + Drupal Canvas

This is the headless path: the **AI Penpot** module reads a Penpot design from server-side PHP and
exposes it as **AI Agent tools**, so the **Drupal Canvas AI** assistant can build a Canvas page from a
pasted Penpot link. Nothing here needs the MCP or a browser tab.

### 4.1 Cross-project DDEV networking

Penpot (`penpot.ddev.site`) and the Drupal site (`your-drupal.ddev.site`) are two separate DDEV
projects on the same host. DDEV's shared router lets one project's web container reach the other's
`*.ddev.site` URL over TLS out of the box — no extra config:

```bash
# from the Drupal project:
ddev exec "curl -sk -o /dev/null -w '%{http_code}\n' https://penpot.ddev.site/api/rpc/command/get-profile -X POST -H 'Accept: application/json' -d '{}'"
# → 200
```

### 4.2 Enable access tokens + create a token

1. On Penpot, ensure `enable-access-tokens` is in `PENPOT_FLAGS` (both services) and `ddev restart`.
2. Create a token: in Penpot go to **Your account → Access tokens**, or via the API:

   ```bash
   # log in (cookie), then create a token
   J=jar; curl -sk -c $J -X POST https://penpot.ddev.site/api/rpc/command/login-with-password \
     -H 'Accept: application/json' -H 'Content-Type: application/json' \
     -d '{"email":"you@example.com","password":"…"}' -o /dev/null
   curl -sk -b $J -X POST https://penpot.ddev.site/api/rpc/command/create-access-token \
     -H 'Accept: application/json' -H 'Content-Type: application/json' -H 'Origin: https://penpot.ddev.site' \
     -d '{"name":"drupal ai_penpot"}'      # → {"token":"eyJ…"}
   ```

### 4.3 The Penpot API the module uses (reference)

The module talks to the Penpot **RPC API** at `<instance>/api/rpc/command/<command>`, same origin:

- Send `Accept: application/json` → plain JSON (default is the Transit encoding, painful in PHP).
- JSON keys are **camelCased** (`data.pagesIndex`; the Transit form is `pages-index`).
- Auth header: `Authorization: Token <access-token>` (needs the `enable-access-tokens` flag).
- `get-file {id}` → `{name, data:{pages:[ids], pagesIndex:{id→{name}}}}` (page objects are pointer-
  stored, not inlined). `get-page {file-id, page-id}` → `{id, name, objects:{shapeId→shape}}` (flat
  map). Fills are already hex (`{fillColor:"#…", fillOpacity}`); text lives in a `content` tree.

### 4.4 Install and configure AI Penpot on the Drupal site

```bash
# on the Drupal DDEV project
ddev composer require drupal/ai_penpot
ddev drush en ai_penpot -y && ddev drush cr
```

This pulls `ai`, `ai_agents`, `key`, `easy_encryption` and creates an empty `penpot` Key. Then at
**Configuration → AI → AI Penpot** (`/admin/config/ai/penpot`):

1. **Penpot instance URL** = `https://penpot.ddev.site` (Penpot Cloud: `https://design.penpot.app`).
2. Paste the access token into the **Penpot access token** Key (`/admin/config/system/keys`) — it is
   encrypted at rest via Easy Encryption.
3. Optionally set a **default file id** (the UUID from a Penpot `/workspace/…/<fileId>` URL).
4. Click **Test Penpot connection** → it reports the file's page count and the colours/typography on
   the first page.

### 4.5 Wire the tools into Drupal Canvas AI

The module registers two tools — `ai_penpot:list_design_pages` and `ai_penpot:design_context`. Add
them to the Canvas AI agents so the assistant can call them:

- Add both tool ids to the **Drupal Canvas AI Orchestrator** and/or the **Page Builder Agent** (an
  `ai_agent` config entity's `tools` map).
- Use a strong tool-calling model — this was verified with **Claude Sonnet 5**
  (`ai.settings` → `default_providers.chat_with_tools`). Weaker models may not invoke the tool.

You can exercise a tool directly in the browser via the **AI API Explorer**
(`/admin/config/ai/explorers/tools_explorer`, module `ai_api_explorer`): pick *Penpot: Read Design
Context*, paste a Penpot link, **Run Function**.

### 4.6 Use it

In the Canvas editor open the **AI panel** and paste a Penpot link:

> Use your Penpot design context tool to read this page and list the colours and text you find:
> `https://penpot.ddev.site/#/workspace/<team>/<file>` page "Alert"

The assistant calls `ai_penpot:design_context`, reads the real colours/typography/text, and builds the
section from your theme components — mapping the Penpot colours onto the theme's design tokens, not
hard-coded hex.

Applies to **Drupal Core**, **Drupal CMS**, and **Varbase 11** the same way — AI Penpot is
config-driven and theme/framework-agnostic.

---

## Reference

| Thing | Value |
|---|---|
| Penpot URL (DDEV) | `https://penpot.ddev.site` |
| Penpot RPC API | `<instance>/api/rpc/command/<command>` (POST, `Accept: application/json`) |
| Token auth | `Authorization: Token <access-token>` — needs `enable-access-tokens` |
| MCP server | `http://localhost:4401/mcp` (needs `enable-plugins` + an open plugin tab) |
| MCP tools | `execute_code`, `high_level_overview`, `penpot_api_info`, `export_shape`, `import_image` |
| Module | [drupal/ai_penpot](https://www.drupal.org/project/ai_penpot) — settings at `/admin/config/ai/penpot` |
| Module tools | `ai_penpot:list_design_pages`, `ai_penpot:design_context` |
