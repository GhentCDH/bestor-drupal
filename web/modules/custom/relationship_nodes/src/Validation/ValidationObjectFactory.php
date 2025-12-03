<?php

namespace Drupal\relationship_nodes\Validation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationBundle\Settings\BundleSettingsManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\relationship_nodes\RelationField\RelationshipFieldManager;
use Drupal\relationship_nodes\RelationBundle\BundleInfoService;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\relationship_nodes\Validation\Bundle\BundleValidator;
use Drupal\relationship_nodes\Validation\Field\FieldConfigValidator;
use Drupal\relationship_nodes\Validation\Field\FieldStorageValidator;


/**
 * Factory for creating validation objects.
 *
 * Creates validation objects from various sources including entities,
 * form state, and configuration files.
 */
class ValidationObjectFactory {

  protected FieldNameResolver $fieldNameResolver;
  protected RelationshipFieldManager $relationFieldManager;
  protected BundleSettingsManager $settingsManager;
  protected BundleInfoService $bundleInfoService;


  /**
   * Constructs a ValidationObjectFactory.
   *
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver.
   * @param RelationshipFieldManager $relationFieldManager
   *   The field configurator.
   * @param BundleSettingsManager $settingsManager
   *   The settings manager.
   * @param BundleInfoService $bundleInfoService
   *   The bundle info service.
   */
  public function __construct(
    FieldNameResolver $fieldNameResolver,
    RelationshipFieldManager $relationFieldManager,
    BundleSettingsManager $settingsManager,
    BundleInfoService $bundleInfoService
  ) {
    $this->fieldNameResolver = $fieldNameResolver;
    $this->relationFieldManager = $relationFieldManager;
    $this->settingsManager = $settingsManager;
    $this->bundleInfoService = $bundleInfoService;
  }


  /**
   * Creates validation object from a bundle entity.
   *
   * @param ConfigEntityBundleBase $entity
   *   The bundle entity.
   *
   * @return BundleValidator
   *   The validation object.
   */
  public function fromEntity(ConfigEntityBundleBase $entity): BundleValidator {
    return new BundleValidator(
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
   * @return BundleValidator|null
   *   The validation object or NULL.
   */
  public function fromFormState(FormStateInterface $form_state): ?BundleValidator {
    $entity = $form_state->getFormObject()->getEntity();
    if (!$entity instanceof ConfigEntityBundleBase) {
      return null;
    }

    return new BundleValidator(
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
   * @return BundleValidator|null
   *   The validation object or NULL.
   */
  public function fromBundleConfigFile(string $config_name, StorageInterface $storage): ?BundleValidator {
    $config_data = $storage->read($config_name);        
    $relation_settings = !empty($config_data['third_party_settings']['relationship_nodes'])
      ? $config_data['third_party_settings']['relationship_nodes']
      : [];
    if (empty($this->settingsManager->getConfigFileEntityClasses($config_name))) {
      return null;
    }
    $entity_classes = $this->settingsManager->getConfigFileEntityClasses($config_name);

    $entity_type_id = $entity_classes['entity_type_id'];
    return new BundleValidator(
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
   * @return FieldConfigValidator|null
   *   The validation object or NULL.
   */
  public function fromFieldConfig(FieldConfig $field_config): ?FieldConfigValidator {        
    return new FieldConfigValidator(
      $field_config->getName(),
      $field_config->getTargetBundle(),
      $field_config->isRequired(),
      $field_config->getType(),
      $field_config->getSetting('handler_settings')['target_bundles'] ?? null,
      null,
      $this->fieldNameResolver,
      $this->relationFieldManager, 
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
   * @return FieldConfigValidator|null
   *   The validation object or NULL.
   */
  public function fromFieldConfigConfigFile(array $config_data, StorageInterface $storage): ?FieldConfigValidator {
    $target_bundles = empty($config_data['settings']['handler_settings']['target_bundles']) 
      ? null 
      : $config_data['settings']['handler_settings']['target_bundles'];

    return new FieldConfigValidator(
      $config_data['field_name'],
      $config_data['bundle'],
      $config_data['required'],  
      $config_data['field_type'],
      $target_bundles,
      $storage,
      $this->fieldNameResolver,
      $this->relationFieldManager, 
      $this->settingsManager
    );
  }


  /**
   * Creates validation object from field storage.
   *
   * @param FieldStorageConfig $storage
   *   The field storage configuration.
   *
   * @return FieldStorageValidator|null
   *   The validation object or NULL.
   */
  public function fromFieldStorage(FieldStorageConfig $storage): ?FieldStorageValidator {
    return new FieldStorageValidator(
      $storage->getName(),
      $storage->getType(),
      $storage->getCardinality(),
      $storage->getSetting('target_type') ?? null, 
      $this->relationFieldManager 
    );
  }


  /**
   * Creates validation object from field storage configuration file.
   *
   * @param array $config_data
   *   The configuration data.
   *
   * @return FieldStorageValidator|null
   *   The validation object or NULL.
   */
  public function fromFieldStorageConfigFile(array $config_data): ?FieldStorageValidator {
    $target_type = empty($config_data['settings']['target_type']) 
      ? null 
      : $config_data['settings']['target_type'];
        

    return new FieldStorageValidator(
      $config_data['field_name'],
      $config_data['type'],
      $config_data['cardinality'],
      $target_type,
      $this->relationFieldManager 
    );
  }  
}