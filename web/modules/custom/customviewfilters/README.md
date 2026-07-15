# customviewfilters

Three small Views plugins that fill gaps in Drupal core and contrib.

## Purpose

Provides a permission-based filter, a string filter extension, and a content-type machine name field for use in Views configurations.

## Architecture overview

```
src/Plugin/views/
├── filter/
│   ├── CreatePermissionFilter.php      — filters content types/vocabs by create permission
│   └── CustomExtendedStringFilter.php  — string filter with additional operators
└── field/
    └── ContentTypeMachineName.php      — Views field exposing the node bundle machine name
```

## Plugins

### `CreatePermissionFilter` (`@ViewsFilter("create_permission_filter")`)
Filters a list of content types or vocabularies to those that a given role (or the current user) has create permission for. Useful for admin views that should only show content types relevant to the logged-in editor. The filter checks `create <type> content` for node types and `create terms in <vocab>` for vocabularies. Adds `user.permissions` to the render cache context so the result varies per user.

### `CustomExtendedStringFilter`
Extends the built-in string filter with additional comparison operators not available in core.

### `ContentTypeMachineName` (Views field)
Exposes the node bundle machine name as a field in a View. Useful when you need the raw machine name for theming or logic rather than the human-readable label.

## Configuration

All plugins are configured through the standard Views UI. The schema at `config/schema/customviewfilters.schema.yml` ensures filter options are exported correctly in config.

## Dependencies

- `drupal:views`
- No custom module dependencies
