<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\ElasticMappingInspector;


class NestedAggregationService {

    protected ElasticMappingInspector $mappingInspector;


    public function __construct(ElasticMappingInspector $mappingInspector) {
        $this->mappingInspector = $mappingInspector;
    }


    /**
     * Build aggregation for a nested field to get unique values.
     */
    public function buildNestedAggregation(Index $index, string $field_id, int $size = 10000): array {
        [$parent, $child] = explode(':', $field_id, 2);
        $query_field_path = $this->mappingInspector->getElasticQueryFieldPath($index, $parent, $child);
   
        return [
            $field_id . '_filtered' => [
                'nested' => [
                    'path' => $parent,
                ],
                'aggs' => [
                    $field_id => [
                        'terms' => [
                            'field' => $query_field_path,
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