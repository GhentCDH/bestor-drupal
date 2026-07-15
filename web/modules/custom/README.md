# Custom modules

## Module inventory

| Module | Status | Purpose |
|--------|--------|---------|
| `relationship_nodes` | Stable | Core bidirectional node relationship system |
| `relationship_nodes_search` | Stable | Elasticsearch / Search API integration for relationships |
| `bestor_content_helper` | Stable | Content utilities, site settings, language fallback, Twig layer |
| `customize_admin_menu_per_role` | Stable | Role-based admin dashboards and redirects |
| `cytoscape_egonetwork` | Stable | Ego network graph visualization (Cytoscape.js) |
| `bestor_custom_search` | Stable | Simple full-text search block for the database View |
| `customviewfilters` | Stable | Custom Views plugins: permission filter, string filter, machine name field |
| `views_nested_filters_summary` | Stable | Bridges views_filters_summary with Facets-managed filters |
| `search_api_range_filter` | Stable | Range-overlap filter for Search API Views |
| `views_date_past_upcoming` | Stable | Views field and sort plugins for past/upcoming date classification |
| `sapi_language_fallback` | Stable | Language filter with optional fallback for Search API Views |

## Dependency graph

```
relationship_nodes          (foundation — no custom deps)
  ├── relationship_nodes_search    (decorates elasticsearch_connector services)
  └── cytoscape_egonetwork         (uses relationship_nodes services for graph building)

bestor_content_helper       (no custom deps)
  └── customize_admin_menu_per_role

views_nested_filters_summary (optional soft dep on relationship_nodes for mirror labels)

bestor_custom_search        (standalone)
customviewfilters           (standalone)
search_api_range_filter     (standalone)
views_date_past_upcoming    (standalone)
sapi_language_fallback      (standalone)
```

## External system integrations

| System | Modules |
|--------|---------|
| Elasticsearch / `elasticsearch_connector` | `relationship_nodes_search` (decorates two core services) |
| Search API | `relationship_nodes_search`, `search_api_range_filter`, `sapi_language_fallback` |
| Facets | `relationship_nodes_search`, `views_nested_filters_summary` |
| Inline Entity Form | `relationship_nodes` |
| `entity_events` | `relationship_nodes`, `relationship_nodes_search` |

## Key architectural notes

### The relationship system
`relationship_nodes` is the central module. A "relation node" is a full content entity that sits between two parent nodes, carrying its own fields. This is not Drupal's entity_reference. If you are adding new content types that need relations, configure them through the node type form in the admin UI after enabling `relationship_nodes`.

### Elasticsearch nested indexing
`relationship_nodes_search` must use Elasticsearch's `nested` type (not flat indexing) to avoid cross-object query pollution in multi-value relationship data. The module decorates two `elasticsearch_connector` services; upgrading `elasticsearch_connector` may silently break the decoration interface.

### Disabling `relationship_nodes_search` in production
Do not disable `relationship_nodes_search` on a live site without first clearing all Search API indexes. Disabling the module leaves elasticsearch_connector unable to resolve the custom `relationship_nodes_search_nested_relationship` data type, which produces `SearchApiException` errors during index updates.

### Language fallback for `sapi_language_fallback`
Use this filter **instead of** (not alongside) the built-in "Search: Language" filter. Having both active in a View will break language filtering.

## Known limitations

See `relationship_nodes/todo.md` for the current backlog. Key items:
- Nested field display not yet functional
- Relation nodes can only be enabled on existing (not newly created) content types
- Autocomplete widget for relationship search filters not yet implemented

## Further reading

- [relationship_nodes/README.md](relationship_nodes/README.md)
- [relationship_nodes_search/README.md](relationship_nodes/modules/relationship_nodes_search/README.md)
- [bestor_content_helper/README.md](bestor_content_helper/README.md)
- [customize_admin_menu_per_role/README.md](customize_admin_menu_per_role/README.md)
- [cytoscape_egonetwork/README.md](cytoscape_egonetwork/README.md)
- [bestor_custom_search/README.md](bestor_custom_search/README.md)
- [customviewfilters/README.md](customviewfilters/README.md)
- [views_nested_filters_summary/README.md](views_nested_filters_summary/README.md)
- [search_api_range_filter/README.md](search_api_range_filter/README.md)
- [views_date_past_upcoming/README.md](views_date_past_upcoming/README.md)
- [sapi_language_fallback/README.md](sapi_language_fallback/README.md)
