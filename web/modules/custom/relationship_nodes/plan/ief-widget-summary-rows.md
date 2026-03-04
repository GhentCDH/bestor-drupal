# Plan: IEF Widget → Summary Rows (lazy IEF forms)

**Goal**: When a node with many relations is opened, no longer render all IEF
subforms at once. Instead show a compact table of summary rows (label + Edit +
Remove). Only the one entity actively being edited gets a real IEF form.

---

## Root Causes

### 1. OOM (primary blocker)

`formMultipleElements()` calls `parent::formMultipleElements()`, which iterates
over every entity in `widget_state['entities']` and calls `formElement()` for
each. `formElement()` builds a full Inline Entity Form for every entity
(renders all field widgets, loads displays, etc.). With 50+ relations this
exhausts PHP memory.

The current code then replaces those rendered forms with summary rows — but the
damage is done: all forms were already built in RAM.

**Fix**: Do NOT call `parent::formMultipleElements()`. Instead initialise the
widget state manually and call `parent::formElement()` only for the one delta
that is currently in edit mode.

### 2. `#relation_extended_widget` flag placed at wrong level (secondary bug)

`formElement()` sets the flag at:
```
$form[$field]['widget'][$delta]['inline_entity_form']['#relation_extended_widget']
```

But `RelationFormHelper::getRelationExtendedWidgetFields()` reads it from:
```
$form[$field]['widget']['#relation_extended_widget']   // widget container level
```

Because the paths don't match, `isParentFormWithRelationSubforms()` always
returns `FALSE`. Consequence: `WidgetSubmitHandler::doSubmit()` never calls
`handleRelationWidgetSubmit()`, so **save and delete operations never fire**.

**Fix**: Set `$element['#relation_extended_widget'] = TRUE` on the container
element inside `formMultipleElements()` (not in `formElement()`).

### 3. Minor issues

- Debug `dpm()` calls throughout `RelationIefWidget` must be removed.
- The Remove-click detection in `extractFormValues()` (block 2) is dead code:
  `removeRelation()` already updated `rn_summary_entities` before
  `extractFormValues()` runs, so `$summary_entities[$delta]` is never found.
  The block can be simplified or removed.
- AJAX callback `return $form[$ief_id]['widget']` is correct only for top-level
  forms (where `$ief_id == $field_name`). For nested forms this would need
  adjustment, but top-level is the current use-case.

---

## Implementation Plan

### Step 1 — Rewrite `formMultipleElements()` in `RelationIefWidget`

Replace the entire method with a new version that:

1. Computes `$field_name`, `$parents`, `$ief_id`.
2. **CRITICAL — Manually initialises widget state if not yet set.** On the
   very first page load IEF has no state at all. The `if (empty($widget_state))`
   guard is the only place where `$items` is read. If this block is skipped or
   placed incorrectly, `widget_state['entities']` stays empty and the widget
   renders nothing. This mirrors what `InlineEntityFormWidget::formMultipleElements()`
   does internally, but we do it ourselves so we can skip the rest of what that
   method does (i.e. building all entity subforms).
3. Reads `$editing_delta` and `$delete_ids` from form state.
4. Builds the wrapper element with `#prefix`/`#suffix` AJAX div **and**
   sets `#relation_extended_widget = TRUE` here (fixing bug 2).
5. Iterates `widget_state['entities']`:
   - Skip entities whose ID is in `$delete_ids`.
   - For the `$editing_delta`: call `parent::formElement($items, $delta, [], $form, $form_state)` to get the real IEF form, then append the Cancel button.
   - For all others: build a lightweight summary container.
6. Saves the snapshot (`rn_summary_entities`) for `extractFormValues()`.
7. Optionally preserves the `add_more` button by delegating only that part to
   parent — or replicates it if the parent call is too expensive.

Skeleton:

