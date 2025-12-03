<?php

namespace Drupal\relationship_nodes\Validation;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationField\RelationshipFieldManager;
use Drupal\relationship_nodes\RelationBundle\Settings\BundleSettingsManager;
use Drupal\relationship_nodes\RelationBundle\BundleInfoService;
use Drupal\relationship_nodes\Validation\ValidationObjectFactory;
use Drupal\relationship_nodes\Validation\Bundle\BundleValidator;
use Drupal\relationship_nodes\Validation\Field\FieldConfigValidator;
use Drupal\relationship_nodes\Validation\Field\FieldStorageValidator;
use Drupal\relationship_nodes\Validation\ValidationResultFormatter;


/**
 * Service for validating relationship nodes configuration.
 *
 * Provides comprehensive validation for bundles, fields, and entire
 * relationship node configurations.
 */
class ValidationService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FieldNameResolver $fieldNameResolver;
  protected RelationshipFieldManager $relationFieldManager;
  protected BundleInfoService $bundleInfoService;
  protected BundleSettingsManager $settingsManager;
  protected ValidationObjectFactory $validationFactory;
  protected ValidationResultFormatter $errorFormatter;


  /**
   * Constructs a ValidationService.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver.
   * @param RelationshipFieldManager $relationFieldManager
   *   The field configurator.
   * @param BundleInfoService $bundleInfoService
   *   The bundle info service.
   * @param BundleSettingsManager $settingsManager
   *   The settings manager.
   * @param ValidationObjectFactory $validationFactory
   *   The validation object factory.
   * @param ValidationResultFormatter $errorFormatter
   *   The error formatter.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    FieldNameResolver $fieldNameResolver,
    RelationshipFieldManager $relationFieldManager,
    BundleInfoService $bundleInfoService,
    BundleSettingsManager $settingsManager,
    ValidationObjectFactory $validationFactory,
    ValidationResultFormatter $errorFormatter
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldNameResolver = $fieldNameResolver;
    $this->relationFieldManager = $relationFieldManager;
    $this->bundleInfoService = $bundleInfoService;
    $this->settingsManager = $settingsManager;
    $this->validationFactory = $validationFactory;
    $this->errorFormatter = $errorFormatter;
  }


  /**
   * Gets validation errors for a bundle entity.
   *
   * @param ConfigEntityBundleBase $entity
   *   The bundle entity.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function getBundleValidationErrors(ConfigEntityBundleBase $entity): array {
    $errors = [];
    $validator = $this->validationFactory->fromEntity($entity);
    if (!$validator instanceof BundleValidator) {
      return [];
    }
    if (!$validator->validate()) {
      foreach ($validator->getErrors() as $error_code) {
        $errors[] = [
          'error_code' => $error_code,
          'context' => [
            '@bundle' => $entity->id()
          ]
        ];
      }
    }

    $field_errors = $this->validateEntityExistingFields($entity);
    return array_merge($errors, $field_errors);
  }
  
  
  /**
   * Gets validation errors from form state.
   *
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function getFormStateValidationErrors(FormStateInterface $form_state): array {
    $errors = [];

    $validator = $this->validationFactory->fromFormState($form_state);
    if (!$validator instanceof BundleValidator) {
      return [];
    }

    $entity = $form_state->getFormObject()->getEntity();
    if (!$validator->validate()) {
      foreach ($validator->getErrors() as $error_code) {
        $errors[] = [
          'error_code' => $error_code,
          'context' => [
            '@bundle' => $entity->id()
          ]
        ];
      }
    }    
    $rn_settings = $form_state->getValue('relationship_nodes') ?? null;
    $field_errors = $this->validateEntityExistingFields($entity, $rn_settings);
    return array_merge($errors, $field_errors);
  }


  /**
   * Displays form state validation errors.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function displayFormStateValidationErrors(array &$form, FormStateInterface $form_state): void {
    $errors = $this->getFormStateValidationErrors($form_state);
    if (empty($errors)) {
      return;
    }

    $error_message = $this->errorFormatter->formatValidationErrors($form_state->getFormObject()->getEntity()->id(), $errors);
    $form_state->setErrorByName('relationship_nodes', $error_message);
  }


  /**
   * Gets validation errors for a field storage.
   *
   * @param FieldStorageConfig $storage
   *   The field storage configuration.
   *
   * @return array
   *   Array of validation errors.
   */
  public function getFieldStorageValidationErrors(FieldStorageConfig $storage): array {
    $errors = [];
    $validator = $this->validationFactory->fromFieldStorage($storage);
    if (!$validator instanceof FieldStorageValidator) {
      return [];
    }
    if (!$validator->validate()) {
      foreach ($validator->getErrors() as $error_code) {
        $errors[] = [
          'error_code' => $error_code,
          'context' => [
            '@field' =>  $storage->getName(),
          ]
        ];
      }
    } 
    return $errors;   
  }


  /**
   * Gets validation errors for a field configuration.
   *
   * @param FieldConfig $field_config
   *   The field configuration.
   * @param bool $include_storage_validation
   *   Whether to include storage validation.
   *
   * @return array
   *   Array of validation errors.
   */
  public function getFieldConfigValidationErrors(FieldConfig $field_config, bool $include_storage_validation = true): array {
    $errors = []; 
    $context = [
      '@field' =>  $field_config->getName(),
      '@bundle' => $field_config->getTargetBundle()
    ];
    if ($include_storage_validation == true) {
      $storage = $field_config->getFieldStorageDefinition();
      if (!$storage instanceof FieldStorageConfig) {
        $errors[] = [
          'error_code' => 'no_field_storage',
          'context' => $context
        ];
        return $errors;
      }
      $storage_errors = $this->getFieldStorageValidationErrors($storage);
      if (!empty($storage_errors)) {
        $errors = $storage_errors;
      }
    }

    $validator = $this->validationFactory->fromFieldConfig($field_config);
    if (!$validator instanceof FieldConfigValidator) {
      return $errors;
    }
    if (!$validator->validate()) {
      foreach ($validator->getErrors() as $error_code) {
        $errors[] = [
          'error_code' => $error_code,
          'context' => $context 
        ];
      }
    }   
    
    return $errors;
  }


  /**
   * Validates existing fields for an entity.
   *
   * @param ConfigEntityBundleBase $entity
   *   The bundle entity.
   * @param array|null $rn_settings
   *   Optional relationship nodes settings.
   *
   * @return array
   *   Array of validation errors.
   */
  protected function validateEntityExistingFields(ConfigEntityBundleBase $entity, ?array $rn_settings = null): array {
    $errors = [];
    $existing_fields = $this->relationFieldManager->getBundleFieldsStatus($entity, $rn_settings)['existing'];
    foreach ($existing_fields as $field => $field_info) {
      if (!isset($field_info['field_config'])) {
        $errors[] = [
          'error_code' => 'missing_field_config',
          'context' => [
            '@field' => $field,
            '@bundle' => $entity->id()
          ]
        ];
        continue;
      }
      $field_errors = $this->getFieldConfigValidationErrors($field_info['field_config']);
      if (!empty($field_errors)) {
        $errors = array_merge($errors, $field_errors);
      }
    }
    return $errors; 
  }


  /**
   * Validates all relation bundles.
   *
   * @return array
   *   Array of all validation errors.
   */
  protected function validateAllRelationBundles(): array {
    $all_errors = [];
    
    foreach ($this->bundleInfoService->getAllRelationBundles() as $bundle_name => $entity) {
      $errors = $this->getBundleValidationErrors($entity);
      if (!empty($errors)) {
        $all_errors = array_merge($all_errors, $errors);
      }
    }
    return $all_errors;
  }


  /**
   * Validates all relation fields.
   *
   * @return array
   *   Array of all validation errors.
   */
  protected function validateAllRelationFields(): array {
    $all_errors = [];
    $rn_fields = $this->relationFieldManager->getAllRnCreatedFields();
    $relation_field_names = $this->fieldNameResolver->getAllRelationFieldNames();
    foreach ($rn_fields as $field_id => $field) {
      $field_config = false;
      $field_name = $field->getName();
      if ($field instanceof FieldStorageConfig) {
        $storage_errors = $this->getFieldStorageValidationErrors($field);
        if (!empty($storage_errors)) {
          $all_errors = array_merge($all_errors, $storage_errors);
        }   
      } elseif ($field instanceof FieldConfig) {
        $field_config = true;
        $config_errors = $this->getFieldConfigValidationErrors($field);
        if (!empty($config_errors)) {
          $all_errors = array_merge($all_errors, $config_errors);
        }       
      }
      if (!in_array($field_name, $relation_field_names)) {
        $context = ['@field' => $field_name];
        if ($field_config) {
          $context['@bundle'] = $field->getTargetBundle();
        }
        $all_errors[] = [
          'error_code' => 'orphaned_rn_field_settings',
          'context' => $context
        ];
      }
    }
    return $all_errors;
  }


  /**
   * Validates all relation configuration.
   *
   * @return array
   *   Array of all validation errors.
   */
  public function validateAllRelationConfig(): array {      
    return array_merge(
      // Validate bundles config and existing fields related to this bundle
      $this->validateAllRelationBundles(), 
      // Validate all fields marked as created by this module (find orphaned fields)
      $this->validateAllRelationFields()
    );
  }   
}    