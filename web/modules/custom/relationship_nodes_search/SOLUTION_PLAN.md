## Problem — Better widgets for integer and date subfields

### 1A — Integer range dropdown (`select_range`)

Widget keys in use:
- `textfield` — free text input
- `select_indexed` — dropdown populated from Search API indexed values
- `select_range` — dropdown of a manually configured consecutive integer range
- `date_select` — date picker (outstanding, see 1B)

#### 1A-2 — Better default values for the integer range

**Current:** `min` and `max` default to `NULL` in the config form, which renders as
an empty number field. The fallback in `buildIntRangeOptions()` is `date('Y')` for
both, producing a single-item dropdown — useless.

**Fix 1 — config form defaults** in `addWidgetSelector()`:
```php
$form['field_settings'][$field_name]['int_range']['min'] = [
  '#type'          => 'number',
  '#default_value' => $config['int_range']['min'] ?? 1,   // was: NULL
  ...
];

$form['field_settings'][$field_name]['int_range']['max'] = [
  '#type'          => 'number',
  '#default_value' => $config['int_range']['max'] ?? 10,  // was: NULL
  ...
];
```

`1` and `10` are neutral starting points — the admin will always set these to
something meaningful (e.g. 1900 / 2030 for a year range).

**Fix 2 — runtime fallback** in `buildIntRangeOptions()` (`NestedExposedFormBuilder`):
```php
$min = ... ($int_range['min'] ?? 1);   // was: date('Y')
$max = ... ($int_range['max'] ?? 10);  // was: date('Y')
```

This way, even if the admin forgot to save the config, the dropdown shows
something (1–10) rather than a single entry with the current year.

---

#### 1A-3 — Conditional states: hide min/max when "use current year" is checked

**Current problem:** when the admin checks "Use current year as minimum", the
`min` number field stays visible and editable, even though it has no effect. Same
for `max` / `use_current_year_max`. The fields should disappear when their
respective checkbox is checked.

**How Drupal `#states` AND conditions work:**
Multiple selector entries under the same state key are combined with AND.
So to make `min` visible only when `widget = select_range` **and** `use_current_year_min`
is **unchecked**, provide both conditions under `'visible'`:

```php
'visible' => [
  ':input[name="options[field_settings][FIELD][widget]"]'                          => ['value' => 'select_range'],
  ':input[name="options[field_settings][FIELD][int_range][use_current_year_min]"]' => ['checked' => FALSE],
],
```

**Implementation in `addWidgetSelector()`:**

Build the input names once at the top of the `if ($config['supports_range'])` block:

```php
$field_prefix       = ($context_prefix ? $context_prefix . '[' : '') . 'field_settings][' . $field_name . ']';
$widget_input       = ($context_prefix ? $context_prefix . '[' : '') . 'field_settings][' . $field_name . '][widget]';
$cur_year_min_input = $field_prefix . '[int_range][use_current_year_min]';
$cur_year_max_input = $field_prefix . '[int_range][use_current_year_max]';
```

Then replace the current `$range_field_state` (used for all four sub-fields) with
**three separate states**:

```php
// Checkboxes: visible when select_range widget is active
$range_visible_state = array_merge($disabled_state, [
  'visible' => [
    ':input[name="' . $widget_input . '"]' => ['value' => 'select_range'],
  ],
]);

// min field: visible only when select_range AND use_current_year_min unchecked
$min_state = array_merge($disabled_state, [
  'visible' => [
    ':input[name="' . $widget_input . '"]'       => ['value' => 'select_range'],
    ':input[name="' . $cur_year_min_input . '"]'  => ['checked' => FALSE],
  ],
]);

// max field: visible only when select_range AND use_current_year_max unchecked
$max_state = array_merge($disabled_state, [
  'visible' => [
    ':input[name="' . $widget_input . '"]'       => ['value' => 'select_range'],
    ':input[name="' . $cur_year_max_input . '"]'  => ['checked' => FALSE],
  ],
]);
```

