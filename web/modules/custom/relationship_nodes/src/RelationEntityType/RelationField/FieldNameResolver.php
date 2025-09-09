<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationField;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

class FieldNameResolver {

    protected ConfigFactoryInterface $configFactory;
    

    public function __construct(ConfigFactoryInterface $configFactory) {
        $this->configFactory = $configFactory;
    }


    public function getRelationTypeField(): string {
        return $this->getConfig()->get('relation_type') ?? '';
    }


    public function getRelatedEntityFields(?int $no = null): array|string {
        $fields = $this->getConfig()->get('related_entity_fields') ?? [];
        if($no === 1 || $no === 2){
            return array_values($fields)[$no - 1] ?? '';
        }    
        return $fields;
    }


    public function getMirrorFields(?string $type = null): array|string {
        $options = ['string' => 'mirror_string', 'entity_reference' => 'mirror_entity_reference'];
        $fields  = $this->getConfig()->get('mirror_fields') ?? [];
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


    public function getConfig(): ImmutableConfig {
        return $this->configFactory->get('relationship_nodes.settings');
    }    
}