<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationField;


class FieldNameResolver {

    private const FIELD_NAMES = [
        'related_entity_fields' => [
            'related_entity_1' => 'rn_related_entity_1',
            'related_entity_2'=> 'rn_related_entity_2',
        ],
        'relation_type' => 'rn_relation_type',
        'mirror_fields' => [
            'mirror_entity_reference' => 'rn_mirror_reference',
            'mirror_string' => 'rn_mirror_string',
        ],
    ];


  
    public function getRelationTypeField(): string {
        return $this->getConfig('relation_type');
    }


    public function getRelatedEntityFields(?int $no = null): array|string {
        $fields = $this->getConfig('related_entity_fields') ?? [];
        if($no === 1 || $no === 2){
            return array_values($fields)[$no - 1] ?? '';
        }    
        return $fields;
    }


    public function getMirrorFields(?string $type = null): array|string {
        $options = ['string' => 'mirror_string', 'entity_reference' => 'mirror_entity_reference'];
        $fields  = $this->getConfig('mirror_fields')?? [];
        if($type !== null && isset($options[$type])){

            return $fields[$options[$type]] ?? '';
        }
        return $fields;
    }


    public function getOppositeMirrorField(string $field_name): ?string {
        return match($field_name){
            $this->getMirrorFields('string') => $this->getMirrorFields('entity_reference'),
            $this->getMirrorFields('entity_reference') => $this->getMirrorFields('string'),
            default => null
        };
    }


    public function getAllRelationFieldNames(): array{
        $fields = [];
        $relation_type = $this->getRelationTypeField();
        if ($relation_type) {
            $fields[] = $relation_type;
        }
        $related = $this->getRelatedEntityFields();
        if (!empty($related)) {
            $fields = array_merge($fields, array_values($related));
        }
        $mirror = $this->getMirrorFields();
        if (!empty($mirror)) {
            $fields = array_merge($fields, array_values($mirror));
        }
        return $fields;
    }

    public function getConfig(?string $key=null):string|array|null{
        $config = self::FIELD_NAMES;
        if($key == null){
            return $config;
        }
        if(!isset($config[$key])){
            return null;
        }
        return $config[$key];
    }
  
}