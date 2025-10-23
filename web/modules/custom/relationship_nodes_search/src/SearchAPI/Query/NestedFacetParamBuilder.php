<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\elasticsearch_connector\SearchAPI\Query\FacetParamBuilder;
use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Drupal\relationship_nodes_search\Service\RelationSearchService;
use Drupal\relationship_nodes_search\Service\NestedAggregationService;

/**
 * Extended Facet builder with nested field support.
 */
class NestedFacetParamBuilder extends FacetParamBuilder {

    protected NestedAggregationService $nestedAggregationService;
    protected RelationSearchService $relationSearchService;

    public function __construct(
        LoggerInterface $logger, 
        NestedAggregationService $nestedAggregationService,
        RelationSearchService $relationSearchService,
    ) {
        parent::__construct($logger);
        $this->nestedAggregationService = $nestedAggregationService;
        $this->relationSearchService = $relationSearchService;
    }

    /**
     * {@inheritdoc}
     */
    public function buildFacetParams(QueryInterface $query, array $indexFields, array $facetFilters = []) {
        $aggs = [];
        $facets = $query->getOption('search_api_facets', []);
        if (empty($facets)) {
            return $aggs;
        }

        $index = $query->getIndex();


        foreach ($facets as $facet_id => $facet) {
            $field = $facet['field'];
            $parsed_names = $this->relationSearchService->validateNestedPath($index, $facet_id);
            
            if(empty($parsed_names['parent'])){
                if(!$this->checkFieldInIndex($indexFields, $field)){
                    continue;
                }
                $aggs += $this->buildTermBucketAgg($facet_id, $facet, $facetFilters);;
            } else {
                $parent = $parsed_names['parent'];
                if(!$this->checkFieldInIndex($indexFields, $parsed_names['parent'])){
                    continue;
                }
                $aggs += $this->buildNestedTermBucketAgg($facet_id, $facet, $facetFilters);
            }
        }
        dpm($aggs, 'agggggs');
        return $aggs;
    }


    protected function checkFieldInIndex(array $indexFields , string $field_name):bool{
        if (!isset($indexFields[$field_name])) {
            $this->logger->warning('Unknown facet field: %field', ['%field' => $field_name]);
            return false;
        }
        return true;
    }



    /**
     * Builds a nested bucket aggregation.
     * 
     * Creates an Elasticsearch nested aggregation for fields within nested objects.
     */
    protected function buildNestedTermBucketAgg(string $facet_id, array $facet, array $postFilter): array {
        $size = $facet['limit'] ?? self::DEFAULT_FACET_SIZE;
        if ($size === 0) {
            $size = self::UNLIMITED_FACET_SIZE;
        }


        $agg = $this->nestedAggregationService->buildNestedAggregation($facet_id, $size);

        // Apply post filters if needed
        if (!empty($postFilter)) {
            $agg = $this->applyPostFiltersToNestedAgg($facet_id, $facet, $agg, $postFilter, $parentName);
        }

        return $agg;
    }

    /**
     * Apply post filters to nested aggregation.
     *
     * Post filters allow facets to interact with each other (e.g., when multiple
     * facets are selected, each facet's counts reflect the other selections).
     */
    protected function applyPostFiltersToNestedAgg(string $facet_id, array $facet, array $agg, array $postFilter, string $nestedParentPath): array {
        $filters = [];

        foreach ($postFilter as $filter_facet_id => $filter) {
        // Skip the current facet if using OR operator
        // (OR facets should show all options regardless of selection)
        if ($filter_facet_id == $facet_id && ($facet['operator'] ?? 'and') === 'or') {
            continue;
        }
        $filters[] = $filter;
        }

        if (empty($filters)) {
        return $agg;
        }

        // Simplify if only one filter
        if (count($filters) == 1) {
        $filters = array_pop($filters);
        }

        $filtered_facet_id = sprintf('%s_filtered', $facet_id);

        // Determine boolean operator based on facet operator setting
        switch ($facet['operator'] ?? 'and') {
        case 'or':
            $facet_operator = 'should';
            break;

        case 'and':
        default:
            $facet_operator = 'must';
            break;
        }

        // Wrap the aggregation in a filter aggregation
        $agg = [
        $filtered_facet_id => [
            'filter' => [
            'bool' => [
                $facet_operator => $filters,
            ],
            ],
            'aggs' => $agg,
        ],
        ];

        return $agg;
    }

}