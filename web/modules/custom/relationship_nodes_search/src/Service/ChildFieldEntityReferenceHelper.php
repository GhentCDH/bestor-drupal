<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\relationship_nodes_search\Service\NestedFieldHelper;
use Drupal\relationship_nodes_search\Service\CalculatedFieldHelper;

/**
 * Service for processing entity reference values.
 * 
 * Handles parsing, loading, and URL generation for entity reference fields,
 * converting raw IDs into labels and links.
 */
class ChildFieldEntityReferenceHelper {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected NestedFieldHelper $nestedFieldHelper; 
    protected CalculatedFieldHelper $calculatedFieldHelper; 

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager, 
        NestedFieldHelper $nestedFieldHelper,
        CalculatedFieldHelper $calculatedFieldHelper
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->nestedFieldHelper = $nestedFieldHelper;
        $this->calculatedFieldHelper = $calculatedFieldHelper;
    }


    /**
     * Processes a single field value based on display mode.
     *
     * @param mixed $value
     *   The raw field value.
     * @param string $display_mode
     *   Display mode: 'raw', 'label', or 'link'.
     *
     * @return array
     *   Array with 'value' and optional 'link_url' keys.
     */
    public function processSingleFieldValue($value, $display_mode = 'raw'){
        $result = ['value' => $value, 'link_url' => null];

        // Raw mode or non-reference fields
        if(!in_array($display_mode, ['label', 'link'], true)) {
            return $result;
        }

        return $this->processEntityReferenceValue($value, $display_mode);
    }
    

    /**
     * Parses an entity reference value string.
     *
     * Converts "entity_type/id" format into component parts.
     *
     * @param mixed $value
     *   The value to parse.
     *
     * @return array|null
     *   Array with 'entity_type' and 'id' keys, or NULL if invalid.
     */
    public function parseEntityReferenceValue($value): ?array {
         if (empty($value) || !is_string($value) || strpos($value, '/') === false) {
            return null;
        }
       
        [$type, $id] = explode('/', $value, 2);
        return ['entity_type'=> $type, 'id'=> $id];
    }


    /**
     * Extracts integer IDs from entity reference string values.
     *
     * Converts ["node/123", "node/456"] to [123, 456].
     *
     * @param array $str_id_array
     *   Array of string IDs in "entity_type/id" format.
     * @param string $entity_type
     *   The entity type to filter by.
     *
     * @return array
     *   Array of integer IDs.
     */
    public function extractIntIdsFromStringIds(array $str_id_array, string $entity_type){
        $result = [];
        $prefix = $entity_type . '/';
        foreach($str_id_array as $string_id){
            if(!is_string($string_id) || !str_starts_with($string_id, $prefix) ){
                continue;
            }
            $cleaned = substr($string_id, strlen($prefix));
            if (is_numeric($cleaned)) {
                $result[] = (int) $cleaned;
            }
        }
        return $result;
    }
    

    /**
     * Gets the target entity type for a nested entity reference field.
     *
     * @param Index $index
     *   The Search API index.
     * @param string $sapi_fld_nm
     *   The parent field name.
     * @param string $child_fld_nm
     *   The child field name.
     *
     * @return string|null
     *   The target entity type (e.g., 'node', 'taxonomy_term'), or NULL.
     */
    public function getNestedFieldTargetType(Index $index, string $sapi_fld_nm, string $child_fld_nm): ?string {
        $sapi_fld = $this->nestedFieldHelper->getIndexFieldInstance($index, $sapi_fld_nm);
        
        if (!$sapi_fld) {
            return null;
        }
        
        $property = $this->nestedFieldHelper->getNestedFieldProperty($sapi_fld);
        return $property ? $property->getDrupalFieldTargetType($child_fld_nm) : null;
    }


    /**
     * Determines if a nested field can be displayed as a link.
     *
     * A field can be linked if it's either:
     * - A calculated ID field
     * - An entity reference field
     *
     * @param Index $index
     *   The Search API index.
     * @param string $sapi_fld_nm
     *   The parent field name.
     * @param string $child_fld_nm
     *   The child field name.
     *
     * @return bool
     *   TRUE if the field can be displayed as a link.
     */
    public function nestedFieldCanLink(Index $index, string $sapi_fld_nm, string $child_fld_nm){
        if($this->calculatedFieldHelper->isCalculatedChildField($child_fld_nm)) { 
            $calc_id_fields = $this->calculatedFieldHelper->getCalculatedFieldNames(null, 'id', true);
            if(!in_array($child_fld_nm, $calc_id_fields)){
                return false;
            }
        } else {
            if(!$this->childFieldIsEntityReference($index, $sapi_fld_nm, $child_fld_nm)){
                return false;
            }
        }

        return true;
    }


     /**
     * Processes an entity reference value to get label and optionally link.
     *
     * @param string $value
     *   The entity reference value (e.g., "node/123").
     * @param string $display_mode
     *   Display mode: 'label' or 'link'.
     *
     * @return array
     *   Processed value with 'value' and optional 'link_url'.
     */
    protected function processEntityReferenceValue(string $value, string $display_mode): array {
        $result = ['value' => $value, 'link_url' => null];

        $parsed_value = $this->parseEntityReferenceValue($value);
        
        if(empty($parsed_value['entity_type']) || empty($parsed_value['id'])){
            return $result;
        }

        // Load entity label
        $label = $this->loadEntityLabel($parsed_value['entity_type'], $parsed_value['id']);
        $result['value'] = $label ?: $value;

        // Add link if requested
        if ($display_mode === 'link') {
            $result['link_url'] = $this->buildEntityUrl($parsed_value['entity_type'], $parsed_value['id']);
        }
        
        return $result;
    }

    
    /**
     * Loads an entity's label.
     *
     * @param string $entity_type
     *   The entity type (e.g., 'node', 'taxonomy_term').
     * @param mixed $entity_id
     *   The entity ID.
     *
     * @return string|null
     *   The entity label, or NULL if not found.
     */
    protected function loadEntityLabel(string $entity_type, $entity_id): ?string {
        try {
            $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
            return $entity ? $entity->label() : null;
        } catch (\Exception $e) {
            return null;
        }
    }


    /**
     * Builds a URL to an entity's canonical page.
     *
     * @param string $entity_type
     *   The entity type.
     * @param mixed $entity_id
     *   The entity ID.
     *
     * @return \Drupal\Core\Url|null
     *   The URL object, or NULL if route doesn't exist.
     */
    protected function buildEntityUrl(string $entity_type, $entity_id): ?Url {
        try {
            return Url::fromRoute(
                'entity.' . $entity_type . '.canonical',
                [$entity_type => $entity_id]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    
    /**
     * Checks if a nested field is an entity reference.
     *
     * @param Index $index
     *   The Search API index.
     * @param string $sapi_fld_nm
     *   The parent field name.
     * @param string $child_fld_nm
     *   The child field name.
     *
     * @return bool
     *   TRUE if the field is an entity reference.
     */
    protected function childFieldIsEntityReference(Index $index, string $sapi_fld_nm, string $child_fld_nm): bool {
        $sapi_fld = $this->nestedFieldHelper->getIndexFieldInstance($index, $sapi_fld_nm);

        if (!$sapi_fld) {
            return false;
        }
        
        $property = $this->nestedFieldHelper->getNestedFieldProperty($sapi_fld);
        return $property? $property->drupalFieldIsReference($child_fld_nm) : false;
    }
}