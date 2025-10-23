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
    public function getProcessedNestedChildFieldNames(Index $index, string $field_name):array{
        $nested_fields = $this->getAllNestedChildFieldNames($index, $field_name);
        if(empty($nested_fields)){
            return [];
        }

        $related_entity_fields = $this->fieldNameResolver->getRelatedEntityFields();
        $replaced_fields = [];
        foreach($related_entity_fields as $related_entity_field){
            if(!in_array($related_entity_field, $nested_fields)){
                // Misconfigured relationship object
                return [];
            }
            // Unset - a default 'other entity' field will be added below.
            $replaced_fields[] = $related_entity_field;
        }

        $result_fields = array_values($this->getCalculatedFieldNames('related_entity'));
        $relation_type_field = $this->fieldNameResolver->getRelationTypeField();
        if(in_array($relation_type_field, $nested_fields)){
            $replaced_fields[] = $relation_type_field;
            $result_fields = array_merge($result_fields, array_values($this->getCalculatedFieldNames('relation_type')));
        }

        foreach($nested_fields as $nested_field){
            if(in_array($nested_field, $replaced_fields)){
                continue;
            }
            $result_fields[] = $nested_field;
        }
        return $result_fields;
    }

    /**
     * Returns a flat array of all the child field names of a parent field.
     */
    protected function getAllNestedChildFieldNames(Index $index, string $field_name):array{
        $field = $this->getIndexFieldInstance($index, $field_name);
        return $field instanceof Field ? array_keys($this->getAllNestedChildFieldsConfig($field)) : [];
    }



    /**
     * Returns an array (map/flat) or string containing one or more names of index fields, calculated by this module.
     */
    public function getCalculatedFieldNames(string $calculated_entity_key = null, string $property = null, bool $flatten = false):array|string|null{
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

        if ($calculated_entity_key === null) {
            if ($property === null) {
                return $flatten ? $this->flattenFieldsArray($calculated_fields) : $calculated_fields;
            }
            
            $result = [];
            foreach ($calculated_fields as $key => $props) {
                if (!empty($props[$property])) {
                    $result[$key] = $props[$property];
                }
            }
            return empty($result) ? null : ($flatten ? array_values($result) : $result);
        }

        $calculated_entity = $calculated_fields[$calculated_entity_key] ?? null;
        
        if (empty($calculated_entity)) {
            return null;
        }
        
        if ($property === null) {
            return $flatten ? array_values($calculated_entity) : $calculated_entity;
        }
        
        return $calculated_entity[$property] ?? null;
             
    }


    /**
     * Checks if a field is a calculated field created by this module.
     */
    public function isCalculatedChildField(string $field_name):bool{
        $calculated_fields = $this->getCalculatedFieldNames(null, null, true);
        if(!is_array($calculated_fields) || !in_array($field_name, $calculated_fields)){
            return false;
        }
        return true;
    }


    /**
     * Checks if a field is a parent index field, containing nested child fields.
     */
    protected function isNestedSapiField(Field $parent_field):bool{
        $index_field_config = $parent_field->getConfiguration() ?? [];
        if(!is_array($index_field_config) || empty($index_field_config['nested_fields'])){
            return false;
        }
        return true;
    }


    /**
     * Returns the config of the nested child fields of a parent field.
     */
    public function getAllNestedChildFieldsConfig(Field $parent_field):array{
        if(!$this->isNestedSapiField($parent_field)){
            return [];
        }
        return $parent_field->getConfiguration()['nested_fields'];
    }



    public function isNestedFieldEntityReference(Index $index, string $parent_field_name, string $nested_field_name): bool {
        $parent_field = $this->getIndexFieldInstance($index, $parent_field_name);
        $property = $parent_field instanceof Field ? $this->getNestedFieldProperty($parent_field) : null;
        
        
        return $property? $property->drupalFieldIsReference($nested_field_name) : false;
    }




    public function nestedFieldCanLink(Index $index, string $parent_field, string $nested_field){
        if($this->isCalculatedChildField($nested_field)) { 
            $calc_id_fields = $this->getCalculatedFieldNames(null, 'id', true);
            if(!in_array($nested_field, $calc_id_fields)){
                return false;
            }
        } else {
            if(!$this->isNestedFieldEntityReference($index, $parent_field, $nested_field)){
                return false;
            }
        }

        return true;
    }


    public function processSingleFieldValue($value, $display_mode = 'default'){
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
        
        if(empty($parsed_value['entity_type'] || empty($parsed_value['id']))){
            return $result;
        }    
        $result['value'] = $this->loadEntityLabel($parsed_value['entity_type'], $parsed_value['id']) ?: $value;
        if ($display_mode === 'link') {
            $result['link_url'] = $this->buildEntityUrl($parsed_value['entity_type'], $parsed_value['id']);
        }
        
        return $result;
    }


    public function getNestedFieldTargetType(Index $index, string $parent_field_name, string $nested_field_name): ?string {
        $parent_field = $this->getIndexFieldInstance($index, $parent_field_name);
        $property = $parent_field instanceof Field ? $this->getNestedFieldProperty($parent_field) : null;
        
        return $property ? $property->getDrupalFieldTargetType($nested_field_name) : null;
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

    
    public function formatCalculatedFieldLabel($field_name): string {
        $label = str_replace(['calculated_', '_'], ['', ' '], $field_name);
        return ucfirst(trim($label));
    }


    

    protected function flattenFieldsArray(array $calculated_fields):array{
        $result = [];
        foreach ($calculated_fields as $props) {
            foreach($props as $field_name){
                if(!empty($field_name)){
                    $result[] = $field_name;
                }
            }
        }
        return $result;
    }


    public function getNestedFieldProperty(Field $field): ?RelationProcessorProperty {
        $property = $field->getDataDefinition();
        return $property instanceof RelationProcessorProperty ? $property : null;
    }

  public function colonsToDots(string $field): string {
    return str_replace(':', '.', $field);
  }

public function getIndexFieldInstance(Index $index, string $field_name):?Field{
    $index_fields = $index->getFields();
    if (!isset($index_fields[$field_name])) {
        return null;
    }
    
    $field = $index_fields[$field_name];
    return $field instanceof Field ? $field : null;      
}


  public function validateNestedPath(Index $index, string $path): ?array {
    if (strpos($path, ':') === false) {
        return null;
    }
    [$parent, $child] = explode(':', $path, 2);
    $parent = trim($parent);
    $child  = trim($child);

    if(empty($parent) || empty($child)){
        return null;
    }
    $parent_field = $this->getIndexFieldInstance($index, $parent);
    if(!$parent_field instanceof Field){
        return null;
    }

    $prop = $this->getNestedFieldProperty($parent_field);
    if(!$prop instanceof RelationProcessorProperty){
      return null;
    }

    $all_nested = $this->getAllNestedChildFieldNames($parent_field);

    if(!in_array($child, $all_nested)){
        return null;
    }

    return ['parent' => $parent, 'child' => $child];
  }
}