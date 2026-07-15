# bestor

Frontend theme for the Bestor website. Child of `jakarta`.

## Purpose

All visitor-facing presentation lives here. `jakarta` provides the design system (components, compiled CSS); `bestor` applies Bestor-specific overrides, templates, page structure, and feature-specific libraries.

## Architecture overview

```
bestor/
├── bestor.info.yml          — regions, global libraries, base theme declaration
├── bestor.libraries.yml     — library definitions: global, database-search, advanced-search-filters, lightbox
├── bestor.theme             — PHP preprocess hooks and theme suggestion overrides
├── css/                     — bestor.css (global), footer.css, advanced-search-filters.css
├── js/                      — bestor.js, database-search.js, advanced-search-filters.js, lightbox-init.js
├── components/molecules/
│   └── c-media-gallery/     — SDC component for image gallery (Bestor-specific, not reusable)
├── libraries/glightbox/     — Vendored glightbox library (not installed via npm)
└── templates/               — Twig overrides organized by type
    ├── block/               — language switcher, search block, page title, pagecontent
    ├── field/               — paragraph media field overrides
    ├── form/                — contact form, exposed search forms, custom select
    ├── layout/              — footer regions (newsletter, socials, main footer)
    ├── media/               — remote video embed
    ├── node/                — display modes: card-vertical, card-horizontal, card-spotlight,
    │                          card-vertical-slim, teaser, line, info-box, event--full, lemma--full
    ├── page/                — front page, main page, bestor-translation-unavailable
    ├── paragraphs/          — formatted-text, media, table, text-media
    ├── region/              — subcontent region
    └── view/                — database, database-advanced-search, announcements, frontpage views
```

## Regions

| Machine name | Label                |
|--------------|----------------------|
| `nav_left`   | Nav (left)           |
| `nav`        | Nav (middle)         |
| `nav_right`  | Nav (right)          |
| `precontent` | Precontent           |
| `sidebar`    | Sidebar              |
| `content`    | Content              |
| `subcontent` | Subcontent           |
| `footer_1`   | Footer top left      |
| `footer_2`   | Footer top right     |
| `footer_3`   | Footer bottom left   |
| `footer_4`   | Footer bottom right  |
| `footer`     | Footer (centered)    |
| `overlay`    | Overlay              |

## Key hook implementations (`bestor.theme`)

### `bestor_theme_suggestions_node_alter`
Adds `node__lemma__full` suggestion when viewing a lemma node in full display mode. The suggestion is injected via PHP rather than relying on the filename convention because `isLemma()` in `NodeContentAnalyzer` encapsulates which bundles count as "lemma" — that set can change without renaming template files.

### `bestor_preprocess_field__field_media_multiple`
When `field_media_multiple` has more than one item, injects `is_gallery`, `gallery_id`, and `display_mode` variables for the `c-media-gallery` SDC component and the glightbox lightbox. `display_mode` is `'slider'` or `'grid'` based on `#cardinality_exception`.

### `bestor_theme_suggestions_alter` (views)
Adds `views_view__database_advanced_search` suggestion to the `database_advanced_search_affiliations` view so it shares the same template as `database_advanced_search`.

### `bestor_preprocess_views_view`
Attaches `bestor/database-search` to the `database` view, and both `bestor/database-search` and `bestor/advanced-search-filters` to the two advanced-search views.

### `bestor_theme_suggestions_form_alter` / `bestor_theme_suggestions_block_alter`
Adds `__pagecontent` suggestion to the contact form and to system 403/404 blocks so they share a generic page-content layout template.

### `bestor_theme_suggestions_select_alter`
Adds `select__author_dropdown` to the author select on the database view page, enabling a custom styled dropdown.

## Libraries

| Library                   | Contents                                                       |
|---------------------------|----------------------------------------------------------------|
| `bestor/global`           | `bestor.css`, `footer.css`, `bestor.js` — loaded on all pages |
| `bestor/database-search`  | `database-search.js` — view-specific search JS                |
| `bestor/advanced-search-filters` | `advanced-search-filters.css` + JS — advanced filter UI |
| `bestor/lightbox`         | Vendored `glightbox.min.css/js` + `lightbox-init.js`          |

The `lightbox` library is vendored under `libraries/glightbox/` and declared in `bestor.libraries.yml` with `minified: true`. It is not managed through npm.

## The `c-media-gallery` SDC component

Located at `components/molecules/c-media-gallery/`. This is the only SDC component owned by `bestor`; all other components are inherited from `jakarta`. It lives here because it is specific to the Bestor media field and is not reusable across other sites.

## Dependencies

- Base theme: `jakarta`
- Runtime PHP dependency: `bestor_content_helper.node_content_analyzer` (used in node theme suggestions)
- Library dependency: `views_filters_summary/views_filters_summary` (required by `bestor/database-search`)
