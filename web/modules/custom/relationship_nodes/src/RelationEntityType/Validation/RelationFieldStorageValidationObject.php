<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;


class RelationFieldStorageValidationObject {

  protected string $fieldName;
  protected string $fieldType;
  protected int $cardinality;
  protected ?string $targetType;
  protected RelationFieldConfigurator $configurator;
  protected array $errors = [];


  public function __construct(
    string $fieldName,
    string $fieldType,
    int $cardinality,
    ?string $targetType,
    RelationFieldConfigurator $fieldConfigurator
  ) {
    $this->fieldName = $fieldName;
    $this->fieldType = $fieldType;
    $this->cardinality = $cardinality;
    $this->targetType = $targetType;
    $this->fieldConfigurator = $fieldConfigurator;
  }


  /*
  * Validate if all settings of a field storage match those defined in the field configurator.
  */
  public function validate():bool {
    $this->errors = [];

    $required_settings = $this->fieldConfigurator->getRequiredFieldConfiguration($this->fieldName);

    if(!$required_settings){
      // Not a RN field, no validation required. 
      return true;
    }

    $this->validateFieldType($required_settings);

    $this->validateCardinality($required_settings);

    $this->validateTargetType($required_settings);

    return empty($this->errors);
  }


  public function getErrors(): array {
    return $this->errors;
  }


  protected function validateFieldType(array $required_settings): void {
    if ($this->fieldType !== $required_settings['type']) {
      $this->errors[] = 'invalid_field_type';
    }
  }


  protected function validateCardinality(array $required_settings): void {
    if ($this->cardinality != $required_settings['cardinality']) {
      $this->errors[] = 'invalid_cardinality';
    }
  }


  protected function validateTargetType(array $required_settings): void {
    if (isset($required_settings['target_type']) && $this->targetType != $required_settings['target_type']) {
      $this->errors[] = 'invalid_target_type';
    }
  }
}