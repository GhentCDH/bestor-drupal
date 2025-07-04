<?php

namespace Drupal\relationship_nodes\Service;


use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList;
use Drupal\relationship_nodes\Service\RelationshipInfoService;


class RelationshipFieldAutoAdder {

    protected $relationshipInfoService;
  

    public function __construct(RelationshipInfoService $relationshipInfoService) {
      $this->relationshipInfoService = $relationshipInfoService;
    }


   /**
   * Voeg dynamische relationship-velden toe aan een node-bundle.
   *
   * @param array $fields
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param string $bundle
   */
  public function addFields(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($entity_type->id() !== 'node') {
      return;
    }

    $relationships = $this->relationshipInfoService->relationshipInfoForRelatedItemNodeType($entity_type, $bundle);

    if (empty($relationships)) {
      return;
    }

    $bundles_involved = [];
    foreach ($relationships as $relationship) {
      $field_name = 'computed_relationshipfield__' . $relationship['this_bundle'] . '__' . $relationship['related_bundle'];
      $fields[$field_name] = BaseFieldDefinition::create('entity_reference')
        ->setName($field_name)
        ->setLabel('Relationships with ' . $relationship['related_bundle'])
        ->setDescription(t('This computed field lists all the relationships between @this and @related.', [
          '@this' => $relationship['this_bundle'],
          '@related' => $relationship['related_bundle'],
        ]))
        ->setClass(ReferencingRelationshipItemList::class)
        ->setComputed(TRUE)
        ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
        ->setTargetEntityTypeId('node')
        ->setTargetBundle($relationship['relationship_bundle'])
        ->setDisplayOptions('form', [
          'type' => 'ief_validated_relations_simple',
          'weight' => 0,
          'settings' => ['form_mode' => \Drupal::service('relationship_nodes.relationship_info_service')->getRelationshipFormMode() ?? 'default'],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE)
        ->setSetting('handler_settings', [
          'target_bundles' => [$relationship['relationship_bundle']],
        ])
        ->setSetting('join_field', $relationship['join_fields'])
        ->setRevisionable(FALSE);
    }
  }
}


