<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\elasticsearch_connector\SearchAPI\Query\FilterBuilder;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;
use Drupal\relationship_nodes_search\Service\RelationSearchService;
use Drupal\search_api\Item\Field;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedParentFieldConditionGroup;

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
        if (!($condition_group instanceof NestedParentFieldConditionGroup)) {
            return parent::buildFilters($condition_group, $index_fields);
        }
        return $this->buildNestedFieldConditionFilters($condition_group, $index_fields);
    }



    protected function buildNestedFieldConditionFilters(NestedParentFieldConditionGroup $condition_group, array $index_fields): array {
        $parent_field_name = $condition_group->getParentFieldName();
        dpm($parent_field_name, 'pfn');
        if (empty($parent_field_name)) {
            return [
                'filters' => [],
                'post_filters' => [],
                'facets_post_filters' => [],
            ];
        }

        $nested_index_fields = $this->relationSearchService->getNestedFields($index_fields[$parent_field_name]);
        $nested_sub_filters = parent::buildFilters($condition_group, $nested_index_fields);
        dpm($nested_sub_filters, 'nested sub filter results');

        if (empty($nested_sub_filters['filters'])) {
            return $nested_sub_filters;
        }

        // Wrap in nested query
        $nested_sub_filters['filters'] = [
            'nested' => [
                'path' => $parent_field_name . '.',
                'query' => $nested_sub_filters['filters'],
            ]
        ];
        return $nested_sub_filters;
    }
}