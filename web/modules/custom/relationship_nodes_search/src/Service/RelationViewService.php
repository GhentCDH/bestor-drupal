<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;



class RelationViewService {


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

        $result_fields = ['calculated_related_id', 'calculated_related_title'];

        $relation_type_field = $this->fieldNameResolver->getRelationTypeField();
        if(in_array($relation_type_field, $nested_fields)){
            $replaced_fields[] = $relation_type_field;
            $result_fields[] = 'calculated_relation_type_title';
        }

        foreach($nested_fields as $key => $nested_field){
            if(in_array($nested_field, $replaced_fields)){
                continue;
            }
            $result_fields[] = $nested_field;
        }
        return $result_fields;
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

        $index_field_config = $index_field->getConfiguration();
        if(!is_array($index_field_config) || empty($index_field_config['nested_fields'])){
            return [];
        }

        return $index_field_config['nested_fields'];
    }
}