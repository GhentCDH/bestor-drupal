<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\ViewsHandlerInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;


class RelationSearchService {

    protected FieldNameResolver $fieldNameResolver;


    public function __construct(
        FieldNameResolver $fieldNameResolver   
    ) {
        $this->fieldNameResolver = $fieldNameResolver;
    }
    
    public function getCalculatedFields(Index $index, string $field_name):array{
        $nested_fields = $this->getOriginalNestedFields($index, $field_name);
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

        foreach($nested_fields as $key => $nested_field){
            if(in_array($nested_field, $replaced_fields)){
                continue;
            }
            $result_fields[] = $nested_field;
        }
        return $result_fields;
    }


    public function titleFieldHasMatchingIdField(Index $index, string $parent_field_name, string $title_field_name):bool{
  
        $nested_fields = $this->getCalculatedFields($index, $parent_field_name);
        
        if(empty($nested_fields)){
            return false;
        }

        foreach($this->getCalculatedFieldNames() as $calculated_group){
            if(
                isset($calculated_group['name']) && 
                isset($calculated_group['id']) &&
                $calculated_group['name'] === $title_field_name && 
                in_array($calculated_group['id'], $nested_fields)
            ){
                return true;
            }
        }
        return false;
    }


    public function getOriginalNestedFields(Index $index, string $field_name):array{
    
        $index_fields = $index->getFields();
        if(!is_array($index_fields) || empty($index_fields[$field_name])){
            return [];
        }

        $index_field = $index_fields[$field_name];

        if(!$index_field instanceof Field){
            return [];
        }

        return array_keys($this->getNestedFields($index_field));
    }


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

    public function isPredefinedRelationField(string $field_name):bool{
        $calculated_fields = $this->getCalculatedFieldNames(null, null, true) ?? [];
        $rn_related_entity_fields = array_values($this->fieldNameResolver->getRelatedEntityFields());
        $rn_relationtype_field = [$this->fieldNameResolver->getRelationTypeField()];
        $all_predefined = array_merge($calculated_fields, $rn_related_entity_fields, $rn_relationtype_field);
    
        return in_array($field_name, $all_predefined, true);
    }

    public function isNestedSapiField(Field $field):bool{
        $index_field_config = $field->getConfiguration() ?? [];
        if(!is_array($index_field_config) || empty($index_field_config['nested_fields'])){
            return false;
        }
        return true;
    }

    public function getNestedFields(Field $field):array{
        if(!$this->isNestedSapiField($field)){
            return [];
        }

        return  $field->getConfiguration()['nested_fields'];
    }


    public function getIdFieldForLabelField(string $field_name): ?string{
        $calculated_fields = $this->getCalculatedFieldNames();
        foreach($calculated_fields as $field_value_pair){
            if(($field_value_pair['name'] ?? null) === $field_name){
                return  !empty($field_value_pair['id']) ? $field_value_pair['id'] : null;
            }
        }
        return null;
    }


    public function getUrlForField(string $field_name, array $item): ?Url {
        $calculated_fields = $this->getCalculatedFieldNames();
        
        foreach ($calculated_fields as $type => $field_value_pair) {
            if (($field_value_pair['name'] ?? null) === $field_name) {
                $id_field = $field_value_pair['id'] ?? null;
                
                if (!$id_field || !isset($item[$id_field])) {
                    return null;
                }
                
                $parsed_value = $this->parseEntityReferenceValue($item[$id_field]);

                if(empty($parsed_value['entity_type'] || empty($parsed_value['id']))){
                    continue;
                } 
                $entity_type = $parsed_value['entity_type'];
                $entity_id = $parsed_value['id'];
                         
                return Url::fromRoute('entity.' . $entity_type . '.canonical', [
                    $entity_type => $entity_id
                ]);
                   
            }
        }
        return null;
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
}