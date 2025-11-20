<?php

/**
 * @file
 * Provides Views integration for relationship_nodes_search.
 */


/**
 * Implements hook_views_data_alter().
 */
function relationship_nodes_search_views_data_alter(array &$data) {
  foreach ($data as $table_name => &$table) {
    if (!str_starts_with($table_name, 'search_api_index_')) {
      continue;
    }
    
    foreach ($table as $field_name => &$field) {
      if ($field_name === 'table') {
        continue;
      }
         
      $sapi_field = $field['real field'] ?? $field_name;
      
      if (str_starts_with($sapi_field, 'relationship_info__') && isset($field['field'])) {
        $field['field']['id'] = 'search_api_relationship_field';   
        $field['filter'] = [
          'id' => 'search_api_relationship_filter',
          'allow empty' => TRUE,
        ];
      }
    }
  }
}