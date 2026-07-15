# jakarta

Base theme providing the design system. All visitor-facing themes on this project are children of `jakarta`.

## Purpose

`jakarta` is a component-driven starter theme built around atomic design. It owns the compiled CSS/JS design system. Child themes (`bestor`) add project-specific templates and overrides; they do not re-implement components already in `jakarta`.

## Build system

```bash
# Requires Node.js — check .nvmrc for the expected version (nvm use)
npm install

# Development: watch for SCSS/JS changes
npm run watch

# Production build
npm run build
```

Webpack is configured in `webpack.config.js`. Source files live under `src/`; compiled output goes to `css/dist/` and component directories. The `node_modules/` and `dist/` directories are committed to the repository.

## Component architecture

Components follow atomic design and live under `components/`:

| Tier | Path | Description |
|------|------|-------------|
| Atoms | `components/atoms/` | Single UI elements (button, icon, input, label, tag, …) |
| Molecules | `components/molecules/` | Composed elements (card, breadcrumb, menu, pagination, teaser, …) |
| Organisms | `components/organisms/` | Page-section components (header, footer, hero, sidebar, …) |

Each component directory contains:
- `c-name.component.yml` — SDC metadata (props, slots, dependencies)
- `c-name.twig` — Template
- `src/c-name.scss` — Source styles
- `src/c-name.js` — Optional scripts
- `c-name.css` / `c-name.css.map` — Compiled output

`components/_bin/` contains removed/deprecated components kept for reference. Do not use these in new work.

## CSS conventions (SMACSS with GBL twist)

`jakarta` suppresses all Drupal and core CSS via `libraries-override: system/base: false` and several `stylesheets-remove` entries. This ensures no Drupal default styles bleed through — all styling comes from the design system.

Source SCSS is organized by:
- **Base** — element defaults (single-element selectors, pseudo-classes)
- **Layout** — page sections (header, footer, navigation); items present on every page
- **Components** — reusable modules (nodes, paragraphs, blocks)
- **Helpers** — mixins, extendables

CKEditor 5 gets a dedicated stylesheet at `dist/css/wysiwyg.css` (declared via `ckeditor5-stylesheets`).

## Regions

12 regions: `nav_left`, `nav`, `nav_right`, `precontent`, `sidebar`, `content`, `subcontent`, `footer_1`, `footer_2`, `footer_3`, `footer_4`, `footer`, `overlay`. Child themes inherit this region set.

## When to edit this theme

Edit `jakarta` only when a change should apply to **all sites using this design system** — for example, fixing a shared component or updating a design token. For Bestor-specific overrides, edit `bestor` instead.
