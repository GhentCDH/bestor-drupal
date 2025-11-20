<?php

namespace Drupal\relationship_nodes_search\Service\Query;

use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\Query\ElasticMappingInspector;


/**
 * Service for building Elasticsearch nested aggregations.
 * 
 * Handles the complexity of creating aggregations for nested fields,
 * ensuring proper field paths and structure for Elasticsearch queries.
 */
class NestedQueryStructureBuilder {

    protected ElasticMappingInspector $mappingInspector;

    /**
     * Constructs a NestedQueryStructureBuilder object.
     *
     * @param ElasticMappingInspector $mappingInspector
     *   The Elasticsearch mapping inspector service.
     */
    public function __construct(ElasticMappingInspector $mappingInspector) {
        $this->mappingInspector = $mappingInspector;
    }


    /**
     * Builds a nested aggregation for a specific field.
     *
     * Creates an Elasticsearch nested aggregation with optional post-filtering
     * for facet interaction. Structure varies based on whether a filter is provided:
     * - With filter: nested → filter → terms aggregation
     * - Without filter: nested → terms aggregation
     *
     * @param Index $index
     *   The Search API index.
     * @param string $field_id
     *   The field identifier in "parent:child" format (e.g., "parent_field:child_field").
     * @param int $size
     *   Maximum number of unique values to return (default: 10000).
     * @param array|null $filter
     *   Optional Elasticsearch filter for facet interaction/post-filters.
     *   If NULL, no filter wrapper is added.
     *
     * @return array
     *   The Elasticsearch aggregation structure with key "{field_id}_filtered".
     */
    public function buildNestedAggregation(Index $index, string $field_id, int $size = 10000, ?array $filter = null): array {
        [$parent, $child] = explode(':', $field_id, 2);
        $query_field_path = $this->getElasticQueryFieldPath($index, $parent, $child);
   
        // Base terms aggregation
        $agg_structure = [
            $field_id => [
                'terms' => [
                    'field' => $query_field_path,
                    'size' => $size,
                ],
            ],
        ];

        // Wrap in filter aggregation if needed (for facet interaction)
        if ($filter !== null) {
            $agg_structure = [
                $field_id . '_filtered' => [
                    'filter' => $filter,
                    'aggs' => $agg_structure,
                ],
            ];
        }

        // Wrap in nested aggregation
        return [
            $field_id . '_filtered' => [
                'nested' => [
                    'path' => $parent,
                ],
                'aggs' => $agg_structure,
            ],
        ];
    }

    
    /**
     * Builds a nested filter structure.
     *
     * @param string $parent_path 
     *   Parent field path.
     * @param array $subfilters 
     *   Array of subfilters (must already be combined with bool/must/should).
     *
     * @return array 
     *   Elasticsearch nested filter structure.
     */
    public function buildNestedFilter(string $parent_path, array $subfilters): array {
        return [
            'nested' => [
                'path' => $parent_path,
                'query' => $subfilters,
            ],
        ];
    }


    /**
     * Combines multiple filters with boolean conjunction.
     *
     * @param array $filters
     *   Array of filter structures to combine.
     * @param string $conjunction
     *   Conjunction type: 'AND' or 'OR'.
     *
     * @return array
     *   Combined filter structure, or empty array if no filters provided.
     */
    public function combineFilters(array $filters, string $conjunction): array {
        if (empty($filters)) {
            return [];
        }

        if (count($filters) === 1) {
            return reset($filters);
        }

        $bool_key = strtoupper($conjunction) === 'OR' ? 'should' : 'must';

        return [
            'bool' => [
                $bool_key => $filters,
            ],
        ];
    }


    /**
     * Returns the correct field path to use in a query (with or without ".keyword").
     *
     * @param Index $index
     * @param string $sapi_fld_nm
     *  The parent field name
     * @param string $child_fld_nm
     * The nested child field name
     *
     * @return string
     *  The complete field path for querying (e.g., "parent.child.keyword").
     */
    public function getElasticQueryFieldPath(Index $index, string $sapi_fld_nm, string $child_fld_nm): string {
        $path_base = $sapi_fld_nm . '.' . $child_fld_nm;
        if ($this->needsKeywordSuffix($index, $sapi_fld_nm, $child_fld_nm)) {
            return $path_base . '.keyword';
        }
        return $path_base;
    }
    

    /**
     * Check if a field needs the ".keyword" suffix for aggregations or filters.
     *
     * @param Index $index
     *   The Search API index.
     * @param string $sapi_fld_nm
     * The parent field name
     * @param string $child_fld_nm
     * The nested child field name
     *
     * @return bool
     *   TRUE if ".keyword" is needed, FALSE otherwise.
     */
    protected function needsKeywordSuffix(Index $index, string $sapi_fld_nm, string $child_fld_nm): bool {
        $mapping = $this->mappingInspector->getFieldMapping($index, $sapi_fld_nm, $child_fld_nm);
        
        if (!$mapping) {
            return false;
        }

        // Already a keyword field - no suffix needed
        if (isset($mapping['type']) && $mapping['type'] === 'keyword') {
            return false;
        }

        // Text field with keyword subfield - suffix needed
        if (isset($mapping['type']) && $mapping['type'] === 'text') {
            return isset($mapping['fields']['keyword']);
        }

        return false;
    }
}