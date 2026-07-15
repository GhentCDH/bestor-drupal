# views_nested_filters_summary

Bridges the `views_filters_summary` module with Facets-managed exposed filters.

## Purpose

`views_filters_summary` builds a summary of active filters for a View, but skips any filter whose `$value` is empty. Facets-managed filters store their active state in the URL processor (not in `$filter->value`), so they are always skipped. This module injects the active Facets URL values into `$filter->value` before the summary is built, and resolves entity labels (including mirror labels for relation type facets).

## Architecture overview

```
src/Service/FacetsFilterSummaryResolver.php   — injects Facets URL values; resolves labels
views_nested_filters_summary.module           — hooks: hook_views_pre_render
js/views_nested_filters_summary.js            — dynamic summary UI updates
```

## How it works

`FacetsFilterSummaryResolver::populateFacetValues()` is called in a pre-render hook. For each `facets_filter` plugin in the View, it reads the active values from the request URL and sets them on `$filter->value`. The summary module then sees populated values and includes those filters in the output.

Label resolution order (in `resolveLabel()`):
1. If the identifier is `category` → node bundle label
2. If numeric + mirror processor configured + `MirrorProvider` available → mirror label (for relation type facets)
3. If numeric → load entity from correct storage, return translated label
4. Fallback → return raw value (e.g. startchar letters)

## Two URL styles

Facets uses two URL parameter formats depending on the configured URL processor:

| Style | Example URL parameter | Array key |
|-------|-----------------------|-----------|
| Array style (standard) | `field[41]=1` | `$_GET['field']` is `['41' => '1']`; active value is the key `'41'` |
| Scalar style (simple/query string) | `field=41` | `$_GET['field']` is `'41'`; active value is the string itself |

`normalizeFacetValues()` handles both formats to produce a flat array of active value strings.

## Optional dependency on `relationship_nodes`

`FacetsFilterSummaryResolver` accepts `$mirrorProvider` as a nullable constructor argument. When `relationship_nodes` is not installed, the service container passes `NULL` and mirror label resolution is silently skipped. When `relationship_nodes` is installed, the `translate_entity_mirror_label` Facets processor triggers mirror resolution.

## Dependencies

- `views_filters_summary` (contrib) — provides the summary display this module extends
- Optional: `relationship_nodes:relationship_nodes` — for mirror label resolution
