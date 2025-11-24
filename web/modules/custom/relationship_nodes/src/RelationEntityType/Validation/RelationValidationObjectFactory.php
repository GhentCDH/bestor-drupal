<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\field\Entity\FieldStorageConfig;


/**
 * Factory for creating validation objects.
 *
 * Creates validation objects from various sources including entities,
 * form state, and configuration files.
 */
class RelationValidationObjectFactory {

  protected FieldNameResolver $fieldNameResolver;
  protected RelationFieldConfigurator $fieldConfigurator;
  protected RelationBundleSettingsManager $settingsManager;


  /**
   * Constructs a RelationValidationObjectFactory.
   *
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver.
   * @param RelationFieldConfigurator $fieldConfigurator
   *   The field configurator.
   * @param RelationBundleSettingsManager $settingsManager
   *   The settings manager.
   */
  public function __construct(
    FieldNameResolver $fieldNameResolver,
    RelationFieldConfigurator $fieldConfigurator,
    RelationBundleSettingsManager $settingsManager,
  ) {
    $this->fieldNameResolver = $fieldNameResolver;
    $this->fieldConfigurator = $fieldConfigurator;
    $this->settingsManager = $settingsManager;
  }


  /**
   * Creates validation object from a bundle entity.
   *
   * @param ConfigEntityBundleBase $entity
   *   The bundle entity.
   *
   * @return RelationBundleValidationObject
   *   The validation object.
   */
  public function fromEntity(ConfigEntityBundleBase $entity): RelationBundleValidationObject {
    return new RelationBundleValidationObject(
      $entity->getEntityTypeId(),
      $this->settingsManager->getProperties($entity),
      $this->bundleInfoService->getNodeTypesLinkedToVocab($entity), 
      $this->fieldNameResolver
    );
  }


  /**
   * Creates validation object from form state.
   *
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return RelationBundleValidationObject|null
   *   The validation object or NULL.
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


  /**
   * Creates validation object from bundle configuration file.
   *
   * @param string $config_name
   *   The configuration name.
   * @param StorageInterface $storage
   *   The configuration storage.
   *
   * @return RelationBundleValidationObject|null
   *   The validation object or NULL.
   */
  public function fromBundleConfigFile(string $config_name, StorageInterface $storage): ?RelationBundleValidationObject {
    $config_data = $storage->read($config_name);        
    $relation_settings = !empty($config_data['third_party_settings']['relationship_nodes'])
      ? $config_data['third_party_settings']['relationship_nodes']
      : [];
    if (empty($this->settingsManager->getConfigFileEntityClasses($config_name))) {
      return null;
    }
    $entity_classes = $this->settingsManager->getConfigFileEntityClasses($config_name);

    $entity_type_id = $entity_classes['entity_type_id'];
    return new RelationBundleValidationObject(
      $entity_type_id,
      $relation_settings,
      $this->bundleInfoService->getCimNodeTypesLinkedToVocab($config_name, $storage),
      $this->fieldNameResolver
    );
  }


  /**
   * Creates validation object from field configuration.
   *
   * @param FieldConfig $field_config
   *   The field configuration.
   *
   * @return RelationFieldConfigValidationObject|null
   *   The validation object or NULL.
   */
  public function fromFieldConfig(FieldConfig $field_config): ?RelationFieldConfigValidationObject {        
    return new RelationFieldConfigValidationObject(
      $field_config->getName(),
      $field_config->getTargetBundle(),
      $field_config->isRequired(),
      $field_config->getType(),
      $field_config->getSetting('handler_settings')['target_bundles'] ?? null,
      null,
      $this->fieldNameResolver,
      $this->fieldConfigurator, 
      $this->settingsManager
    );
  }


  /**
   * Creates validation object from field configuration file.
   *
   * @param array $config_data
   *   The configuration data.
   * @param StorageInterface $storage
   *   The configuration storage.
   *
   * @return RelationFieldConfigValidationObject|null
   *   The validation object or NULL.
   */
  public function fromFieldConfigConfigFile(array $config_data, StorageInterface $storage): ?RelationFieldConfigValidationObject {
    $target_bundles = empty($config_data['settings']['handler_settings']['target_bundles']) 
      ? null 
      : $config_data['settings']['handler_settings']['target_bundles'];

    return new RelationFieldConfigValidationObject(
      $config_data['field_name'],
      $config_data['bundle'],
      $config_data['required'],  
      $config_data['field_type'],
      $target_bundles,
      $storage,
      $this->fieldNameResolver,
      $this->fieldConfigurator, 
      $this->settingsManager
    );
  }


  /**
   * Creates validation object from field storage.
   *
   * @param FieldStorageConfig $storage
   *   The field storage configuration.
   *
   * @return RelationFieldStorageValidationObject|null
   *   The validation object or NULL.
   */
  public function fromFieldStorage(FieldStorageConfig $storage): ?RelationFieldStorageValidationObject {
    return new RelationFieldStorageValidationObject(
      $storage->getName(),
      $storage->getType(),
      $storage->getCardinality(),
      $storage->getSetting('target_type') ?? null, 
      $this->fieldConfigurator 
    );
  }


  /**
   * Creates validation object from field storage configuration file.
   *
   * @param array $config_data
   *   The configuration data.
   *
   * @return RelationFieldStorageValidationObject|null
   *   The validation object or NULL.
   */
  public function fromFieldStorageConfigFile(array $config_data): ?RelationFieldStorageValidationObject {
    $target_type = empty($config_data['settings']['target_type']) 
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