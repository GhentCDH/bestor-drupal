# search_api_range_filter

Range-overlap filter for Search API Views.

## Purpose

Provides a Views filter (`@ViewsFilter("search_api_range_filter")`) that matches records whose stored `[start, end]` interval overlaps a user-supplied `[from, to]` range. Designed for date or numeric fields on a Search API index (e.g. filtering events by active period).

## Architecture overview

```
src/Plugin/views/filter/RangeFilter.php   — the Views filter plugin
search_api_range_filter.views.inc         — Views data integration
config/schema/                            — Views config schema for export
```

## How the overlap logic works

A record matches when its stored interval overlaps the filter range:

```
COALESCE(end, start) >= from   AND   COALESCE(start, end) <= to
```

Expanded into Search API condition groups:
- `(end >= from) OR (end IS NULL AND start >= from)`
- `(start <= to) OR (start IS NULL AND end <= to)`

This handles records where the end field is empty (open-ended intervals still match if the start field satisfies the condition).

## Configuration

In the Views filter admin form:
- **Start field** / **End field** — two range-capable fields from the Search API index (date, integer, decimal, or float)
- **Widget type** — `textfield` or `Dropdown (consecutive integer range)`
- **From/To labels** — customizable labels for the exposed filter inputs
- For the dropdown widget: min/max values (with optional "use current year" checkbox)

Date fields: when using the select_range (year) widget on a `date` type field, year integers are automatically converted to ISO 8601 boundary strings before the Elasticsearch query (`>= year` → Jan 1 at 00:00:00; `<= year` → Dec 31 at 23:59:59).

## Dependencies

- `search_api:search_api`
- No custom module dependencies
