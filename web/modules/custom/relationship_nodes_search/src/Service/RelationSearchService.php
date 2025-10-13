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
                isset($calculated_group['label']) && 
                isset($calculated_group['id']) &&
                $calculated_group['label'] === $title_field_name && 
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

        return $this->getNestedFields($index_field);
    }


    public function getCalculatedFieldNames(string $calculated_key = null, string $field =  null):array|string|null{
        $calculated_fields = [
            'this_entity' => [
                'id' => 'calculated_this_id',
                'label' => 'calculated_this_label',
            ],
            'related_entity' => [
                'id' => 'calculated_related_id',
                'label' => 'calculated_related_label',
            ],
            'relation_type' => [
                'id' => 'calculated_relation_type_id',
                'label' => 'calculated_relation_type_label',
            ],
        ];

       if ($calculated_key === null && $field === null){
            return $calculated_fields;
        } elseif($calculated_key !== null && $field === null && !empty($calculated_fields[$calculated_key])){
            return $calculated_fields[$calculated_key];        
        } elseif($calculated_key !== null && $field !== null && !empty($calculated_fields[$calculated_key][$field])){
            return $calculated_fields[$calculated_key][$field];     
        } 
        
        return null;
               
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
            if(($field_value_pair['label'] ?? null) === $field_name){
                return  !empty($field_value_pair['id']) ? $field_value_pair['id'] : null;
            }
        }
        return null;
    }


    public function getUrlForField(string $field_name, array $item): ?Url {
        $calculated_fields = $this->getCalculatedFieldNames();
        
        foreach ($calculated_fields as $type => $field_value_pair) {
            if (($field_value_pair['label'] ?? null) === $field_name) {
                $id_field = $field_value_pair['id'] ?? null;
                
                if (!$id_field || !isset($item[$id_field])) {
                    return null;
                }
                
                $entity_id = $item[$id_field];
                
                if(in_array($type, ['related_entity', 'this_entity'])){
                    return Url::fromRoute('entity.node.canonical', [
                        'node' => $entity_id
                    ]);
                } elseif($type === 'relation_type'){
                    return Url::fromRoute('entity.taxonomy_term.canonical', [
                        'taxonomy_term' => $entity_id
                    ]);
                }           
            }
        }
        return null;
    }
}