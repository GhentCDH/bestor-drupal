<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;


class RelationValidationObjectFactory {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationFieldConfigurator $fieldConfigurator;
    protected RelationBundleSettingsManager $settingsManager;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager, 
        FieldNameResolver $fieldNameResolver,
        RelationFieldConfigurator $fieldConfigurator,
        RelationBundleSettingsManager $settingsManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->fieldConfigurator = $fieldConfigurator;
        $this->settingsManager = $settingsManager;
    }


    /*
    * Create an entity type validation object from a bundle entity object (NodeType or Vocabulary)
    */
    public function fromEntity(ConfigEntityBundleBase $entity): RelationBundleValidationObject {
        return new RelationBundleValidationObject(
            $entity->getEntityTypeId(),
            $this->settingsManager->getProperties($entity),
            $this->entityTypeManager->getStorage('field_config'), 
            $this->fieldNameResolver
        );
    }


    /*
    * Create an entity type validation object  from a FormStateInterface object (bundle entity config form)
    */
    public function fromFormState(FormStateInterface $form_state): ?RelationBundleValidationObject {
        $entity = $form_state->getFormObject()->getEntity();
        if (!$entity instanceof ConfigEntityBundleBase) {
            return null;
        }
        if (empty($form_state->getValue('relationship_nodes')['enabled'])) {
            return null;
        }
        return new RelationBundleValidationObject(
            $entity->getEntityTypeId(),
            $form_state->getValue('relationship_nodes'),
            $this->entityTypeManager->getStorage('field_config'), 
            $this->fieldNameResolver
        );
    }


    /*
    * Create an entity type validation object from a config yaml file (of the type node_type or taxonomy_vocabulary) (cf config import)
    */
    public function fromBundleConfigFile(string $config_name, StorageInterface $storage): ?RelationBundleValidationObject {
        $config_data = $storage->read($config_name);
        if (empty($config_data['third_party_settings']['relationship_nodes']['enabled'])) {
            return null;
        }

        $relation_settings = $config_data['third_party_settings']['relationship_nodes'];
        $entity_type_id = $this->settingsManager->getConfigFileEntityClasses($config_name)['entity_type'];
        return new RelationBundleValidationObject(
            $entity_type_id,
            $relation_settings,
            $storage, 
            $this->fieldNameResolver
        );
    }


    /*
    * Create a fieldconfig validation object from a FieldConfig object
    */
    public function fromFieldConfig(FieldConfig $field_config){        
        return new RelationFieldConfigValidationObject(
            $field_config->getName(),
            $field_config->getTargetBundle(),
            $field_config->isRequired(),
            $field_config->getSetting('handler_settings')['target_bundles'] ?? null,
            $this->fieldNameResolver,
            $this->settingsManager
        );
    }


    /*
    * Create a fieldconfig validation object from a config yaml file of the type field.field.node/taxonomy_term (cf config import)
    */
    public function fromFieldConfigConfigFile($config_data){
        $target_bundles = empty($config_data['settings']['handler_settings']['target_bundles']) 
            ? null 
            : $config_data['settings']['handler_settings']['target_bundles'];
        return new RelationFieldConfigValidationObject(
            $config_data['field_name'],
            $config_data['bundle'],
            $config_data['required'],  
            $target_bundles,
            $this->fieldNameResolver,
            $this->settingsManager
        );

    }


    /*
    * Create a field storage validation object from a field storage object
    */
    public function fromFieldStorage($storage){
        return new RelationFieldStorageValidationObject(
            $storage->getName(),
            $storage->getType(),
            $storage->getCardinality(),
            $storage->getSetting('target_type') ?? null, 
            $this->fieldConfigurator 
        );
    }


    /*
    * Create a field storage validation object from a config yaml file of the type field.storage.node/taxonomy_term (cf config import)
    */
    public function fromFieldStorageConfigFile($config_data){
        $target_type =  empty($config_data['settings']['target_type']) 
            ? null 
            : $config_data['settings']['target_type'];
        return new RelationFieldStorageValidationObject(
            $config_data['field_name'],
            $config_data['type'],
            $config_data['cardinality'],
            $target_type,
            $this->fieldConfigurator 
        );
    }  


    
}