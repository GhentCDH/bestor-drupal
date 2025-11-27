<?php

namespace Drupal\relationship_nodes\Validation\Bundle;

use Drupal\relationship_nodes\RelationField\FieldNameResolver;


/**
 * Validation object for relationship bundle configuration.
 *
 * Validates node types and vocabularies configured as relationship entities.
 */
class BundleValidator {

  protected ?string $entity_type_id;
  protected array $rn_settings;
  protected array $dependent_relation_bundles;
  protected FieldNameResolver $fieldNameResolver;
  protected array $errors = [];


  /**
   * Constructs a BundleValidator.
   *
   * @param string|null $entity_type_id
   *   The entity type ID.
   * @param array $rn_settings
   *   The relationship nodes settings.
   * @param array $dependent_relation_bundles
   *   Array of dependent relation bundles.
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver.
   */
  public function __construct(
    ?string $entity_type_id,
    array $rn_settings,
    array $dependent_relation_bundles,
    FieldNameResolver $fieldNameResolver,
  ) {
    $this->entity_type_id = $entity_type_id;
    $this->rn_settings = $rn_settings;
    $this->dependent_relation_bundles = $dependent_relation_bundles;
    $this->fieldNameResolver = $fieldNameResolver;
  }


  /**
   * Validates the bundle configuration.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function validate(): bool {
    if (!$this->isRelevantEntityType()) {
      // No RN Entity Type, so cannot be invalid
      return TRUE;
    }

    $this->errors = [];
    
    if (empty($this->rn_settings['enabled'])) {
      $this->validateDependencies();
    } else {
      $this->validateFieldNameConfig(); 
      $this->validateMirrorType(); 
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
   * Checks if the entity type is relevant for validation.
   *
   * @return bool
   *   TRUE if relevant entity type, FALSE otherwise.
   */
  protected function isRelevantEntityType(): bool {
    if (!in_array($this->entity_type_id, ['node_type', 'taxonomy_vocabulary'], TRUE)) {
      return FALSE;
    }
    return TRUE;
  }


  /**
   * Validates mirror type setting for vocabularies.
   */
  protected function validateMirrorType(): void {
    if ($this->entity_type_id !== 'taxonomy_vocabulary') {
      return;
    }

    $options = ['none', 'entity_reference', 'string'];
    if (!empty($this->rn_settings['referencing_type']) && in_array($this->rn_settings['referencing_type'], $options)) {
      return;
    }

    $this->errors[] = 'invalid_mirror_type';
    return;
  }
  

  /**
   * Validates field name configuration.
   */
  protected function validateFieldNameConfig(): void {
    if ($this->entity_type_id === 'node_type') {
      if (!$this->validBasicRelationConfig()) {
        $this->errors[] = 'missing_field_name_config';
        return;
      }
      if (!empty($this->rn_settings['typed_relation']) && !$this->validTypedRelationConfig()) {
        $this->errors[] = 'missing_field_name_config';
        return;
      }
    } elseif ($this->entity_type_id === 'taxonomy_vocabulary') {
      if (!$this->validRelationVocabConfig()) {
        $this->errors[] = 'missing_field_name_config';
        return;
      }
    }
  }


  /**
   * Validates dependencies when disabling relationship nodes.
   */
  protected function validateDependencies(): void {  
    if (
      $this->entity_type_id === 'taxonomy_vocabulary' &&
      empty($this->rn_settings['enabled']) &&
      !empty($this->dependent_relation_bundles)
    ) {
      $this->errors[] = 'disabled_with_dependencies';
      return;
    }
  }


  /**
   * Validates basic relation configuration for node types.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validBasicRelationConfig(): bool {  
    if (!$this->validChildFieldConfig($this->fieldNameResolver->getRelatedEntityFields(), 'related_entity_fields')) {
      return FALSE;
    }
    return TRUE;
  }


  /**
   * Validates typed relation configuration.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validTypedRelationConfig(): bool {
    if (empty($this->fieldNameResolver->getRelationTypeField()) || !$this->validRelationVocabConfig()) {
      return FALSE;
    }  
    return TRUE;
  }


  /**
   * Validates relation vocabulary configuration.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validRelationVocabConfig(): bool {
    if (!$this->validChildFieldConfig($this->fieldNameResolver->getMirrorFields(), 'mirror_fields')) {
      return FALSE;
    }
    return TRUE;
  }


  /**
   * Validates child field configuration.
   *
   * @param array $array
   *   Array of field values.
   * @param string $parent_key
   *   The parent configuration key.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validChildFieldConfig(array $array, string $parent_key): bool {
    if (!is_array($array)) {
      return FALSE;
    }
    $subfields = $this->fieldNameResolver->getConfig($parent_key);
    if (empty($subfields) || !is_array($subfields)) {
      return FALSE;
    }

    foreach (array_keys($subfields) as $subfield) {
      if (!array_key_exists($subfield, $array) || empty($array[$subfield])) {
        return FALSE;
      }
    }
    return TRUE;
  }
}