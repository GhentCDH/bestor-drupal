<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\field\Entity\FieldStorageConfig;


class RelationValidationObjectFactory {

    protected FieldNameResolver $fieldNameResolver;
    protected RelationFieldConfigurator $fieldConfigurator;
    protected RelationBundleSettingsManager $settingsManager;
    protected RelationBundleInfoService $bundleInfoService;


    public function __construct(
        FieldNameResolver $fieldNameResolver,
        RelationFieldConfigurator $fieldConfigurator,
        RelationBundleSettingsManager $settingsManager,
        RelationBundleInfoService $bundleInfoService
    ) {
        $this->fieldNameResolver = $fieldNameResolver;
        $this->fieldConfigurator = $fieldConfigurator;
        $this->settingsManager = $settingsManager;
        $this->bundleInfoService = $bundleInfoService;
    }


    /*
    * Create an entity type validation object from a bundle entity object (NodeType or Vocabulary)
    */
    public function fromEntity(ConfigEntityBundleBase $entity): RelationBundleValidationObject {
        return new RelationBundleValidationObject(
            $entity->getEntityTypeId(),
            $this->settingsManager->getProperties($entity),
            $this->bundleInfoService->getNodeTypesLinkedToVocab($entity), 
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

        return new RelationBundleValidationObject(
            $entity->getEntityTypeId(),
            $form_state->getValue('relationship_nodes'),
            $this->bundleInfoService->getNodeTypesLinkedToVocab($entity), 
            $this->fieldNameResolver
        );
    }


    /*
    * Create an entity type validation object from a config yaml file (of the type node_type or taxonomy_vocabulary) (cf config import)
    */
    public function fromBundleConfigFile(string $config_name, StorageInterface $storage): ?RelationBundleValidationObject {
        $config_data = $storage->read($config_name);        
        $relation_settings = !empty($config_data['third_party_settings']['relationship_nodes'])
            ? $config_data['third_party_settings']['relationship_nodes']
            : [];
        if(empty($this->settingsManager->getConfigFileEntityClasses($config_name))){
            return null;
        }
        $entity_classes = $this->settingsManager->getConfigFileEntityClasses($config_name);
        $entity_type_id = $entity_classes['entity_type'];
        return new RelationBundleValidationObject(
            $entity_type_id,
            $relation_settings,
            $this->bundleInfoService->getCimNodeTypesLinkedToVocab($config_name, $storage),
            $this->fieldNameResolver
        );
    }


    /*
    * Create a fieldconfig validation object from a FieldConfig object
    */
    public function fromFieldConfig(FieldConfig $field_config):?RelationFieldConfigValidationObject{        
        return new RelationFieldConfigValidationObject(
            $field_config->getName(),
            $field_config->getTargetBundle(),
            $field_config->isRequired(),
            $field_config->getSetting('handler_settings')['target_bundles'] ?? null,
            null,
            $this->fieldNameResolver,
            $this->settingsManager
        );
    }


    /*
    * Create a fieldconfig validation object from a config yaml file of the type field.field.node/taxonomy_term (cf config import)
    */
    public function fromFieldConfigConfigFile(array $config_data, StorageInterface $storage):?RelationFieldConfigValidationObject{
        $target_bundles = empty($config_data['settings']['handler_settings']['target_bundles']) 
            ? null 
            : $config_data['settings']['handler_settings']['target_bundles'];
        return new RelationFieldConfigValidationObject(
            $config_data['field_name'],
            $config_data['bundle'],
            $config_data['required'],  
            $target_bundles,
            $storage,
            $this->fieldNameResolver,
            $this->settingsManager
        );

    }


    /*
    * Create a field storage validation object from a field storage object
    */
    public function fromFieldStorage(FieldStorageConfig $storage):?RelationFieldStorageValidationObject{
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
    public function fromFieldStorageConfigFile(array $config_data):?RelationFieldStorageValidationObject{
        $target_type =  empty($config_data['settings']['target_type']) 
            ? null 
            : $config_data['settings']['target_type'];
            print_r($config_data);
        return new RelationFieldStorageValidationObject(
            $config_data['field_name'],
            $config_data['type'],
            $config_data['cardinality'],
            $target_type,
            $this->fieldConfigurator 
        );
    }  
}