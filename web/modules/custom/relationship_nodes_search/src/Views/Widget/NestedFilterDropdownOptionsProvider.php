<?php

namespace Drupal\relationship_nodes_search\Views\Widget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\relationship_nodes_search\FieldHelper\NestedIndexFieldHelper;
use Drupal\relationship_nodes\RelationField\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\Views\Parser\NestedFieldResultViewsParser;
use Drupal\relationship_nodes_search\QueryHelper\NestedFacetResultParser;
use Drupal\Core\Language\LanguageManagerInterface; 
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\ConditionGroup;

/**
 * Provides dropdown options for nested filter fields.
 * 
 * Handles facet queries, entity loading, caching, and conversion
 * to form-compatible option arrays.
 */
class NestedFilterDropdownOptionsProvider {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected CacheBackendInterface $cache;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected AccountProxyInterface $currentUser;
  protected LanguageManagerInterface $languageManager;
  protected NestedIndexFieldHelper $nestedFieldHelper;
  protected CalculatedFieldHelper $calculatedFieldHelper;
  protected NestedFieldResultViewsParser $resultParser;
  protected NestedFacetResultParser $facetResultParser;


  /**
   * Constructs a NestedFilterDropdownOptionsProvider object.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param CacheBackendInterface $cache
   *   The cache backend service.
   * @param LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory service.
   * @param AccountProxyInterface $currentUser
   *   The current user service.
   * @param LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param NestedIndexFieldHelper $nestedFieldHelper
   *   The nested field helper service.
   * @param CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper service.
   * @param NestedFieldResultViewsParser $resultParser
   *   The child field entity reference helper service.
   * @param NestedFacetResultParser $facetResultParser
   *   The facet result parser service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    CacheBackendInterface $cache,
    LoggerChannelFactoryInterface $loggerFactory,
    AccountProxyInterface $currentUser,
    LanguageManagerInterface $languageManager,
    NestedIndexFieldHelper $nestedFieldHelper,
    CalculatedFieldHelper $calculatedFieldHelper,
    NestedFieldResultViewsParser $resultParser,
    NestedFacetResultParser $facetResultParser
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->cache = $cache;
    $this->loggerFactory = $loggerFactory;
    $this->currentUser = $currentUser;
    $this->languageManager = $languageManager;
    $this->nestedFieldHelper = $nestedFieldHelper;
    $this->calculatedFieldHelper = $calculatedFieldHelper;
    $this->resultParser = $resultParser;
    $this->facetResultParser = $facetResultParser;
  }


  /**
   * Get dropdown options for a field.
   *
   * Retrieves unique values from Elasticsearch index with caching.
   * Results are cached per user and language to ensure proper access
   * control and multilingual support.
   *
   * @param Index $index
   *   The search index.
   * @param string $sapi_fld_nm
   *   Parent field name (e.g., 'relationship_info__parent').
   * @param string $child_fld_nm
   *   Child field name (e.g., 'person', 'calculated_related_id').
   * @param string $display_mode
   *   Display mode: 'raw' (show IDs) or 'label' (show entity labels).
   * @param ?SearchApiQuery $view_query
   *   The view query.
   *
   * @return array
   *   Options array suitable for form select element (value => label).
   *   Returns empty array on error.
   */
  public function getDropdownOptions(Index $index, string $sapi_fld_nm, string $child_fld_nm, string $display_mode = 'label', ?SearchApiQuery $view_query = NULL): array {
    try {
      $options = $this->fetchOptionsFromIndex($index, $sapi_fld_nm, $child_fld_nm, $display_mode, $view_query);
      // Cache tags include bundle for granular invalidation
      $cache_tags = [
        'relationship_filter_options',
        'relationship_filter_options:' . $sapi_fld_nm,
      ];

      //$this->cache->set($cache_key, $options, Cache::PERMANENT, $cache_tags);
      return $options;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('relationship_nodes_search')->error(
        'Failed to fetch options for @index:@field: @message',
        ['@index' => $index->id(), '@field' => $child_fld_nm, '@message' => $e->getMessage()]
      );
      return [];
    }
  }


