<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationField;


use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;


class VirtualFieldAdder {

  protected RelationBundleInfoService $bundleInfoService;

  
  public function __construct(RelationBundleInfoService $bundleInfoService) {
    $this->bundleInfoService = $bundleInfoService;
  }


  public function addFields(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($entity_type->id() !== 'node') {
      return;
    }

    $relationships = $this->bundleInfoService->getRelationInfoForTargetBundle($bundle);

    if (empty($relationships)) {
      return;
    }

    $bundles_involved = [];
    foreach ($relationships as $relation_bundle => $relationship) {
      $field_name = 'computed_relationshipfield__' . $bundle . '__' . implode('_', $relationship['related_bundles']);
      $fields[$field_name] = BaseFieldDefinition::create('entity_reference')
        ->setName($field_name)
        ->setLabel('Relationships with ' . implode(', ', $relationship['related_bundles']))
        ->setDescription(t('This computed field lists all the relationships between @this and @related.', [
          '@this' => $bundle,
          '@related' => implode(', ', $relationship['related_bundles']),
        ]))
        ->setClass(ReferencingRelationshipItemList::class)
        ->setComputed(TRUE)
        ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
        ->setTargetEntityTypeId('node')
        ->setTargetBundle($relation_bundle)
        ->setDisplayOptions('form', [
          'type' => 'ief_validated_relations_simple',
          'weight' => 0,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE)
        ->setSetting('handler_settings', [
          'target_bundles' => [$relation_bundle],
        ])
        ->setSetting('join_field', $relationship['join_fields'])
        ->setRevisionable(FALSE);
    }
  }
}