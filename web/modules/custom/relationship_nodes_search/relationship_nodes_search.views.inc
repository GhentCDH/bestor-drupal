<?php

/**
 * @file
 * Provides Views integration for relationship_nodes_search.
 */

/**
 * Implements hook_views_data_alter().
 */
function relationship_nodes_search_views_data_alter(array &$data): void {
  foreach ($data as $table_name => &$table) {
    $is_index = str_starts_with($table_name, 'search_api_index_');
    $is_datasource = str_starts_with($table_name, 'search_api_datasource_');
    $is_node = str_starts_with($table_name, 'search_api_entity_node_');

    // Only process Search API-related tables.
    if (!$is_index && !$is_datasource && !$is_node) {
      continue;
    }

    // Collect fields to remove after the loop — unsetting keys while
    // iterating over a referenced array is unreliable in PHP.
    $fields_to_remove = [];

    foreach ($table as $field_name => &$field) {
      // Skip the table metadata entry and any non-array values.
      if ($field_name === 'table' || !is_array($field)) {
        continue;
      }

      // Search API may use double underscores in 'real field' names (e.g.
      // computed_relationshipfield__concept__concept), so we match both the
      // Views key and the real field using str_contains rather than a strict
      // prefix check.
      $real_field = $field['real field'] ?? $field_name;

      $is_computed_relationship = str_contains($field_name, 'computed_relationshipfield') || str_contains($real_field, 'computed_relationshipfield');
      $is_nested_relationship = (str_starts_with($field_name, 'relationship_info_') && str_ends_with($field_name, '_nested')) || (str_starts_with($real_field, 'relationship_info_') && str_ends_with($real_field, '_nested'));

      if (!$is_computed_relationship && !$is_nested_relationship) {
        continue;
      }

      if ($is_node || $is_datasource) {
        // Search API registers these fields on entity and datasource tables,
        // but they have no field definition and cannot be rendered there.
        // The usable entry lives on the index table (search_api_index_*).
        // Queue for removal to avoid modifying the array mid-iteration.
        $fields_to_remove[] = $field_name;
      }
      elseif ($is_index) {
        // Assign the custom Views field handler so the nested JSON structure
        // can be rendered correctly.
        if (isset($field['field'])) {
          $field['field']['id'] = 'search_api_relationship_field';
        }

        // Assign the custom filter handler for nested Elasticsearch queries.
        $field['filter'] = [
          'id' => 'search_api_relationship_filter',
          'allow empty' => TRUE,
        ];

        // Sorting and arguments are not meaningful for nested relationship
        // fields, so remove them to keep the Views UI clean.
        unset($field['sort'], $field['argument']);
      }
    }
    unset($field); // Break the last reference from the foreach.

    // Now safely remove the queued fields from the original array.
    foreach ($fields_to_remove as $field_name) {
      unset($data[$table_name][$field_name]);
    }
  }
}