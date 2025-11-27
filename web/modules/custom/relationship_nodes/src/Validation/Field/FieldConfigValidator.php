<?php

namespace Drupal\relationship_nodes\Validation\Field;

use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationBundle\Settings\BundleSettingsManager;
use Drupal\relationship_nodes\RelationField\RelationshipFieldManager;
use Drupal\Core\Config\StorageInterface;


/**
 * Validation object for relationship field configuration.
 *
 * Validates field configurations for relationship node fields.
 */
class FieldConfigValidator {

  protected string $fieldName;
  protected string $bundle;
  protected bool $required;
  protected string $fieldType;
  protected ?array $targetBundles;
  protected ?StorageInterface $storage;
  protected FieldNameResolver $fieldNameResolver;
  protected RelationshipFieldManager $relationFieldManager;
  protected BundleSettingsManager $settingsManager;
  protected array $errors = [];


  /**
   * Constructs a FieldConfigValidator.
   *
   * @param string $fieldName
   *   The field name.
   * @param string $bundle
   *   The bundle name.
   * @param bool $required
   *   Whether the field is required.
   * @param string $fieldType
   *   The field type.
   * @param array|null $targetBundles
   *   Target bundles for entity reference fields.
   * @param StorageInterface|null $storage
   *   The configuration storage.
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver.
   * @param RelationshipFieldManager $relationFieldManager
   *   The field configurator.
   * @param BundleSettingsManager $settingsManager
   *   The settings manager.
   */
  public function __construct(
    string $fieldName,
    string $bundle,
    bool $required,
    string $fieldType,
    ?array $targetBundles = null,
    ?StorageInterface $storage,
    FieldNameResolver $fieldNameResolver,
    RelationshipFieldManager $relationFieldManager,
    BundleSettingsManager $settingsManager
  ) {
    $this->fieldName = $fieldName;
    $this->bundle = $bundle;
    $this->required = $required;
    $this->fieldType = $fieldType;
    $this->targetBundles = $targetBundles;
    $this->storage = $storage;
    $this->fieldNameResolver = $fieldNameResolver;
    $this->relationFieldManager = $relationFieldManager;
    $this->settingsManager = $settingsManager;
  }


  /**
   * Validates the field configuration.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function validate(): bool {
    $required_settings = $this->fieldConfigurator->getRequiredFieldConfiguration($this->fieldName);

    if (!$required_settings) {
      // Not a RN field, no validation required. 
      return TRUE;
    }

    $this->validateTargetBundles();
    $this->validateFieldRequired();
    $this->validateFieldType($required_settings);
    $this->validateSelfReferencingMirrorField();
    if ($required_settings['type'] === 'entity_reference') {
      $this->validateRelationVocabTarget();
    }
    return empty($this->errors);
  }


  /**
   * Gets validation errors.
   *
   * @return array
   *   Array of error codes.
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Validates target bundles configuration.
   */
  protected function validateTargetBundles(): void {
    if (!empty($this->targetBundles) && count($this->targetBundles) !== 1) {
      $this->errors[] = 'multiple_target_bundles';
    }
  }


  /**
   * Validates that field is not required.
   */
  protected function validateFieldRequired(): void {
    if ($this->required) {
      $this->errors[] = 'field_cannot_be_required';
    }
  }
  

  /**
   * Validates self-referencing mirror field configuration.
   */
  protected function validateSelfReferencingMirrorField(): void {
    if ($this->fieldName === $this->fieldNameResolver->getMirrorFields('entity_reference')) {
      if (!empty($this->targetBundles) && key($this->targetBundles) !== $this->bundle) {
        $this->errors[] = 'mirror_field_bundle_mismatch';
      }
    }
  }        
  

  /**
   * Validates relation vocabulary target for relation type fields.
   */
  protected function validateRelationVocabTarget(): void {
    if ($this->fieldName === $this->fieldNameResolver->getRelationTypeField()) {
      if (empty($this->targetBundles)) {
        $this->errors[] = 'relation_type_field_no_targets';
        return;
      }
      // werkt niet bij configimport...
      foreach ($this->targetBundles as $vocab_name => $vocab_label) {
        if (empty($this->storage) && $this->settingsManager->isRelationVocab($vocab_name)) {
          continue;
        } elseif (!empty($this->storage)) {
          $config_data = $this->storage->read('taxonomy.vocabulary.' . $vocab_name);

          if (!empty($config_data) && $this->settingsManager->isCimRelationEntity($config_data)) {
            continue;
          }
        }
        $this->errors[] = 'invalid_relation_vocabulary';
        break;
      }
    }
  }

  
  /**
   * Validates field type matches required configuration.
   *
   * @param array $required_settings
   *   Required field settings.
   */
  protected function validateFieldType(array $required_settings): void {
    if ($this->fieldType !== $required_settings['type']) {
      $this->errors[] = 'invalid_field_type';
    }
  }
}