  /**
   * Fetch options from search index using facets.
   *
   * @param \Drupal\search_api\Entity\Index $index
   *   The search index.
   * @param string $sapi_fld_nm
   *   Parent field name.
   * @param string $child_fld_nm
   *   Child field name.
   * @param string $display_mode
   *   Display mode.
   * @param ?SearchApiQuery $view_query
   *   The view query.
   *
   * @return array
   *   Options array.
   */
  protected function fetchOptionsFromIndex(Index $index, string $sapi_fld_nm, string $child_fld_nm, string $display_mode, ?SearchApiQuery $view_query = NULL): array {
    $field_id = $sapi_fld_nm . ':' . $child_fld_nm;
    $full_field_path = $this->nestedFieldHelper->colonsToDots($field_id);

    try {
      if ($view_query && method_exists($view_query, 'getSearchApiQuery')) {
        $sapi_query = $view_query->getSearchApiQuery();
        if ($sapi_query) {
          $query = clone $sapi_query;
        } else {
          $query = $index->query();
        }
      } else {
        $query = $index->query();
      }
      
      // Configureer voor facets
      $query->range(0, 0);
      $query->setOption('search_api_facets', [
        $field_id => [
          'field' => $full_field_path,
          'limit' => 0,
          'operator' => 'or',
          'min_count' => 1,
          'missing' => FALSE,
        ],
      ]);

      // Execute de facet query
      $results = $query->execute();
      $facet_values = $this->facetResultParser->extractTrimmedFacetValues($results, $field_id);
      
      if (empty($facet_values)) {
        return [];
      }

      // Convert to form options
      return $this->convertToFormOptions($facet_values, $index, $sapi_fld_nm, $child_fld_nm, $display_mode);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('relationship_nodes_search')->error(
        'Failed to fetch dropdown options for @field: @message',
        ['@field' => $field_id, '@message' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Convert facet values to form options.
   *
   * @param array $facet_values
   *   Raw facet values (e.g., ['node/123', 'node/456']).
   * @param Index $index
   *   The search index.
   * @param string $sapi_fld_nm
   *   Parent field name.
   * @param string $child_fld_nm
   *   Child field name.
   * @param string $display_mode
   *   Display mode: 'raw' or 'label'.
   *
   * @return array
   *   Form options (value => label).
   */
  protected function convertToFormOptions(array $facet_values, Index $index, string $sapi_fld_nm, string $child_fld_nm, string $display_mode): array {
    if (empty($facet_values)) {
      return [];
    }

    // Raw mode: ID is both value and label
    if ($display_mode === 'raw') {
      return array_combine($facet_values, $facet_values);
    }

    // Determine target entity type
    $target_type = $this->calculatedFieldHelper->isCalculatedChildField($child_fld_nm)
      ? $this->calculatedFieldHelper->getCalculatedFieldTargetType($child_fld_nm)
      : $this->nestedFieldHelper->getChildFieldTargetType($index, $sapi_fld_nm, $child_fld_nm);

    // Validate entity type
    if (!$target_type || !in_array($target_type, ['node', 'taxonomy_term'])) {
      return array_combine($facet_values, $facet_values);
    }

    // Load entities and build options
    return $this->buildEntityOptions($facet_values, $target_type);
  }


  /**
   * Build form options by loading entities with access control.
   *
   * @param array $entity_ids
   *   Array of entity ID strings (e.g., ['node/123', 'node/456']).
   * @param string $target_type
   *   Entity type (e.g., 'node', 'taxonomy_term').
   *
   * @return array
   *   Form options array (value => label).
   */
  protected function buildEntityOptions(array $entity_ids, string $target_type): array {
    try {
      // Extract numeric IDs
      $numeric_ids = $this->resultParser->extractIntIdsFromStrings($entity_ids, $target_type);
      
      if (empty($numeric_ids)) {
        $this->loggerFactory->get('relationship_nodes_search')->warning(
          'No valid numeric IDs found in entity reference values for type @type',
          ['@type' => $target_type]
        );
        return [];
      }
      
      // Load entities
      $storage = $this->entityTypeManager->getStorage($target_type);
      $entities = $storage->loadMultiple($numeric_ids);
      
      if (empty($entities)) {
        $this->loggerFactory->get('relationship_nodes_search')->warning(
          'Failed to load any entities of type @type for @count IDs',
          ['@type' => $target_type, '@count' => count($numeric_ids)]
        );
        return [];
      }
      
      // Build options with labels - only for entities user can view
      $options = [];
      $current_language = $this->languageManager->getCurrentLanguage()->getId();

      foreach ($entities as $id => $entity) {
        // Check access
        if (!$entity->access('view', $this->currentUser)) {
          continue;
        }

        if ($entity->hasTranslation($current_language)) {
          $translated_entity = $entity->getTranslation($current_language);
        } else {
          $translated_entity = $entity;
        }
        
        $options[$target_type . '/' . $id] = $translated_entity->label();
      }


      // Log if some entities failed to load or were filtered by access control
      if (count($entities) < count($numeric_ids)) {
        $loaded_ids = array_keys($entities);
        $missing_ids = array_diff($numeric_ids, $loaded_ids);
        $this->loggerFactory->get('relationship_nodes_search')->notice(
          'Could not load @count entities of type @type: @ids',
          [
            '@count' => count($missing_ids),
            '@type' => $target_type,
            '@ids' => implode(', ', array_slice($missing_ids, 0, 10)) // Only first 10 to avoid huge logs
          ]
        );
      }
      
      return $options;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('relationship_nodes_search')->error(
        'Failed to load entities for options: @message',
        ['@message' => $e->getMessage()]
      );
      
      return [];
    }
  }


  /**
   * Generate cache key for dropdown options.
   * Includes language to ensure translated labels are cached separately.
   *
   * @param Index $index
   *   The search index.
   * @param string $sapi_fld_nm
   *   Parent field name.
   * @param string $child_fld_nm
   *   Child field name.
   * @param string $display_mode
   *   Display mode.
   *
   * @return string
   *   Cache key.
   */
  protected function getCacheKey(Index $index, string $sapi_fld_nm, string $child_fld_nm, string $display_mode): string {
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    
    $parts = [
        'relationship_filter_options',
        $index->id(),
        str_replace([':', '.', '/'], '_', $sapi_fld_nm),
        str_replace([':', '.', '/'], '_', $child_fld_nm),
        $display_mode,
        $this->currentUser->id(),
        $current_language,
    ];

    return implode(':', $parts);
  }


  /**
   * Get dropdown options with view context for filtering.
   *
   * @param Index $index
   *   The search index.
   * @param string $sapi_fld_nm
   *   Parent field name.
   * @param string $child_fld_nm
   *   Child field name.
   * @param string $display_mode
   *   Display mode: 'raw' or 'label'.
   * @param SearchApiQuery|null $view_query
   *   The view query to extract non-exposed filters from.
   *
   * @return array
   *   Options array suitable for form select element.
   */
  public function getDropdownOptionsWithViewContext(
    Index $index,
    string $sapi_fld_nm,
    string $child_fld_nm,
    string $display_mode = 'raw',
    ?SearchApiQuery $view_query = NULL
  ): array {
    try {
      // Create fresh query
      $query = $index->query();

      // Extract and apply non-exposed conditions
      if ($view_query) {       
        $non_exposed_fields = [];
        foreach ($view_query->view->filter as $filter_id => $filter) {
          if (!$filter->isExposed() && !empty($filter->value)) {
            $non_exposed_fields[] = $filter->realField;
          }
        }

        if (!empty($non_exposed_fields)) {
          $source_conditions = $view_query->getSearchApiQuery()->getConditionGroup();
          $target_conditions = $query->getConditionGroup();
          
          $this->copyNonExposedConditionsRecursive(
            $source_conditions,
            $target_conditions,
            $non_exposed_fields
          );
        }
      }

      // Query only needs facets, no results
      $query->range(0, 0);

      // Add facet configuration
      $field_key = $sapi_fld_nm . ':' . $child_fld_nm;  // <- FIX
      $full_field_path = $this->nestedFieldHelper->colonsToDots($field_key);
      
      $facets = [
        $field_key => [
          'field' => $full_field_path,
          'limit' => 0,
          'operator' => 'or',
          'min_count' => 1,
          'missing' => FALSE,
        ],
      ];
      
      $query->setOption('search_api_facets', $facets);

      // Execute query
      $results = $query->execute();
      
      $raw_values = $this->facetResultParser->extractTrimmedFacetValues($results, $field_key);
      
      // Reuse existing conversion logic
      return $this->convertToFormOptions($raw_values, $index, $sapi_fld_nm, $child_fld_nm, $display_mode);  // <- FIX

    } catch (\Exception $e) {
      $this->loggerFactory->get('relationship_nodes_search')->error(
        'Failed to fetch facet options: @message',
        ['@message' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Recursively copy non-exposed conditions from source to target group.
   *
   * @param ConditionGroupInterface $source
   *   Source condition group.
   * @param ConditionGroupInterface $target
   *   Target condition group.
   * @param array $non_exposed_fields
   *   Array of field names that belong to non-exposed filters.
   */
  protected function copyNonExposedConditionsRecursive(ConditionGroupInterface $source, ConditionGroupInterface $target, array $non_exposed_fields): void {
    $conditions = $source->getConditions();
    
    foreach ($conditions as $condition) {
      // Check if this is a nested ConditionGroup
      if ($condition instanceof ConditionGroupInterface) {
        // Create new group with same conjunction (AND/OR) and tags
        $new_group = new ConditionGroup(
          $condition->getConjunction(),
          $condition->getTags()
        );
        
        // Recurse into nested group
        $this->copyNonExposedConditionsRecursive($condition, $new_group, $non_exposed_fields);
        
        // Only add group if it has conditions
        if (!$new_group->isEmpty()) {
          $target->addConditionGroup($new_group);
        }
      } else {
        // Regular condition - check if it belongs to non-exposed filter
        $field = $condition->getField();
        
        if (in_array($field, $non_exposed_fields, true)) {
          try {
            $target->addCondition(
              $field,
              $condition->getValue(),
              $condition->getOperator()
            );
          } catch (\Exception $e) {
            // Skip conditions that can't be copied
            $this->loggerFactory->get('relationship_nodes_search')->debug(
              'Skipped condition for field @field: @message',
              ['@field' => $field, '@message' => $e->getMessage()]
            );
          }
        }
      }
    }
  }
}