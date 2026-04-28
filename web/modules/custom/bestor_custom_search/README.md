# bestor_custom_search

Standalone full-text search block for the Bestor database view.

## Purpose

Provides a simple text search form that can be placed as a block anywhere on the site. On submit it redirects to `view.database.page_1` with the query as the `fullsearch` GET parameter. This is distinct from the Elasticsearch-backed advanced search — it targets the `database` View directly.

## Architecture overview

```
src/
├── Form/DatabaseFullTextSearchForm.php   — GET form, submits to view.database.page_1
└── Plugin/Block/DatabaseFullTextSearchBlock.php — exposes the form as a placeable block
js/
└── database_search_form.js              — client-side prefill after AJAX navigation
```

## Configuration

No configuration form. The route (`view.database.page_1`) and the parameter name (`fullsearch`) are hard-coded in the form class. Place the block via the standard Drupal block UI.

## Known notes

The JS library handles client-side prefill of the search input after AJAX-driven page transitions. A Dutch inline comment in the form (`// Library zorgt voor client-side prefill na AJAX`) documents this intent.

## Dependencies

- `drupal:node` (implicit via Views)
- No custom module dependencies