```php
protected function formMultipleElements(
  FieldItemListInterface $items,
  array &$form,
  FormStateInterface $form_state
) {
  $field_name  = $this->fieldDefinition->getName();
  $parents     = array_merge($form['#parents'], [$field_name]);
  $ief_id      = $this->makeIefId($parents);

  // --- 1. Initialise widget state from $items (only on first build) ---
  $widget_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
  if (empty($widget_state)) {
    $widget_state = ['entities' => [], 'delete' => [], 'form' => NULL];
    foreach ($items as $delta => $item) {
      if (!empty($item->entity)) {
        $widget_state['entities'][$delta] = [
          'entity'     => $item->entity,
          'weight'     => $delta,
          'needs_save' => FALSE,
        ];
      }
    }
    static::setWidgetState($form['#parents'], $field_name, $form_state, $widget_state);
  }

  // --- 2. Current state ---
  $editing_delta = $form_state->get(['rn_editing_delta', $ief_id]);
  $delete_ids    = $form_state->get(['rn_delete_ids',    $ief_id]) ?? [];

  // --- 3. Build wrapper ---
  $element = [
    '#prefix'                  => "<div id=\"rn-widget-{$ief_id}\">",
    '#suffix'                  => '</div>',
    '#relation_extended_widget' => TRUE,   // <-- fixes bug 2
  ];

  // --- 4. Rows ---
  $all_entities = [];
  foreach ($widget_state['entities'] as $delta => $item_data) {
    $entity    = $item_data['entity'];
    $entity_id = $entity->id();

    if ($entity_id && isset($delete_ids[$entity_id])) {
      continue; // skip deleted
    }

    $all_entities[$delta] = $entity;

    if ($delta === $editing_delta) {
      // Full IEF form — only for this one entity
      $element[$delta] = parent::formElement($items, $delta, [], $form, $form_state);
      $element[$delta]['#relation_extended_widget'] = TRUE;
      $element[$delta]['cancel'] = [
        '#type'                   => 'submit',
        '#value'                  => t('Cancel'),
        '#name'                   => "rn_cancel_{$ief_id}_{$delta}",
        '#delta'                  => $delta,
        '#ief_id'                 => $ief_id,
        '#submit'                 => [[static::class, 'cancelEditRelation']],
        '#ajax'                   => ['callback' => [static::class, 'relationRowAjaxCallback'], 'wrapper' => "rn-widget-{$ief_id}"],
        '#limit_validation_errors' => [],
      ];
    } else {
      // Lightweight summary row — no IEF subform
      $element[$delta] = [
        '#type'       => 'container',
        '#attributes' => ['class' => ['rn-summary-row']],
        'label'  => ['#markup' => $entity->label()],
        'edit'   => [
          '#type'                   => 'submit',
          '#value'                  => t('Edit'),
          '#name'                   => "rn_edit_{$ief_id}_{$delta}",
          '#delta'                  => $delta,
          '#ief_id'                 => $ief_id,
          '#submit'                 => [[static::class, 'openEditRelation']],
          '#ajax'                   => ['callback' => [static::class, 'relationRowAjaxCallback'], 'wrapper' => "rn-widget-{$ief_id}"],
          '#limit_validation_errors' => [],
        ],
        'remove' => [
          '#type'                   => 'submit',
          '#value'                  => t('Remove'),
          '#name'                   => "rn_remove_{$ief_id}_{$delta}",
          '#delta'                  => $delta,
          '#ief_id'                 => $ief_id,
          '#submit'                 => [[static::class, 'removeRelation']],
          '#ajax'                   => ['callback' => [static::class, 'relationRowAjaxCallback'], 'wrapper' => "rn-widget-{$ief_id}"],
          '#limit_validation_errors' => [],
        ],
      ];
    }
  }

  // --- 5. Snapshot for extractFormValues safety net ---
  $form_state->set(['rn_summary_entities', $ief_id], $all_entities);

  // --- 6. Borrow add_more from parent without building entity subforms ---
  // Temporarily empty entities in widget_state so parent builds zero subforms,
  // then restore. See Step 4 in the plan for the full rationale.
  $saved_entities = $widget_state['entities'];
  $widget_state['entities'] = [];
  static::setWidgetState($form['#parents'], $field_name, $form_state, $widget_state);

  $fake_element = parent::formMultipleElements($items, $form, $form_state);

  $widget_state['entities'] = $saved_entities;
  static::setWidgetState($form['#parents'], $field_name, $form_state, $widget_state);

  if (isset($fake_element['add_more'])) {
    $element['add_more'] = $fake_element['add_more'];
    if (!empty($element['add_more']['#ajax'])) {
      $element['add_more']['#ajax']['wrapper'] = "rn-widget-{$ief_id}";
    }
  }

  return $element;
}
```

### Step 2 — Simplify `formElement()`

The `#relation_extended_widget` flag is now set at the container level in
`formMultipleElements()`. The override of `formElement()` is no longer needed.
Remove it (or keep it as a pass-through if the parent signature requires it).

### Step 3 — Clean up `extractFormValues()`

- Remove the dead Remove-click detection block (the `if ($trigger && ...)` block
  in step 2). `removeRelation()` already handles the state update before
  `extractFormValues()` even runs.
- Remove all `dpm()` calls.

**CRITICAL — The safety net must stay fully intact.**
The safety net (block 4: re-append `rn_summary_entities` to `$items`) is not
optional cleanup — it is the only mechanism that keeps non-editing entities
alive across form rebuilds. If it is weakened or removed, any entity that was
rendered as a summary row (i.e. not in edit mode) will silently disappear from
`$items`, and the field will lose data on save. The rule is strict:

> Every entity present in `rn_summary_entities` that is not in `rn_delete_ids`
> and not already processed by the parent call MUST be appended back to `$items`.

Do not add conditions or early-exits that could skip this block.

### Step 4 — "Add new relation" button (use Option A)

**Recommended: Option A** — borrow the `add_more` element from parent without
letting parent build any entity subforms.

The trick: parent only builds entity subforms for entries in
`widget_state['entities']`. If we temporarily empty that list, call parent, grab
`add_more`, then restore the real list, parent does zero subform work:

