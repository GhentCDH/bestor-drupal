<?php

namespace Drupal\relationship_nodes\Service;


use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList;
use Drupal\relationship_nodes\Service\ConfigManager;
use Drupal\relationship_nodes\Service\RelationshipInfoService;


class RelationshipFieldAutoAdder {

    
  protected ConfigManager $configManager;
  protected RelationshipInfoService $infoService;

  
  public function __construct(ConfigManager $configManager, RelationshipInfoService $infoService) {
    $this->configManager = $configManager;
    $this->infoService = $infoService;
  }


  public function addFields(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($entity_type->id() !== 'node') {
      return;
    }

    $relationships = $this->infoService->getRelationInfoForTargetBundle($bundle);

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
          'settings' => ['form_mode' => $this->configManager->getRelationFormMode() ?? 'default'],
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