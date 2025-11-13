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
            $field_id . '_filtered' => [
                'nested' => [
                    'path' => $parent,
                ],
                'aggs' => [
                    $field_id => [
                        'terms' => [
                            'field' => $full_field_path,
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
        $nested_key = $agg_id . '_filtered';
        return $response['aggregations'][$nested_key][$agg_id]['buckets'] ?? [];
    }


    /**
     * Get unique values from buckets.
     */
    public function getUniqueValues(array $buckets): array {
        return array_column($buckets, 'key');
    }

    public function cleanFacetResults(array $facetData): array {

            foreach ($facetData as $key => $result) {
            
            if (strlen($result) >= 2 && 
                str_starts_with($result, '"') && 
                str_ends_with($result, '"')) {
                $facetData [$key] = substr($result, 1, -1);
            }
            
        }
        return $facetData;
    }
}