```php
// Temporarily clear entities so parent builds only the add_more button.
$saved_entities = $widget_state['entities'];
$widget_state['entities'] = [];
static::setWidgetState($form['#parents'], $field_name, $form_state, $widget_state);

$fake_element = parent::formMultipleElements($items, $form, $form_state);

// Restore the real entity list.
$widget_state['entities'] = $saved_entities;
static::setWidgetState($form['#parents'], $field_name, $form_state, $widget_state);

if (isset($fake_element['add_more'])) {
  $element['add_more'] = $fake_element['add_more'];
  if (!empty($element['add_more']['#ajax'])) {
    $element['add_more']['#ajax']['wrapper'] = "rn-widget-{$ief_id}";
  }
}
```

Why Option A instead of Option B (manual button):
- The IEF `add_more` button contains hidden logic: access checks, cardinality
  enforcement, translation context, and the IEF-ID mapping needed to open the
  right form. Replicating all of that manually is error-prone.
- The temporary-empty approach costs one cheap parent call with zero entity
  iteration, keeping memory impact negligible.

> **Note on timing**: this temporary-empty + restore block must run AFTER the
> snapshot (`rn_summary_entities`) is saved (step 5 in the skeleton), so the
> parent call cannot interfere with our snapshot.

### Step 5 — Verify `widget_state` consistency after Remove + rebuild

After `removeRelation()` runs and the form rebuilds:
- `rn_delete_ids[$entity_id]` is set.
- `rn_summary_entities` no longer contains the entity.
- `widget_state['entities']` still contains the entity (it's only cleaned up
  by parent's `extractFormValues()`, which processes only the IEF sub-elements
  present in the render array).

The new `formMultipleElements()` skips entities in `$delete_ids` when building
rows, so the removed entity is never rendered. The `extractFormValues()` safety
net only re-adds entities that are in `rn_summary_entities` (which excludes the
removed one). On final save, `handleRelationWidgetSubmit()` calls
`syncService->deleteNodes($delete_ids)` directly.

**Potential issue**: After remove + rebuild, `widget_state['entities']` still
has the removed entity. If `parent::formElement()` is called for the editing
entity, it calls `static::getWidgetState()` which returns the full state
including the removed entity. This is fine because we only render the editing
one; the removed entity is ignored in the UI and explicitly deleted in the
submit handler.

**One edge case to check**: When the user saves without editing anything (pure
summary mode), NO `parent::formElement()` is called. `extractFormValues()` runs:
- Parent call with `$temp_form` (no IEF elements) → `$items` becomes empty.
- Safety net re-adds all `rn_summary_entities` to `$items`.
- `handleRelationWidgetSubmit()` calls `saveSubformRelations()` with
  `widget_state['entities']` — make sure `saveSubformRelations()` in
  `RelationSync` does NOT re-save entities that have `needs_save = FALSE`.
  Verify this in `RelationSync::saveSubformRelations()`.

### Step 6 — Verify `getWidgetState()` / `setWidgetState()` API

`InlineEntityFormWidget` provides:
```php
public static function getWidgetState(array $parents, string $field_name, FormStateInterface $form_state): array
public static function setWidgetState(array $parents, string $field_name, FormStateInterface $form_state, array $widget_state): void
```
These operate on `$form_state->get(['inline_entity_form', $ief_id])`.
Confirm the exact method signatures and accessibility before implementing Step 1.
If the methods are protected/not directly usable statically, fall back to:
```php
$form_state->get(['inline_entity_form', $ief_id])
$form_state->set(['inline_entity_form', $ief_id], $widget_state)
```

---

## Files to Change

| File | Change |
|------|--------|
| `src/Plugin/Field/FieldWidget/RelationIefWidget.php` | Rewrite `formMultipleElements()`, remove/simplify `formElement()`, clean up `extractFormValues()`, remove `dpm()` |
| `src/Form/Entity/RelationEntityFormHandler.php` | No change expected |
| `src/Form/Entity/RelationFormHelper.php` | No change (the flag fix in Step 1 is in the widget) |
| `src/Form/Widget/WidgetSubmitHandler.php` | No change |

---

## Test Checklist

1. [ ] Open a node with 10+ relations → form loads without OOM
2. [ ] Summary rows are shown (label, Edit, Remove buttons)
3. [ ] Click Edit on a row → IEF form opens inline for that relation, others stay as summary rows
4. [ ] Edit fields and click Save (parent node save) → changes are persisted
5. [ ] Click Cancel → IEF form closes, summary row restored
6. [ ] Click Remove on a row → row disappears, entity is deleted on save
7. [ ] Click "Add new relation" → new IEF form appears, can be filled and saved
8. [ ] Open node again after save → correct relations shown, deleted ones gone
9. [ ] `#relation_extended_widget` flag is present at `$form[$field]['widget']` level
     (verifiable with `dpm($form['field_xxx']['widget']['#relation_extended_widget'])`)
10. [ ] `WidgetSubmitHandler::doSubmit()` calls `handleRelationWidgetSubmit()` for relation fields
