<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\ElasticMappingInspector;


/**
 * Service for building Elasticsearch nested aggregations.
 * 
 * Handles the complexity of creating aggregations for nested fields,
 * ensuring proper field paths and structure for Elasticsearch queries.
 */
class NestedAggregationService {

    protected ElasticMappingInspector $mappingInspector;


    public function __construct(ElasticMappingInspector $mappingInspector) {
        $this->mappingInspector = $mappingInspector;
    }


    /**
     * Builds a nested aggregation for a specific field.
     *
     * Creates the Elasticsearch aggregation structure needed to get unique
     * values from a nested field, accounting for proper field paths and
     * keyword suffixes.
     *
     * @param Index $index
     *   The Search API index.
     * @param string $field_id
     *   The field identifier in "parent:child" format.
     * @param int $size
     *   Maximum number of unique values to return.
     *
     * @return array
     *   The Elasticsearch aggregation structure.
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
     * Extracts bucket data from an Elasticsearch aggregation response.
     *
     * @param array $response
     *   The full Elasticsearch response.
     * @param string $agg_id
     *   The aggregation identifier.
     *
     * @return array
     *   Array of bucket data.
     */
    public function extractBuckets(array $response, string $agg_id): array {
        $nested_key = $agg_id . '_filtered';
        return $response['aggregations'][$nested_key][$agg_id]['buckets'] ?? [];
    }


    /**
     * Extracts unique values from aggregation buckets.
     *
     * @param array $buckets
     *   Array of Elasticsearch bucket objects.
     *
     * @return array
     *   Array of unique values (keys).
    */
    public function getUniqueValues(array $buckets): array {
        return array_column($buckets, 'key');
    }

    /**
     * Cleans facet results by removing surrounding quotes.
     *
     * Elasticsearch sometimes returns string values wrapped in quotes.
     * This method strips those quotes for cleaner display.
     *
     * @param array $facetData
     *   Array of facet result strings.
     *
     * @return array
     *   Cleaned facet results.
     */
    public function parseNestedFacetResults(array $facetData): array {
        foreach ($facetData as $key => $result) {  
            if (strlen($result) >= 2 && str_starts_with($result, '"') && str_ends_with($result, '"')) {
                $facetData [$key] = substr($result, 1, -1);
            }
        }
        return $facetData;
    }
}