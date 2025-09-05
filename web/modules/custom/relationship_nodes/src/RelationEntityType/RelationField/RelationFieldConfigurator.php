<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationField;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\ConfigException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;

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


    public function getRequiredFieldConfiguration(string $field_name): ?array {
        if (in_array($field_name, $this->fieldNameResolver->getRelatedEntityFields())) {
            return [
                'type' => 'entity_reference',
                'target_type' => 'node',
                'cardinality' => 1,
            ];
        } elseif ($field_name === $this->fieldNameResolver->getRelationTypeField()) {
            return [
                'type' => 'entity_reference',
                'target_type' => 'taxonomy_term',
                'cardinality' => 1,
            ];
        } elseif ($field_name === $this->fieldNameResolver->getMirrorFields('cross')) {
            return [
                'type' => 'string',
                'cardinality' => 1,
            ];
        } elseif ($field_name === $this->fieldNameResolver->getMirrorFields('self')) {
            return [
                'type' => 'entity_reference',
                'target_type' => 'taxonomy_term',
                'cardinality' => 1,
            ];
        }
        return null;
    }


    public function getFieldStatus(ConfigEntityBundleBase $entity): array {
        $required_fields = $this->getRequiredFields($entity);
        $storage = $this->entityTypeManager->getStorage('field_config');
        $entity_type_id = $this->settingsManager->getEntityTypeId($entity);

        $existing = $missing = $remove = [];
        dpm($required_fields, 'req');
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
            foreach($this->fieldNameResolver->getRelatedEntityFields() as $field_name){
                $config = $this->getRequiredFieldConfiguration($field_name);
                if ($config) {
                    $fields[$field_name] = $config;
                }
            }
            if ($this->settingsManager->isTypedRelationNode($entity)) {
                $field_name = $this->fieldNameResolver->getRelationTypeField();
                $config = $this->getRequiredFieldConfiguration($field_name);
                dpm($config, 'config');
                if ($config) {
                    $fields[$field_name] = $config;
                }
            }
        } elseif ($entity instanceof Vocabulary) {
            if($type = $this->settingsManager->getProperty($entity, 'referencing_type')){
                $field_name = $this->fieldNameResolver->getMirrorFields($type);
                if($field_name){
                    $config = $this->getRequiredFieldConfiguration($field_name);
                    if ($config) {
                        $fields[$field_name] = $config;
                    }
                }          
            }
        }
        dpm($fields, 'fields');
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
                    'settings' => isset($settings['target_type']) ? ['target_type' => $settings['target_type']] : [],
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


    public function isRnCreatedField(FieldConfig|FieldStorageConfig $field) : bool{
        return (bool) $field->getThirdPartySetting('relationship_nodes', 'rn_created', FALSE);
    }


    public function getAllRnCreatedFields(?string $entity_type_id = null) : array {
        $entity_types = ['field_storage_config', 'field_config'];
        if($entity_type_id !== null && !in_array($entity_type_id, $entity_types)){
            return [];
        }

        $input = $entity_type_id !== null ? [$entity_type_id] : $entity_types;

        $result = []; 
        foreach($input as $entity_type){
            $storage = $this->entityTypeManager->getStorage($entity_type);
            if(!$storage instanceof EntityStorageInterface){
                continue;
            }
            $all = $storage->loadMultiple();
            foreach ($all as $type) {
                if($type instanceof ConfigEntityBase && $this->isRnCreatedField($type)){
                    $result[$type->id()] = $type;
                } 
            }    
        }

        return $result;
    }
}