<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\elasticsearch_connector\SearchAPI\Query\FilterBuilder;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;
use Drupal\relationship_nodes_search\Service\RelationSearchService;
use Drupal\search_api\Item\Field;

/**
 * Extended FilterBuilder with nested field support.
 */
class NestedFilterBuilder extends FilterBuilder {

    protected RelationSearchService $relationSearchService;

    public function __construct(LoggerInterface $logger, RelationSearchService $relationSearchService) {
        parent::__construct($logger);
        $this->relationSearchService = $relationSearchService;
    }


  public function buildFilters(ConditionGroupInterface $condition_group, array $index_fields) {
    dpm($condition_group, 'conditiongroup');
    dpm($index_fields, 'indexfields');
    $filters = [
      'filters' => [],
      'post_filters' => [],
      'facets_post_filters' => [],
    ];

    $backend_fields = [
      'search_api_id' => TRUE,
      'search_api_language' => TRUE,
    ];

    if (empty($condition_group->getConditions())) {
      return $filters;
    }

    $conjunction = $condition_group->getConjunction();

    foreach ($condition_group->getConditions() as $condition) {
      $filter = NULL;

      if ($condition instanceof Condition) {
        $field_id = $condition->getField();
        
        $sapi_field = $index_fields[$field_id];

        dpm($field_id, 'field id');
        dpm($sapi_field, 'saapi field') ; 
        if($sapi_field instanceof Field){
            dpm($this->relationSearchService->getNestedFields($index_fields[$field_id]), 'get nested fields...');
        }
       

        if (!$condition->getField() || !$condition->getValue() || !$condition->getOperator()) {
          continue;
        }

        // Check if nested field
        $is_nested = strpos($field_id, '.') !== FALSE;
        
        if ($is_nested) {
          // Extract parent field for validation
          $parent_field = substr($field_id, 0, strpos($field_id, '.'));
          
          if (!isset($index_fields[$parent_field]) && !isset($backend_fields[$parent_field])) {
            throw new SearchApiException(sprintf("Invalid parent field '%s' for nested field '%s'", $parent_field, $field_id));
          }
        } else {
          // Regular field validation
          if (!isset($index_fields[$field_id]) && !isset($backend_fields[$field_id])) {
            throw new SearchApiException(sprintf("Invalid field '%s' in search filter", $field_id));
          }
        }

        // Type conversion for boolean fields (alleen voor niet-nested)
        if (!$is_nested && isset($index_fields[$field_id])) {
          $field = $index_fields[$field_id];
          if ($field->getType() === 'boolean') {
            $condition->setValue((bool) $condition->getValue());
          }
        }

        // Build filter term
        $filter = $this->buildFilterTerm($condition, $index_fields);

        if (!empty($filter)) {
          // Handle facets (alleen voor niet-nested)
          if (!$is_nested && $condition_group->hasTag(sprintf('facet:%s', $field_id)) && $conjunction == "OR") {
            $filters["post_filters"][] = $filter;
            
            if (isset($filters["facets_post_filters"][$field_id])) {
              $existing_filter = $filters["facets_post_filters"][$field_id];
              $merged_values = array_merge(
                $existing_filter['terms'][$field_id] ?? [],
                (array) $condition->getValue()
              );

              $filters["facets_post_filters"][$field_id] = [
                'terms' => [
                  $field_id => array_unique($merged_values),
                ],
              ];
            }
            else {
              $filters["facets_post_filters"][$field_id] = [
                'terms' => [
                  $field_id => (array) $condition->getValue(),
                ],
              ];
            }
          } else {
            $filters["filters"][] = $filter;
          }
        }
      }
      // Nested condition groups
      elseif ($condition instanceof ConditionGroupInterface) {
        $nested_filters = $this->buildFilters($condition, $index_fields);

        foreach (["filters", "post_filters"] as $filter_type) {
          if (!empty($nested_filters[$filter_type])) {
            $filters[$filter_type][] = $nested_filters[$filter_type];
          }
        }

        foreach ($nested_filters["facets_post_filters"] as $facetId => $facetsPostFilters) {
          $filters["facets_post_filters"][$facetId] = $facetsPostFilters;
        }
      }
    }

    // Wrap with conjunction
    foreach (["filters", "post_filters"] as $filter_type) {
      if (count($filters[$filter_type]) > 1) {
        $filters[$filter_type] = $this->wrapWithConjunction($filters[$filter_type], $conjunction);
      } else {
        $filters[$filter_type] = array_pop($filters[$filter_type]);
      }
    }

    // NOW: Group nested filters and wrap them
    $filters = $this->wrapNestedFilters($filters);

    return $filters;
  }

  /**
   * Groups filters by nested path and wraps them in nested queries.
   */
  protected function wrapNestedFilters(array $filters): array {
    if (empty($filters['filters'])) {
      return $filters;
    }

    $wrapped = $this->processNestedFilters($filters['filters']);
    if ($wrapped !== $filters['filters']) {
      $filters['filters'] = $wrapped;
    }

    return $filters;
  }

  /**
   * Recursively processes filters to wrap nested field queries.
   */
  protected function processNestedFilters($filter_structure) {
    // If it's a bool query with must/should, process recursively
    if (isset($filter_structure['bool'])) {
      foreach (['must', 'should', 'must_not'] as $clause) {
        if (isset($filter_structure['bool'][$clause])) {
          $filter_structure['bool'][$clause] = $this->processNestedFilters($filter_structure['bool'][$clause]);
        }
      }
      return $filter_structure;
    }

    // If it's an array of filters
    if (is_array($filter_structure) && !$this->isSingleFilter($filter_structure)) {
      $nested_groups = [];
      $regular_filters = [];

      foreach ($filter_structure as $filter) {
        $nested_info = $this->extractNestedFieldInfo($filter);
        
        if ($nested_info) {
          $path = $nested_info['path'];
          if (!isset($nested_groups[$path])) {
            $nested_groups[$path] = [];
          }
          $nested_groups[$path][] = $filter;
        } else {
          $regular_filters[] = $filter;
        }
      }

      // Build nested queries
      foreach ($nested_groups as $path => $conditions) {
        $regular_filters[] = [
          'nested' => [
            'path' => $path,
            'query' => count($conditions) === 1 
              ? $conditions[0]
              : ['bool' => ['must' => $conditions]]
          ]
        ];
      }

      return $regular_filters;
    }

    return $filter_structure;
  }

  /**
   * Checks if structure is a single filter object.
   */
  protected function isSingleFilter(array $filter): bool {
    $filter_keys = ['term', 'terms', 'range', 'bool', 'nested', 'exists'];
    foreach ($filter_keys as $key) {
      if (isset($filter[$key])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Extracts nested field information from a filter.
   */
  protected function extractNestedFieldInfo(array $filter): ?array {
    $field = NULL;
    
    if (isset($filter['term'])) {
      $field = array_key_first($filter['term']);
    } 
    elseif (isset($filter['terms'])) {
      $field = array_key_first($filter['terms']);
    } 
    elseif (isset($filter['range'])) {
      $field = array_key_first($filter['range']);
    }
    elseif (isset($filter['bool']['must_not']['term'])) {
      $field = array_key_first($filter['bool']['must_not']['term']);
    }
    elseif (isset($filter['bool']['must_not']['terms'])) {
      $field = array_key_first($filter['bool']['must_not']['terms']);
    }

    if (!$field || strpos($field, '.') === FALSE) {
      return NULL;
    }

    $parts = explode('.', $field);
    array_pop($parts);
    
    return [
      'field' => $field,
      'path' => implode('.', $parts),
    ];
  }
}