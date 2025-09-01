<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationField;

use Drupal\Core\Entity\ConfigEntityBundleBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class RelationFieldConfigurator {
    protected EntityTypeManagerInterface $entityTypeManager;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationBundleSettingsManager $settingsManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        FieldNameResolver $fieldNameResolver, 
        RelationBundleSettingsManager $settingsManager,
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->settingsManager = $settingsManager;
    }


    public function getFieldStatus(ConfigEntityBundleBase $entity): array {
        $required_fields = $this->getRequiredFields($entity);
        $storage = $this->entityTypeManager->getStorage('field_config');
        $entity_type_id = $this->settingsManager->getEntityTypeId($entity);

        $existing = $missing = $remove = [];

        foreach ($required_fields as $field_name => $settings) {
            $field_config = $storage->load("$entity_type_id.{$entity->id()}.$field_name");
            if (!$field_config) {
                $missing[$field_name] = ['settings' => $settings];
            } else {
                $existing[$field_name] = ['settings' => $settings, 'field_config' => $field_config];
            }

            if ($incompatible = $this->fieldNameResolver->getOppositeMirrorField($field_name)) {
                $field_to_remove = $storage->load("$entity_type_id.{$entity->id()}.$incompatible");
                if ($field_to_remove) $remove[] = $incompatible;
            }
        }

        return ['existing' => $existing, 'missing' => $missing, 'remove' => $remove];
    }


    protected function getRequiredFields(ConfigEntityBundleBase $entity): array {
        $fields = [];
        if ($entity instanceof NodeType) {
            foreach($this->fieldNameResolver->getRelatedEntityFields() as $related_entity_field){
                $fields[$related_entity_field] = ['type' => 'entity_reference', 'target_type' => 'node', 'cardinality' => 1];
            }
            if ($this->settingsManager->isTypedRelationNode($entity)) {
                $fields[$this->fieldNameResolver->getRelationTypeField()] = ['type' => 'entity_reference', 'target_type' => 'taxonomy_term', 'cardinality' => 1];
            }
        } elseif ($entity instanceof Vocabulary) {
            $type = $this->settingsManager->getProperty($entity, 'referencing_type');
            switch ($type) {
                case 'self':
                    $fields[$this->fieldNameResolver->getMirrorFields('self')] = ['type' => 'entity_reference', 'target_type' => 'taxonomy_term', 'cardinality' => 1];
                    break;
                case 'cross':
                    $fields[$this->fieldNameResolver->getMirrorFields('cross')] = ['type' => 'string', 'cardinality' => 1];
                    break;
            }
        }
        return $fields;
    }


    public function createFields(ConfigEntityBundleBase $entity, array $missing_fields): void {
        $field_storage_config_storage = $this->entityTypeManager->getStorage('field_storage_config');
        $field_config_storage = $this->entityTypeManager->getStorage('field_config');

        $entity_type_id = $this->settingsManager->getEntityTypeId($entity);

        foreach ($missing_fields as $field_name => $field_arr) {
            $settings = $field_arr['settings'];
            $field_storage = $field_storage_config_storage->load("$entity_type_id.$field_name");
            if (!$field_storage) {
                $field_storage = $field_storage_config_storage->create([
                    'field_name' => $field_name,
                    'entity_type' => $entity_type_id,
                    'type' => $settings['type'],
                    'cardinality' => $settings['cardinality'],
                    'settings' => $settings['target_type'] ? ['target_type' => $settings['target_type']] : [],
                    'third_party_settings' => ['relationship_nodes' => ['rn_created'=> true]],
                ]);
                $field_storage->setLocked(true);
                $field_storage->save();
            }

            $field_config = $field_config_storage->load("$entity_type_id.{$entity->id()}.$field_name");
            if (!$field_config) {
                $field_config = $field_config_storage->create([
                    'field_name' => $field_name,
                    'bundle' => $entity->id(),
                    'entity_type' => $entity_type_id,
                    'label' => ucfirst(str_replace('_', ' ', $field_name)),
                    'required' => true,
                    'third_party_settings' => ['relationship_nodes' => ['rn_created'=> true]],
                ]);
                $field_config->save();
            }
        }
    }

 
    public function ensureFieldConfig(ConfigEntityBundleBase $entity, array $existing_fields): void {
        foreach ($existing_fields as $field_arr) {
            $field_config = $field_arr['field_config'];
            $field_storage = $field_config->getFieldStorageDefinition();
            if (!$field_storage->isLocked()) {
                $field_storage->setLocked(true)->save();
            }
            if (!$field_storage->getThirdPartySetting('relationship_nodes', 'rn_created', false)) {
                $field_storage->setThirdPartySetting('relationship_nodes', 'rn_created', true)->save();
            }
            if (!$field_config->getThirdPartySetting('relationship_nodes', 'rn_created', false)) {
                $field_config->setThirdPartySetting('relationship_nodes', 'rn_created', true)->save();
            }
        }
    }


    public function removeFields(ConfigEntityBundleBase $entity, array $fields_to_remove): void {
        $storage = $this->entityTypeManager->getStorage('field_config');
        $entity_type_id = $this->settingsManager->getEntityTypeId($entity);

        foreach ($fields_to_remove as $field_name) {
            $field_config = $storage->load("$entity_type_id.{$entity->id()}.$field_name");
            if ($field_config) $field_config->delete();
        }
    }


    public function isRnCreatedField(FieldConfig $field_config) : bool{
        return (bool) $field_config->getThirdPartySetting('relationship_nodes', 'rn_created', FALSE);
    }

}