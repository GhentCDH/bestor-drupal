<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\ViewsHandlerInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes_search\Processor\RelationProcessorProperty;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;


class RelationSearchService {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected EntityFieldManagerInterface $entityFieldManager;
    protected LoggerChannelFactoryInterface $loggerFactory;
    protected CacheBackendInterface $cacheBackend;
    protected FieldNameResolver $fieldNameResolver;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager, 
        EntityFieldManagerInterface $entityFieldManager,
        LoggerChannelFactoryInterface $loggerFactory,
        CacheBackendInterface $cacheBackend,
        FieldNameResolver $fieldNameResolver
    ){
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
        $this->loggerFactory = $loggerFactory;
        $this->cacheBackend = $cacheBackend;
        $this->fieldNameResolver = $fieldNameResolver;
    }
    

    /**
     * Gets the relevant child fields for a specific (configured) parent field.
     * Removes unrequested/unnecessary source child fields an returns a flat array of the usefull child field names.
     */
    public function getProcessedNestedChildFieldNames(Index $index, string $sapi_fld_nm):array{
        $child_fld_nms = $this->getAllNestedChildFieldNames($index, $sapi_fld_nm);
        if(empty($child_fld_nms)){
            return [];
        }

        $related_entity_flds = $this->fieldNameResolver->getRelatedEntityFields();
        $remove = [];
        foreach($related_entity_flds as $related_entity_fld){
            if(!in_array($related_entity_fld, $child_fld_nms)){
                // Misconfigured relationship object
                return [];
            }
            // Unset - 'other entity' field is to be used.
            $remove[] = $related_entity_fld;
        }

        $relation_type_fld = $this->fieldNameResolver->getRelationTypeField();
        if(in_array($relation_type_fld, $child_fld_nms)){
            $remove[] = $relation_type_fld;
        } else {
            foreach($this->getCalculatedFieldNames('relation_type', null, true) as $relation_type_fld){
                $remove[] = $relation_type_fld;
            }
        }
        $result_flds = [];
        foreach($child_fld_nms as $child_fld_nm){
            if(in_array($child_fld_nm, $remove)){
                continue;
            }
            $result_flds[] = $child_fld_nm;
        }
        return $result_flds;
    }

    /**
     * Returns a flat array of all the child field names of a parent field.
     */
    protected function getAllNestedChildFieldNames(Index $index, string $sapi_fld_nm):array{
        $sapi_fld = $this->getIndexFieldInstance($index, $sapi_fld_nm);
        return $sapi_fld instanceof Field ? array_keys($this->getAllNestedChildFieldsConfig($sapi_fld)) : [];
    }



    /**
     * Returns an array (map/flat) or string containing one or more names of index fields, calculated by this module.
     */
    public function getCalculatedFieldNames(string $calc_entity_key = null, string $property = null, bool $flatten = false):array{
        $calculated_fields = [
            'this_entity' => [
                'id' => 'calculated_this_id',
                'name' => 'calculated_this_name',
            ],
            'related_entity' => [
                'id' => 'calculated_related_id',
                'name' => 'calculated_related_name',
            ],
            'relation_type' => [
                'name' => 'calculated_relation_type_name',
            ],
        ];

        if ($calc_entity_key === null) {
            if ($property === null) {
                return $flatten ? $this->flattenFieldsArray($calculated_fields) : $calculated_fields;
            }
            
            $result = [];
            foreach ($calculated_fields as $key => $props) {
                if (!empty($props[$property])) {
                    $result[$key] = $props[$property];
                }
            }
            return $flatten ? array_values($result) : $result;
        }

        $calculated_entity = $calculated_fields[$calc_entity_key] ?? [];
        
        if (empty($calculated_entity)) {
            return [];
        }
        
        if ($property === null) {
            return $flatten ? array_values($calculated_entity) : $calculated_entity;
        }
        
        return isset($calculated_entity[$property]) ? [$calculated_entity[$property]] : [];
             
    }


    public function getCalculatedFieldTargetType(string $child_fld_nm): ?string {
        $calc_fld_ids = $this->getCalculatedFieldNames(null,'id');
        if(!in_array($child_fld_nm, $calc_fld_ids)){
            return null;
        }
        foreach($calc_fld_ids as $calc_entity_key => $calc_fld_id){
            if($calc_fld_id !== $child_fld_nm){
                continue;
            }
            if(in_array($calc_entity_key, ['this_entity', 'related_entity'])){
                return 'node';
            } elseif ($calc_entity_key === 'relation_type'){
                return 'taxonomy_term';
            }
            break;
        }
        return null;
    }


    /**
     * Checks if a field is a calculated field created by this module.
     */
    public function isCalculatedChildField(string $child_fld_nm):bool{
        $calc_flds = $this->getCalculatedFieldNames(null, null, true);
        if(in_array($child_fld_nm, $calc_flds)){
            return true;
        }
        return false;
    }


    /**
     * Checks if a field is a parent index field, containing nested child fields.
     */
    public function isNestedSapiField(Field $sapi_fld):bool{
        $index_field_config = $sapi_fld->getConfiguration() ?? [];
        if(!is_array($index_field_config) || empty($index_field_config['nested_fields'])){
            return false;
        }
        return true;
    }


    /**
     * Returns the config of the nested child fields of a parent field.
     */
    public function getAllNestedChildFieldsConfig(Field $sapi_fld):array{
        if(!$this->isNestedSapiField($sapi_fld)){
            return [];
        }
        return $sapi_fld->getConfiguration()['nested_fields'];
    }



    public function isNestedFieldEntityReference(Index $index, string $sapi_fld_nm, string $child_fld_nm): bool {
        $sapi_fld = $this->getIndexFieldInstance($index, $sapi_fld_nm);
        $property = $sapi_fld instanceof Field ? $this->getNestedFieldProperty($sapi_fld) : null;
        
        return $property? $property->drupalFieldIsReference($child_fld_nm) : false;
    }




    public function nestedFieldCanLink(Index $index, string $sapi_fld_nm, string $child_fld_nm){
        if($this->isCalculatedChildField($child_fld_nm)) { 
            $calc_id_fields = $this->getCalculatedFieldNames(null, 'id', true);
            if(!in_array($child_fld_nm, $calc_id_fields)){
                return false;
            }
        } else {
            if(!$this->isNestedFieldEntityReference($index, $sapi_fld_nm, $child_fld_nm)){
                return false;
            }
        }

        return true;
    }


    public function processSingleFieldValue($value, $display_mode = 'raw'){
        $result = ['value' => $value, 'link_url' => null];
        if(!in_array($display_mode, ['label', 'link'])){ // options only available if reference
            return $result;
        }

        return $this->processEntityReferenceValue($value, $display_mode);
    }


    /**
     * Process entity reference value to get label and optionally link.
     */
    protected function processEntityReferenceValue(string $value, string $display_mode): array {
        $result = ['value' => $value, 'link_url' => null];

        $parsed_value = $this->parseEntityReferenceValue($value);
        
        if(empty($parsed_value['entity_type']) || empty($parsed_value['id'])){
            return $result;
        }    
        $result['value'] = $this->loadEntityLabel($parsed_value['entity_type'], $parsed_value['id']) ?: $value;
        if ($display_mode === 'link') {
            $result['link_url'] = $this->buildEntityUrl($parsed_value['entity_type'], $parsed_value['id']);
        }
        
        return $result;
    }


    public function getNestedFieldTargetType(Index $index, string $sapi_fld_nm, string $child_fld_nm): ?string {
        $sapi_fld = $this->getIndexFieldInstance($index, $sapi_fld_nm);
        $property = $sapi_fld instanceof Field ? $this->getNestedFieldProperty($sapi_fld) : null;
        return $property ? $property->getDrupalFieldTargetType($child_fld_nm) : null;
    }


    public function parseEntityReferenceValue($value): ?array {
        if (empty($value)) {
            return null;
        }
        
        if (is_string($value) && strpos($value, '/') !== false) {
            [$type, $id] = explode('/', $value, 2);
            return ['entity_type'=> $type, 'id'=> $id];
        }

        return null;
    }


    public function loadEntityLabel(string $entity_type, $entity_id): ?string {
        try {
            $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
            return $entity ? $entity->label() : null;
        } catch (\Exception $e) {
            return null;
        }
    }


    public function buildEntityUrl(string $entity_type, $entity_id): ?Url {
        try {
            return Url::fromRoute(
                'entity.' . $entity_type . '.canonical',
                [$entity_type => $entity_id]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    
    public function formatCalculatedFieldLabel($calc_fld_nm): string {
        $label = str_replace(['calculated_', '_'], ['', ' '], $calc_fld_nm);
        return ucfirst(trim($label));
    }


    

    protected function flattenFieldsArray(array $calc_fld_arr):array{
        $result = [];
        foreach ($calc_fld_arr as $props) {
            foreach($props as $calc_fld_nm){
                if(!empty($calc_fld_nm)){
                    $result[] = $calc_fld_nm;
                }
            }
        }
        return $result;
    }


    public function getNestedFieldProperty(Field $sapi_fld): ?RelationProcessorProperty {
        $property = $sapi_fld->getDataDefinition();
        return $property instanceof RelationProcessorProperty ? $property : null;
    }

  public function colonsToDots(string $str): string {
    return str_replace(':', '.', $str);
  }

public function getIndexFieldInstance(Index $index, string $sapi_fld_nm):?Field{
    $index_flds = $index->getFields();
    if (!isset($index_flds[$sapi_fld_nm])) {
        return null;
    }
    
    $sapi_fld = $index_flds[$sapi_fld_nm];
    return $sapi_fld instanceof Field ? $sapi_fld : null;      
}


  public function validateNestedPath(Index $index, string $path): ?array {
    if (strpos($path, ':') === false) {
        return null;
    }
    [$sapi_fld_nm, $child_fld_nm] = explode(':', $path, 2);
    $sapi_fld_nm = trim($sapi_fld_nm);
    $child_fld_nm  = trim($child_fld_nm);
    if(empty($sapi_fld_nm) || empty($child_fld_nm)){
        return null;
    }

    $child_fld_nms = $this->getAllNestedChildFieldNames($index, $sapi_fld_nm);

    if(!in_array($child_fld_nm, $child_fld_nms)){
        return null;
    }

    $sapi_fld = $this->getIndexFieldInstance($index, $sapi_fld_nm);
   

    $prop = $this->getNestedFieldProperty($sapi_fld);

    if(!$prop instanceof RelationProcessorProperty){
      return null;
    }


    return ['parent' => $sapi_fld_nm, 'child' => $child_fld_nm];
  }

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
}