<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\search_api\Entity\Index;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;


/**
 * Service for inspecting and working with Elasticsearch field mappings.
 * 
 * Provides utilities to determine correct field paths for queries and aggregations,
 * handling the complexity of Elasticsearch's text/keyword field patterns.
 */
class ElasticMappingInspector {

    protected array $mappingCache = [];
    protected LoggerChannelFactoryInterface $loggerFactory;

    
    public function __construct(LoggerChannelFactoryInterface $loggerFactory) {
        $this->loggerFactory = $loggerFactory;
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
    public function needsKeywordSuffix(Index $index, string $sapi_fld_nm, string $child_fld_nm): bool {
        $mapping = $this->getFieldMapping($index, $sapi_fld_nm, $child_fld_nm);
        
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

  
    /**
     * Retrieves the Elasticsearch mapping info for a specific field.
     *
     * @param Index $index
     *   The Search API index.
     * @param string $sapi_fld_nm
     *   The parent field name.
     * @param string $child_fld_nm
     *   The nested child field name.
     *
     * @return array|null
     *   The field mapping array, or NULL if not found.
     */
    public function getFieldMapping(Index $index, string $sapi_fld_nm, string $child_fld_nm): ?array {
        $all_mappings = $this->getIndexMappings($index);

        if (!isset($all_mappings[$sapi_fld_nm])) {
            return null;
        }

        $parent_mapping = $all_mappings[$sapi_fld_nm];

        // Check nested field properties
        if (($parent_mapping['type'] ?? '') === 'nested' && isset($parent_mapping['properties'][$child_fld_nm])) {
            return $parent_mapping['properties'][$child_fld_nm];
        }

        // Fallback to direct properties
        if (isset($parent_mapping['properties'][$child_fld_nm])) {
            return $parent_mapping['properties'][$child_fld_nm];
        }

        return null;
    }


    /**
     * Retrieves all field mappings for a Search API index from Elasticsearch.
     * Results are cached to avoid repeated API calls.
     *
     * @param Index $index
     *   The Search API index.
     *
     * @return array
     *   Array of field mappings keyed by field name.
     */
    public function getIndexMappings(Index $index): array {
        $index_id = $index->id();
        
        if (isset($this->mappingCache[$index_id])) {
            return $this->mappingCache[$index_id];
        }

        try {
            $server = $index->getServerInstance();
            $backend = $server->getBackend();

            $client = $backend->getClient();
        
            
            $response = $client->indices()->getMapping(['index' => $index_id]);
            
            // Extract properties from response
            $properties = $response[$index_id]['mappings']['properties'] ?? [];
            
            $this->mappingCache[$index_id] = $properties;
            
            return $properties;
        } catch (\Exception $e) {
            $this->loggerFactory->get('relationship_nodes_search')->error(
                'Failed to retrieve Elasticsearch mappings for index @index: @message',
                ['@index' => $index_id, '@message' => $e->getMessage()]
            );
        return [];
        }
    }


  /**
   * Clears cached mappings (for a specific index or all indices).
   *
   * @param string|null $index_id
   *    Optional index ID to clear. If NULL, clears all cached mappings.
   */
  public function clearCache(?string $index_id = null): void {
    if ($index_id) {
      unset($this->mappingCache[$index_id]);
    } else {
      $this->mappingCache = [];
    }
  }
}