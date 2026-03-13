## Range overlap search — implementation status

### Concept

Two child fields on a record define a date/integer range `[start, end]`.
The exposed filter shows two inputs: **From** and **To**.
A record matches when the filter range overlaps the data range:

```
(record.end >= filter.from  OR  record.end is missing)
AND
(record.start <= filter.to  OR  record.start is missing)
```

Both conditions must be satisfied by the **same** nested document. This is why
both OR-conditions live inside one `NestedParentFieldConditionGroup` with AND
conjunction — two separate nested queries would allow cross-object false
positives.

Partial input is supported:
- Only **From** → only the `end >= from OR end missing` condition is added
- Only **To**   → only the `start <= to OR start missing` condition is added
- Both         → full overlap check

---

### Design decisions

| Decision | Choice |
|---|---|
| Multiple pairs per filter | No — one pair per filter instance |
| From/To URL keys | Hardcoded as `from` and `to` |
| Shown always | No — only when ≥ 2 rangeable subfields exist |
| Field dropdowns | Only rangeable fields shown |
| Storage location | Inside `field_settings['range_pair']` — saves automatically with the field_settings blob, grouped under "Available fields" in the config form |
| Reserved keys `from`/`to` | Warning shown in range pair form when enabled; validation blocks saving if any child field uses them as identifier |

URL pattern: `?filter_id[from][value]=1800&filter_id[to][value]=1802`

---

### NULL field handling

Elasticsearch does not index null fields — a range condition on a missing field
silently fails. The correct semantic for a null start/end is **COALESCE(end,
start)** and **COALESCE(start, end)** — a null bound is replaced by the other
bound, making the record a point range for filtering purposes.

**Fix:** each range bound uses a nested OR with an AND sub-group:
- `end >= from  OR  (end missing AND start >= from)` — COALESCE(end, start)
- `start <= to  OR  (start missing AND end <= to)` — COALESCE(start, end)

These sub-groups are expressed inside **one** nested query using
`NestedChildFieldConditionGroup` (with recursive nesting for the AND branch).
This is critical — two separate nested queries would evaluate independently per
document, allowing cross-object false positives.

---

### Done

#### ✅ `select_range` on date fields

Two root causes fixed:
- `search_api_type` was never saved. Fix: live lookup via
  `$this->nestedFieldHelper->getChildFieldType()` at query time.
- `convertYearToTimestamp()` returned a Unix integer, but dates are stored as
  ISO 8601 strings. Fix: renamed to `convertYearToDateString()`, returns
  `date('c', mktime(...))`.

#### ✅ `NestedIndexFieldHelper` injected into `RelationshipFilter`

Injected directly; removed the unnecessary delegate method that was previously
added to `NestedFieldViewsFilterConfigurator`.

#### ✅ Query infrastructure — null-safe nested sub-groups

New class hierarchy for nested condition groups:

```
ConditionGroup  (Search API)
  └── NestedConditionGroupBase
        ├── NestedParentFieldConditionGroup
        └── NestedChildFieldConditionGroup
```

**`NestedConditionGroupBase`** — shared base:
- Properties: `$parentFieldName`, `$index`, `$queryBuilder`
- Setters: `setParentFieldName()`, `setIndex()`, `setQueryBuilder()`, `getParentFieldName()`
- `addChildFieldCondition()` — resolves full ES field path and creates a
  `NestedChildFieldCondition`

**`NestedParentFieldConditionGroup`** — top-level nested query:
- `isNestedParentField()` — used by `NestedFilterBuilder` to detect nested groups
- `addChildConditionGroup()` — inherited from base

**`NestedChildFieldConditionGroup`** — inner OR/AND sub-group inside the nested query:
- Inherits everything from base including `addChildConditionGroup()` for deeper nesting
- Exists to give `NestedFilterBuilder` a concrete type to `instanceof` check

**`NestedFilterBuilder`** updated — condition processing is extracted into
`buildConditionGroupSubfilters(NestedConditionGroupBase $group)` which recurses
into `NestedChildFieldConditionGroup` at any depth:

