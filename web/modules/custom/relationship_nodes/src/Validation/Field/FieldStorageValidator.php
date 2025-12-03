<?php

namespace Drupal\relationship_nodes\Validation\Field;

use Drupal\relationship_nodes\RelationField\RelationshipFieldManager;


/**
 * Validation object for relationship field storage configuration.
 *
 * Validates field storage settings for relationship node fields.
 */
class FieldStorageValidator {

  protected string $fieldName;
  protected string $fieldType;
  protected int $cardinality;
  protected ?string $targetType;
  protected RelationshipFieldManager $relationFieldManager;
  protected array $errors = [];


  /**
   * Constructs a FieldStorageValidator.
   *
   * @param string $fieldName
   *   The field name.
   * @param string $fieldType
   *   The field type.
   * @param int $cardinality
   *   The field cardinality.
   * @param string|null $targetType
   *   The target entity type.
   * @param RelationshipFieldManager $relationFieldManager
   *   The field configurator.
   */
  public function __construct(
    string $fieldName,
    string $fieldType,
    int $cardinality,
    ?string $targetType,
    RelationshipFieldManager $relationFieldManager
  ) {
    $this->fieldName = $fieldName;
    $this->fieldType = $fieldType;
    $this->cardinality = $cardinality;
    $this->targetType = $targetType;
    $this->relationFieldManager = $relationFieldManager;
  }


  /**
   * Validates the field storage configuration.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function validate(): bool {
    $this->errors = [];

    $required_settings = $this->relationFieldManager->getRequiredFieldConfiguration($this->fieldName);

    if (!$required_settings) {
      // Not a RN field, no validation required. 
      return true;
    }

    $this->validateFieldType($required_settings);

    $this->validateCardinality($required_settings);

    $this->validateTargetType($required_settings);

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


  /**
   * Validates cardinality matches required configuration.
   *
   * @param array $required_settings
   *   Required field settings.
   */
  protected function validateCardinality(array $required_settings): void {
    if ($this->cardinality != $required_settings['cardinality']) {
      $this->errors[] = 'invalid_cardinality';
    }
  }


  /**
   * Validates target type matches required configuration.
   *
   * @param array $required_settings
   *   Required field settings.
   */
  protected function validateTargetType(array $required_settings): void {
    if (isset($required_settings['target_type']) && $this->targetType != $required_settings['target_type']) {
      $this->errors[] = 'invalid_target_type';
    }
  }
}