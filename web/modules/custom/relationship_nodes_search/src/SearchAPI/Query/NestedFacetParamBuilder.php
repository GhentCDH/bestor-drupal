<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\elasticsearch_connector\SearchAPI\Query\FacetParamBuilder;
use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Drupal\relationship_nodes_search\Service\Query\NestedQueryStructureBuilder;
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\Field\NestedFieldHelper;

/**
 * Extended Facet builder with nested field support.
 */
class NestedFacetParamBuilder extends FacetParamBuilder {

    protected NestedQueryStructureBuilder $queryBuilder;
    protected NestedFieldHelper $nestedFieldHelper;

    public function __construct(
        LoggerInterface $logger, 
        NestedQueryStructureBuilder $queryBuilder,
        NestedFieldHelper $nestedFieldHelper,
    ) {
        parent::__construct($logger);
        $this->queryBuilder = $queryBuilder;
        $this->nestedFieldHelper = $nestedFieldHelper;
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
            $parsed_names = $this->nestedFieldHelper->validateNestedPath($index, $facet_id);
            $check_field = $parsed_names['parent'] ?? $facet['field'];
            if (!$this->checkFieldInIndex($indexFields, $check_field)) {
                continue;
            }

            if(empty($parsed_names['parent'])){
                $aggs += $this->buildTermBucketAgg($facet_id, $facet, $facetFilters);;
            } else {
                $aggs += $this->buildNestedTermBucketAgg($index, $facet_id, $facet, $facetFilters);
            }
        }
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
    protected function buildNestedTermBucketAgg(Index $index, string $facet_id, array $facet, array $postFilters): array {
        return $this->queryBuilder->buildNestedAggregation(
            $index, 
            $facet_id, 
            $this->getFacetSize($facet), 
            $this->buildPostFilter($facet_id, $facet, $postFilters)
        );
    }

    /**
     * Apply post filters to nested aggregation.
     *
     * Post filters allow facets to interact with each other (e.g., when multiple
     * facets are selected, each facet's counts reflect the other selections).
     */
    protected function buildPostFilter(string $facet_id, array $facet, array $postFilters): ?array {
        $filters = [];

        foreach ($postFilters as $filter_facet_id => $filter) {
            // Skip the current facet if using OR operator
            // (OR facets should show all options regardless of selection)
            if ($filter_facet_id == $facet_id && ($facet['operator'] ?? 'and') === 'or') {
                continue;
            }
            $filters[] = $filter;
        }

        if (empty($filters)) {
            return null;
        }

        $conjunction = ($facet['operator'] ?? 'and') === 'or' ? 'OR' : 'AND';
        return $this->queryBuilder->combineFilters($filters, $conjunction);
    }


    protected function getFacetSize(array $facet): int {
        $size = $facet['limit'] ?? self::DEFAULT_FACET_SIZE;
        return $size === 0 ? self::UNLIMITED_FACET_SIZE : $size;
    }

}