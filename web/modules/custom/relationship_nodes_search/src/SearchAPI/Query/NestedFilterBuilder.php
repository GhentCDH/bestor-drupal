<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\elasticsearch_connector\SearchAPI\Query\FilterBuilder;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;
use Drupal\search_api\Item\Field;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedParentFieldConditionGroup;


class NestedFilterBuilder extends FilterBuilder {

    
    public function __construct(LoggerInterface $logger) {
        parent::__construct($logger);
    }


    /**
     * {@inheritdoc}
     */
    public function buildFilters(ConditionGroupInterface $condition_group, array $index_fields) {
        if (!($condition_group instanceof NestedParentFieldConditionGroup)) {
            return parent::buildFilters($condition_group, $index_fields);
        }
        return $this->buildNestedFieldConditionFilters($condition_group, $index_fields);
    }


    /**
     * Extended FilterBuilder with nested field support.
     */
    protected function buildNestedFieldConditionFilters(NestedParentFieldConditionGroup $condition_group, array $index_fields): array {
        $parent = $condition_group->getParentFieldName();
        if (empty($parent)) {
            return [];
        }

        $subfilters = [];
        
        foreach ($condition_group->getConditions() as $condition) {
            if ($condition instanceof NestedChildFieldCondition) {
                $subfilters[] = $this->buildFilterTerm($condition, $index_fields);
            }
        }

        if (empty($subfilters)) {
            return [];
        }

        $combined_subfilters = $this->wrapWithConjunction($subfilters, $condition_group->getConjunction());

        return [
            'filters' => [
                'nested' => [
                    'path' => $parent,
                    'query' => $combined_subfilters
                ]
            ]
        ];
    }
}