Apply them:
```
$form[...]['int_range']['min']                  → '#states' => $min_state
$form[...]['int_range']['use_current_year_min'] → '#states' => $range_visible_state
$form[...]['int_range']['max']                  → '#states' => $max_state
$form[...]['int_range']['use_current_year_max'] → '#states' => $range_visible_state
```

> **Note on the container:** the `int_range` container itself has no `#states`
> right now. That is fine — it is invisible because all its children are. If you
> want the container title/description to also disappear, change its `#type` to
> `fieldset` and give it `'#states' => $range_visible_state`. The `container` type
> has no visible wrapper so `#states` on it has no visual effect.

---

### 1B — Date picker widget

#### Step 1 — expose `search_api_type` in field configs

**File:** `NestedFieldViewsFilterConfigurator::prepareFilterFieldConfigurations()`

`$context['capabilities'][$field_name]['search_api_type']` is already available
(built by `buildViewsContext()`), but it is never written into `$config`. Add it
inside the merge loop for **all** fields, before the `if (isset(...))` block:

```php
foreach ($configs as $field_name => &$config) {
  $config['search_api_type'] = $context['capabilities'][$field_name]['search_api_type'] ?? NULL;

  if (isset($field_settings[$field_name])) {
    // ... existing merges ...
  }
}
```

#### Step 2 — add `date_select` widget option

**File:** `NestedFieldViewsFilterConfigurator::addWidgetSelector()`

After the `select_range` block, add:
```php
if (($config['search_api_type'] ?? NULL) === 'date') {
  $widget_options['date_select'] = $this->t('Date picker');
}
```

Date fields also pass `supports_range`, so `select_range` will appear for them
too. Both being available is intentional — an admin might prefer a year range
dropdown over a calendar picker.

#### Step 3 — implement `addDateWidget()`

**File:** `NestedExposedFormBuilder`

New protected method:
```php
protected function addDateWidget(
  array &$form,
  array $path,
  string $label,
  bool $required,
  ?array $field_value = NULL
): void {
  $path[] = 'value';
  $this->setFormNestedValue($form, $path, [
    '#type'          => 'date',
    '#title'         => $label,
    '#default_value' => $field_value['value'] ?? '',
    '#required'      => $required,
  ]);
}
```

Wire it in `buildChildFieldElement()`:
```php
case 'date_select':
  $this->addDateWidget($form, $path, $label, $required, $field_value);
  break;
```

#### Step 4 — convert Y-m-d to Unix timestamp before querying

**File:** `RelationshipFilter::buildFilterConditions()`

Search API stores date fields as Unix timestamps (integers). The HTML `date`
input returns `Y-m-d` strings. Add this block **after** `sanitizeFieldValue()`
and **before** the empty-check:

```php
$field_type = $field_config['search_api_type'] ?? NULL;
if ($field_type === 'date' && is_string($value) && $value !== '') {
  $ts = strtotime($value . 'T00:00:00');
  if ($ts !== FALSE) {
    $value = $ts;
  }
}
```

> **BETWEEN operator and date ranges:** for BETWEEN you would need two separate
> date inputs (from / to) returning an array value. Deferred. For now `date_select`
> works correctly with all single-value operators (`=`, `!=`, `<`, `<=`, `>`, `>=`).
> Advise admins to use those operators when configuring a `date_select` field.

---

## Summary of files still to change

| File | Remaining work |
|---|---|
| `src/Views/Config/NestedFieldViewsFilterConfigurator.php` | Fix `int_range` defaults to 1/10; add split `#states` for min/max fields; expose `search_api_type` in field configs; add `date_select` widget option. |
| `src/Views/Widget/NestedExposedFormBuilder.php` | Fix fallback defaults in `buildIntRangeOptions()`; add `addDateWidget()`; wire `date_select` case. |
| `src/Plugin/views/filter/RelationshipFilter.php` | Add Y-m-d → timestamp conversion in `buildFilterConditions()`. |
