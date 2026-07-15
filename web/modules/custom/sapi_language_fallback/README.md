# sapi_language_fallback

Language filter with optional fallback for Search API Views.

## Purpose

Replaces the built-in "Search: Language" Views filter with one that supports a user-toggleable fallback mode. In fallback mode, nodes not available in the current interface language are still shown using the best available translation.

## Architecture overview

```
src/Plugin/views/filter/LanguageFallbackFilter.php   — @ViewsFilter("sapi_language_fallback")
sapi_language_fallback.module                        — hook_views_post_execute for deduplication
                                                       and hook_views_pre_render for entity swap
```

## Two modes

**Normal mode** (`no_fallback`): adds `search_api_language = <current_lang>` to the query. Equivalent to the built-in language filter.

**Fallback mode** (`fallback`): adds no language condition to the query (fetches all language versions), then uses `hook_views_post_execute` to deduplicate results to one per node, preferring the current language then the configured priority order.

## Why post-execute deduplication instead of query-time filtering

Search API does not support the OR-with-priority pattern needed for language fallback at query time (`language = nl OR (language != nl AND no nl translation exists)`). The Elasticsearch query cannot introspect whether a specific translation is missing; it can only filter by stored field values. Post-execute reduction is therefore the only reliable approach.

**Side effect**: in fallback mode, Elasticsearch may return more results per page than the View's pager limit, because deduplication happens after the query. Pages near the end of the result set may be shorter than expected.

## Installation note

Add this filter **instead of** (not alongside) the built-in "Search: Language" filter. Having both will apply the language condition twice and break fallback mode.

## Configuration

- **Default value**: current language only, or include fallback
- **Fallback language priority**: ordered list of language codes (one per line); if empty, the first result found is used
- **Link mode** (exposed only): link fallback results to the available language URL, or to the current-language URL (useful with the `bestor_content_helper.translation_unavailable` controller)

## Dependencies

- `search_api:search_api`
- No custom module dependencies
