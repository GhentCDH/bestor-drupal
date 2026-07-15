# bestor_admin

Admin theme for the Bestor back-end interface. Child of Drupal core's `claro` theme.

## Purpose

Applies Bestor-specific styling and behaviour on top of `claro`. Provides pre-configured block placements for the standard admin UI regions and a small CSS/JS layer for the logged-in admin menu.

## Architecture overview

```
bestor_admin/
├── bestor_admin.info.yml          — theme metadata, regions, library declaration
├── bestor_admin.libraries.yml     — declares the global admin library
├── bestor_admin.theme             — PHP hooks (if any)
├── css/bestor_admin.css           — CSS overrides on top of claro
├── js/bestor_admin.js             — Admin UI behaviour
├── logo.svg                       — Admin branding
├── config/
│   ├── optional/                  — 8 block placement configs (installed on theme enable)
│   │   └── block.block.*.yml      — breadcrumb, content, help, page title, actions,
│   │                                messages, tasks, system_main
│   └── schema/claro.schema.yml    — Schema override for claro settings
└── screenshot.png
```

## Configuration

The 8 `config/optional/` block config files pre-place the standard Claro admin blocks (breadcrumb, main content, help text, page title, local actions, status messages, local tasks) into the correct regions when the theme is activated. These files are installed automatically on theme enable; they do not need to be imported manually.

`config/schema/claro.schema.yml` overrides the Claro configuration schema. This is needed because `bestor_admin` inherits Claro's theme settings and Drupal requires the schema to be present in the active theme when those settings are saved.

## Regions

Inherits Claro's region set: `header`, `pre_content`, `breadcrumb`, `highlighted`, `help`, `content`, `page_top`, `page_bottom`. `sidebar_first` is declared but hidden (`regions_hidden`).

## Dependencies

- Base theme: `claro` (Drupal core)
