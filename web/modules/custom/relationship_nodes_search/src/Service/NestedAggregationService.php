<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\RelationSearchService;


class NestedAggregationService {

    protected RelationSearchService $relationSearchService;


    public function __construct(RelationSearchService $relationSearchService) {
        $this->relationSearchService = $relationSearchService;
    }


    /**
     * Build aggregation for a nested field to get unique values.
     */
    public function buildNestedAggregation(string $field_id, int $size = 10000): array {
        [$parent, $child] = explode(':', $field_id, 2);
        $full_field_path = $this->relationSearchService->colonsToDots($field_id);
        return [
            $field_id => [
                'nested' => [
                    'path' => $parent,
                ],
                'aggs' => [
                    'facet_values' => [
                        'terms' => [
                            'field' => $full_field_path . '.keyword',
                            'size' => $size,
                        ],
                    ],
                ],
            ],
        ];
    }


    /**
     * Extract buckets from aggregation response.
     */
    public function extractBuckets(array $response, string $agg_id): array {
        return $response['aggregations'][$agg_id]['facet_values']['buckets'] ?? [];
    }


    /**
     * Get unique values from buckets.
     */
    public function getUniqueValues(array $buckets): array {
        return array_column($buckets, 'key');
    }
}