```php
if ($condition instanceof NestedChildFieldConditionGroup) {
  $inner = $this->buildConditionGroupSubfilters($condition, $index_fields);
  $subfilters[] = $this->wrapWithConjunction($inner, $condition->getConjunction());
}
elseif ($condition instanceof NestedChildFieldCondition) {
  $subfilters[] = $this->buildFilterTerm($condition, $index_fields);
}
```

#### ✅ `buildIntRangeSubForm()` — shared private method

Shared between `addWidgetSelector()` (per-field) and `buildRangePairForm()` (pair).
Builds the four int_range form elements with correct `#states` visibility logic.
Pair defaults: `max = current year`, `use_current_year_max = TRUE`.

#### ✅ Config form — `NestedFieldViewsFilterConfigurator::buildRangePairForm()`

- Guard in `buildFilterConfigForm()`: only called when ≥ 2 rangeable subfields exist
- Form placed at `$form['field_settings']['range_pair']` (inside "Available fields")
- Reads saved data from `$saved_settings['field_settings']['range_pair']`
- `#states` paths include `[field_settings]` segment
- Warning markup shown when enabled, listing `from` and `to` as reserved identifiers
- Delegates int_range sub-form to `buildIntRangeSubForm()`

#### ✅ Exposed form — `NestedExposedFormBuilder::buildRangePairWidget()`

Renders `from` and `to` inputs (textfield or select_range) into the exposed form.

#### ✅ `RelationshipFilter` — all methods updated

- `getFieldSettings()` — strips `range_pair` key so field loops stay clean
- `getRangePairConfig()` — reads `$this->options['field_settings']['range_pair']`
- `buildRangePairConditions()` — builds one `NestedParentFieldConditionGroup` (AND)
  with two inner `NestedChildFieldConditionGroup` (OR) sub-groups for null safety
- `query()` — calls `buildRangePairConditions()` after regular conditions
- `valueForm()` — calls `buildRangePairWidget()` when pair is enabled and exposed
- `hasActiveFilterValues()` — checks `from`/`to` keys when pair is enabled
- `validateOptionsForm()` — skips `range_pair` key in field loop; validates that
  start/end fields are selected, are different, and that no regular field uses
  `from` or `to` as its child filter identifier

---

### To do

- [ ] **Test end-to-end**: configure a filter with two date fields, enable range pair,
      verify URL params and that the Elasticsearch query contains one nested query
      with two inner `bool.should` sub-conditions.
- [ ] **Test partial input**: only From filled, only To filled — confirm only the
      relevant OR sub-group is added.
- [ ] **Test null handling**: verify records with null start or end are correctly
      included or excluded depending on the filter values.
- [ ] **Test select_range widget**: verify year dropdown options build correctly for
      the pair, and that year-to-date-string conversion fires for date-typed fields.
- [ ] **adminSummary()**: currently shows only enabled child field count; consider
      whether range pair enabled state should be reflected.

---

### Files changed

| File | Changes |
|---|---|
| `src/SearchAPI/Query/NestedConditionGroupBase.php` | New — shared base for nested condition groups |
| `src/SearchAPI/Query/NestedChildFieldConditionGroup.php` | New — inner OR/AND sub-group within a nested query |
| `src/SearchAPI/Query/NestedParentFieldConditionGroup.php` | Extends base; adds `addChildConditionGroup()` |
| `src/SearchAPI/Query/NestedFilterBuilder.php` | Handles `NestedChildFieldConditionGroup` in condition loop |
| `src/Plugin/views/filter/RelationshipFilter.php` | `getFieldSettings()`, `getRangePairConfig()`, `buildRangePairConditions()`, `query()`, `valueForm()`, `hasActiveFilterValues()`, `validateOptionsForm()` |
| `src/Views/Config/NestedFieldViewsFilterConfigurator.php` | `buildRangePairForm()`, `buildIntRangeSubForm()`, rangeable guard |
| `src/Views/Widget/NestedExposedFormBuilder.php` | `buildRangePairWidget()` |
