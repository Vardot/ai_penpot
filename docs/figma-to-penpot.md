# Migrating a Figma design to Penpot

AI Penpot reads a **Penpot** design. If your design lives in **Figma**, bring it into Penpot first —
then point AI Penpot at the imported file and let the Drupal Canvas AI assistant build from it. (For a
design that is already in Figma, use the sibling module
[AI Figma](https://www.drupal.org/project/ai_figma) instead — it reads Figma directly.)

There are three routes, from easiest to most controlled.

## Option A — Penpot Exporter (Figma plugin) — recommended

The official **Penpot Exporter** Figma plugin converts a Figma file into a Penpot-importable archive.
Open source (MPL-2.0), maintained by Kaleidos, and — per Penpot's own guidance — the recommended
migration tool.

1. In Figma: **Resources → Plugins**, search **Penpot Exporter**, run it, and **Export** — it produces
   a `.zip` (`.penpot`) file.
2. In Penpot: **Projects/Drafts menu → Import Penpot files**, upload the archive, and open it.

**Transfers well:** shapes (rectangles, ellipses, stars, polygons), vectors, lines, arrows; frames,
sections, groups, boolean groups; masks; text (with custom fonts); **component sets, instances and
variants**; **Auto Layouts**; colour and typography libraries; external library links.

**Limitations:** prototyping/interaction settings are **not** exported; very large files can hit Figma
API limits; features with no Penpot equivalent may not convert perfectly. Community reports also note
**colour variables** may not come across cleanly — check them after import.

## Option B — figpot (CLI, for repeatable sync)

[`@betagouv/figpot`](https://github.com/betagouv/figpot) is a Node/TypeScript CLI (MIT) that not only
converts but **synchronizes** Figma → Penpot, keeping Figma as the source of truth. Use it for
CI/scheduled runs rather than a one-off.

```bash
npx @betagouv/figpot document synchronize -d FIGMA_ID:PENPOT_ID
# env: FIGMA_ACCESS_TOKEN, PENPOT_ACCESS_TOKEN, PENPOT_USER_EMAIL, PENPOT_USER_PASSWORD
```

- **Non-destructive incremental sync** — tracks Figma↔Penpot id mappings locally so repeated runs
  update in place (elements keep stable ids, so bindings don't break). Full **variant** support.
- **Automation** — `--ci` flag for unattended runs.
- **Needs both API tokens plus Penpot email/password** — the final "hydrate" step renders in a browser
  to finalise thumbnails and text positioning, so it needs session credentials, not just tokens.
- **Caveats:** memory-heavy (8–16 GB for large files); no real-time sync (manual/scheduled, e.g. twice
  weekly); remote component links are lost across files (sync dependencies separately); some Figma
  features have no Penpot equivalent.

## Option C — manual recreation / SVG import

For full control, or when the automated tools stumble on a specific file:

- **SVG fallback** — in Figma export the frames as **SVG** into a ZIP, then Penpot **Projects menu →
  Import** them. Penpot converts SVGs into editable shapes.
- **Rebuild with Penpot primitives** — Penpot's **Flex** and **Grid** layouts behave like CSS
  flexbox/grid (the Figma Auto-Layout equivalent). Select elements → right-click → **Create component**
  (stored in the Assets panel; edits to the main propagate to copies unless detached). Assets are
  **Typography**, **Colours** and **Components**; use **design tokens** for colour/dimension/typography
  so they map cleanly onto a Drupal theme's CSS custom properties later.
- **Gotchas:** the Penpot toolbar has no pen/arrow/triangle primitives — use the **path** tool or an
  imported library; rename a file by **double-clicking** its title; Penpot supports **component
  swapping** on instances.

## After import — connect it to Drupal

Once the design is a Penpot file:

1. Note its **file id** (the UUID in the `/workspace/…/<fileId>` URL).
2. In the Drupal site, set the **Penpot instance URL** and paste a **Penpot access token** on the
   [AI Penpot settings page](configuration.md), then **Test Penpot connection**.
3. In the Canvas editor's AI panel, paste the Penpot link — the assistant reads the imported design's
   colours, typography and text and builds from it. See [AI Agent tools](ai-agent-tools.md).

## Figma ↔ Penpot: what to expect

- **Open standards** — Penpot is open source and self-hostable; the Inspect tab exposes real CSS/HTML,
  and layouts are CSS Flex/Grid, so what a designer builds maps directly to front-end code.
- **Components/variants and libraries** transfer via the Exporter; **prototyping** does not.
- **Review before you rely on it** — no converter is 100%; open the imported file and fix colour
  variables, fonts and any unsupported effects before building.
- **Migrate gradually** — Penpot's own advice is to start new work in Penpot and move existing files
  over with the Exporter as you go, rather than a big-bang migration; secure team buy-in and lean on
  Penpot's Get Started guide and Learning Center. Penpot is open source, self-hostable, free, and
  supports 30 languages (incl. RTL).

## Resources

- [Penpot Exporter — Figma plugin](https://www.figma.com/community/plugin/1219369440655168734/penpot-exporter)
  ([source](https://github.com/penpot/penpot-exporter-figma-plugin))
- [figpot — Figma → Penpot converter/synchronizer](https://github.com/betagouv/figpot)
- Penpot: How to make the switch from Figma to Penpot —
  [Part I (why + team)](https://penpot.app/blog/how-to-make-the-switch-from-figma-to-penpot/) ·
  [Part II (migration)](https://penpot.app/blog/how-to-make-the-switch-from-figma-to-penpot-2/)
- [freeCodeCamp: How to recreate Figma components in Penpot](https://www.freecodecamp.org/news/how-to-recreate-figma-components-in-penpot/)
- [Penpot community: transferring a Figma project to Penpot](https://community.penpot.app/t/can-i-transfer-a-figma-project-to-penpot/276)
