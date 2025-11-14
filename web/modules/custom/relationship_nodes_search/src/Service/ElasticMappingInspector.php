<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\search_api\Entity\Index;

class ElasticMappingInspector {

  protected array $mappingCache = [];


    /**
     * Returns the correct field path to use in a query (with or without ".keyword").
     *
     * @param Index $index
     * @param string $sapi_fld_nm
     * The parent field name
     * @param string $child_fld_nm
     * The nested child field name
     *
     * @return string
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

        // If the field is already type 'keyword', no suffix needed
        if (isset($mapping['type']) && $mapping['type'] === 'keyword') {
            return false;
        }

        // If it's type 'text' and has a .keyword subfield, suffix needed
        if (isset($mapping['type']) && $mapping['type'] === 'text') {
            return isset($mapping['fields']['keyword']);
        }

        return false;
    }


  
    /**
     * Retrieves the Elasticsearch mapping info for a specific field.
     *
     * @param Index $index
     * @param string $sapi_fld_nm
     * The parent field name
     * @param string $child_fld_nm
     * The nested child field name
     *
     * @return array|null
     */
    public function getFieldMapping(Index $index, string $sapi_fld_nm, string $child_fld_nm): ?array {
        $all_mappings = $this->getIndexMappings($index);

        if (!isset($all_mappings[$sapi_fld_nm])) {
            return null;
        }

        $parent_mapping = $all_mappings[$sapi_fld_nm];

        if (($parent_mapping['type'] ?? '') === 'nested' && isset($parent_mapping['properties'][$child_fld_nm])) {
            return $parent_mapping['properties'][$child_fld_nm];
        }

        if (isset($parent_mapping['properties'][$child_fld_nm])) {
            return $parent_mapping['properties'][$child_fld_nm];
        }

        return null;
    }



  /**
   * Retrieves all field mappings for a Search API index from Elasticsearch.
   *
   * @param Index $index
   *
   * @return array
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
      \Drupal::logger('relationship_nodes_search')->error('Error getting ES mapping: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }


  
  /**
   * Clears cached mappings (for a specific index or all indices).
   *
   * @param string|null $index_id
   */
  public function clearCache(?string $index_id = null): void {
    if ($index_id) {
      unset($this->mappingCache[$index_id]);
    } else {
      $this->mappingCache = [];
    }
  }
}