<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;


class NestedFilterExposedWidgetHelper {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;
    protected LoggerChannelFactoryInterface $loggerFactory;
    protected CacheBackendInterface $cache;
    protected RelationSearchService $relationSearchService;
    protected NestedAggregationService $nestedAggregationService;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        LoggerChannelFactoryInterface $loggerFactory,
        CacheBackendInterface $cache,
        RelationSearchService $relationSearchService,
        NestedAggregationService $nestedAggregationService
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->loggerFactory = $loggerFactory;
        $this->cache = $cache;
        $this->relationSearchService = $relationSearchService;
        $this->nestedAggregationService = $nestedAggregationService;
    }

    /**
     * Get enabled fields from configuration.
     */
    public function getEnabledFields(array $filter_field_settings): array {
        return array_filter($filter_field_settings, function($config) {
            return !empty($config['enabled']);
        });
    }

    /**
     * Get enabled fields sorted by weight.
     */
    public function getEnabledAndSortedFields(array $filter_field_settings): array {
        $enabled = $this->getEnabledFields($filter_field_settings);

        uasort($enabled, function($a, $b) {
            return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
        });

        return $enabled;
    }

    public function getDropdownOptions(Index $index, string $parent_field, string $child_field, string $display_mode = 'label'): array {
        $cache_key = $this->getCacheKey($index, $parent_field, $child_field, $display_mode);
        if (1 == 2 && $cached = $this->cache->get($cache_key)) {
            $this->loggerFactory->get('relationship_nodes_search')->debug(
                'Cache hit for dropdown options [@field] with @count items',
                ['@field' => $child_field, '@count' => count($cached->data ?? [])]
            );
            return $cached->data;
        }
        try {
            $options = $this->fetchOptionsFromIndex($index, $parent_field, $child_field, $display_mode);
            $this->cache->set($cache_key, $options, Cache::PERMANENT, ['relationship_filter_options']);
           
            return $options;
        }
        catch (\Exception $e) {
            $this->loggerFactory->get('relationship_nodes_search')->error(
                'Failed to fetch options for @index:@field: @message',
                ['@index' => $index->id(), '@field' => $child_field, '@message' => $e->getMessage()]
            );
            return [];
        }
    }

    
    protected function getCacheKey(Index $index, string $parent_field, string $child_field, string $display_mode = 'label', array $extra_context = []): string {
        $parts = [
            'relationship_filter',
            $index->id(),
            str_replace([':', '.', '/'], '_', $parent_field),
            str_replace([':', '.', '/'], '_', $child_field),
            $display_mode,
        ];

        if (!empty($extra_context)) {
            ksort($extra_context);
            $parts[] = md5(json_encode($extra_context));
        }

        return implode(':', $parts);
    }

    protected function fetchOptionsFromIndex(Index $index, string $parent_field, string $child_field, string $display_mode): array {
        $field_id = $parent_field . ':' . $child_field;
        $full_field_path = $this->relationSearchService->colonsToDots($field_id);

        $query = $index->query();
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

        $results = $query->execute();
        $facets = $results->getExtraData('search_api_facets', []);
        if (empty($facets[$field_id])) {
            return [];
        }

        $facet_values = array_column($facets[$field_id], 'filter');
        return $this->convertFacetResultsToOptions($facet_values, $index, $parent_field, $child_field, $display_mode);
    }

    public function convertFacetResultsToOptions(array $results, Index $index, string $parent_field, string $child_field, string $display_mode = 'raw'): array {
        if (empty($results)) {
            return [];
        }
        $results = $this->nestedAggregationService->cleanFacetResults($results);
        
        if ($display_mode === 'raw') {
            return array_combine($results, $results);
        }

        $target_type = $this->relationSearchService->isCalculatedChildField($child_field) 
            ? $this->relationSearchService->getCalculatedFieldTargetType($child_field)
            : $this->relationSearchService->getNestedFieldTargetType($index, $parent_field, $child_field);
        
        if(!in_array($target_type, ['node', 'taxonomy_term'])){
            return [];
        }

        try {
            $int_ids = $this->relationSearchService->extractIntIdsFromStringIds($results, $target_type);
            $storage = $this->entityTypeManager->getStorage($target_type);
            $entities = $storage->loadMultiple($int_ids);
            $options = [];
            foreach ($entities as $id => $entity) {
                $options[$target_type .'/' . $id] = $entity->label();
            }
            return $options;
        } catch (\Exception $e) {
             $this->loggerFactory->get('relationship_nodes_search')->error('Failed to load entities: @message', ['@message' => $e->getMessage()]);
            return array_combine($results, $results);
        }
    }
}