<?php

namespace Drupal\relationship_nodes_search\Service\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\relationship_nodes_search\Service\Field\NestedFieldHelper;
use Drupal\relationship_nodes_search\Service\Field\CalculatedFieldHelper;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Service for processing entity reference values with batch loading support.
 */
class ChildFieldEntityReferenceHelper {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected LanguageManagerInterface $languageManager;
    protected LoggerChannelFactoryInterface $loggerFactory;
    protected NestedFieldHelper $nestedFieldHelper; 
    protected CalculatedFieldHelper $calculatedFieldHelper; 

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        LanguageManagerInterface $languageManager,
        LoggerChannelFactoryInterface $loggerFactory, 
        NestedFieldHelper $nestedFieldHelper,
        CalculatedFieldHelper $calculatedFieldHelper
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->languageManager = $languageManager;
        $this->loggerFactory = $loggerFactory;
        $this->nestedFieldHelper = $nestedFieldHelper;
        $this->calculatedFieldHelper = $calculatedFieldHelper;
    }


    /**
     * Batch load entities for multiple relationship items.
     * Collects all entity references and loads them in one query per type.
     * Loads entities in correct language translation when available.
     *
     * @param array $nested_data
     *   Array of relationship items from search results.
     * @param array $field_settings
     *   Field display settings (which fields are enabled, display modes, etc).
     * @param Index $index
     *   The Search API index.
     * @param string $sapi_fld_nm
     *   Parent field name.
     *
     * @return array
     *   Array keyed by "entity_type/id" with loaded entities as values.
     */
    public function batchLoadEntities(
        array $nested_data, 
        array $field_settings, 
        Index $index, 
        string $sapi_fld_nm
    ): array {
        $entities_to_load = [];
        
        // Collect all entity reference IDs that need loading
        foreach ($nested_data as $item) {
            foreach ($field_settings as $child_fld_nm => $settings) {
                if (empty($settings['enabled']) || !isset($item[$child_fld_nm])) {
                    continue;
                }
                
                $display_mode = $settings['display_mode'] ?? 'raw';
                if (!in_array($display_mode, ['label', 'link'])) {
                    continue; // No entity loading needed for raw mode
                }
                
                // Check if this field needs entity loading
                if (!$this->nestedFieldCanLink($index, $sapi_fld_nm, $child_fld_nm)) {
                    continue;
                }
                
                // Collect entity IDs from field values
                $values = is_array($item[$child_fld_nm]) ? $item[$child_fld_nm] : [$item[$child_fld_nm]];
                foreach ($values as $value) {
                    $parsed = $this->parseEntityReferenceValue($value);
                    if ($parsed && !empty($parsed['entity_type']) && !empty($parsed['id'])) {
                        $entity_type = $parsed['entity_type'];
                        $entity_id = $parsed['id'];
                        
                        if (!isset($entities_to_load[$entity_type])) {
                            $entities_to_load[$entity_type] = [];
                        }
                        $entities_to_load[$entity_type][] = $entity_id;
                    }
                }
            }
        }
        
        // Load entities in batches per type
        return $this->loadEntitiesByType($entities_to_load);
    }


    /**
     * Load entities grouped by type.
     *
     * @param array $entities_to_load
     *   Array keyed by entity type with arrays of IDs as values.
     *
     * @return array
     *   Entity cache keyed by "entity_type/id".
     */
    protected function loadEntitiesByType(array $entities_to_load): array {
        $preloaded_entities = [];
        $current_language = $this->languageManager->getCurrentLanguage()->getId();
        
        foreach ($entities_to_load as $entity_type => $ids) {
            $unique_ids = array_unique($ids);
            
            try {
                $storage = $this->entityTypeManager->getStorage($entity_type);
                $entities = $storage->loadMultiple($unique_ids);
                
                foreach ($entities as $id => $entity) {
                    if ($entity->hasTranslation($current_language)) {
                        $entity = $entity->getTranslation($current_language);
                    }
                    $cache_key = $entity_type . '/' . $id;
                    $preloaded_entities[$cache_key] = $entity;
                }
            } 
            catch (\Exception $e) {
                $this->loggerFactory->get('relationship_nodes_search')->error(
                    'Failed to batch load @type entities: @message',
                    ['@type' => $entity_type, '@message' => $e->getMessage()]
                );
            }
        }
        
        return $preloaded_entities;
    }


    /**
     * Process field value using pre-loaded entity cache.
     *
     * @param mixed $raw_value
     *   The raw field value(s).
     * @param array $settings
     *   Display settings for this field.
     * @param array $preloaded_entities
     *   Pre-loaded entities keyed by "entity_type/id".
     *
     * @return array|null
     *   Processed field data structure, or NULL if empty.
     */
    public function batchProcessFieldValues($raw_value, array $settings, array $preloaded_entities): ?array {
        $value_arr = is_array($raw_value) ? $raw_value : [$raw_value];
        $display_mode = $settings['display_mode'] ?? 'raw';
        $processed_values = [];

        foreach ($value_arr as $raw_val) {
            if (in_array($display_mode, ['label', 'link']) && isset($preloaded_entities[$raw_val])) {
                // Use cached entity
                $entity = $preloaded_entities[$raw_val];
                $processed = [
                    'value' => $entity->label(),
                    'link_url' => null,
                ];
                
                if ($display_mode === 'link') {
                    try {
                        $processed['link_url'] = $entity->toUrl();
                    } catch (\Exception $e) {
                        // Entity doesn't support URLs
                    }
                }
                
                $processed_values[] = $processed;
            } else {
                // Fallback to regular processing (for non-cached or raw values)
                $processed_values[] = $this->processSingleFieldValue($raw_val, $display_mode);
            }
        }
        
        if (empty($processed_values)) {
            return null;
        }
        
        return [
            'field_values' => $processed_values,
            'separator' => $settings['multiple_separator'] ?? ', ',
            'is_multiple' => count($value_arr) > 1,
        ];
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
     * Loads entity in current language when available.
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
            if (!$entity) {
                return null;
            }
            
            $current_language = $this->languageManager->getCurrentLanguage()->getId();
            if ($entity->hasTranslation($current_language)) {
                $entity = $entity->getTranslation($current_language);
            }
        
            return $entity->label();
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