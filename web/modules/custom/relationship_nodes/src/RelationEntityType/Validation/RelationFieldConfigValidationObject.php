<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;


class RelationFieldConfigValidationObject {

  protected string $fieldName;
  protected string $bundle;
  protected bool $required;
  protected ?array $targetBundles;
  protected FieldNameResolver $fieldNameResolver;
  protected RelationBundleSettingsManager $settingsManager;
  protected array $errors = [];


  public function __construct(
    string $fieldName,
    string $bundle,
    bool $required,
    ?array $targetBundles = null,
    FieldNameResolver $fieldNameResolver,
    RelationBundleSettingsManager $settingsManager
  ) {
    $this->fieldName = $fieldName;
    $this->bundle = $bundle;
    $this->required = $required;
    $this->targetBundles = $targetBundles;
    $this->fieldNameResolver = $fieldNameResolver;
    $this->settingsManager = $settingsManager;
  }


  public function validate(): bool {      
    $this->validateTargetBundles();
    $this->validateFieldRequired();
    $this->validateSelfReferencingMirrorField();
    $this->validateRelationVocabTarget();

    return empty($this->errors);
  }


  public function getErrors(): array {
    return $this->errors;
  }

  /*
  * All of the module defined fields can only have 0 or 1 target bundles
  */
  protected function validateTargetBundles(): void {
    if (!empty($this->targetBundles) && count($this->targetBundles) !== 1) {
      $this->errors[] = 'multiple_target_bundles';
    }
  }

  /*
  * None of the module defined fieds can be required, as this conflicts with the IEF widget
  */
  protected function validateFieldRequired(): void {
    if ($this->required) {
      $this->errors[] = 'field_cannot_be_required';
    }
  }
  
  /*
  * Mirror fields (cf relation vocabs) of the type entity reference, always have the same vocab as target bundle.
  * (A relation type and its mirror type, are always terms of the same vocab) 
  */
  protected function validateSelfReferencingMirrorField():void {
    if ($this->fieldName === $this->fieldNameResolver->getMirrorFields('entity_reference')) {
      if (!empty($this->targetBundles) && key($this->targetBundles) !== $this->bundle) {
        $this->errors[] = 'mirror_field_bundle_mismatch';
      }
    }
  }        
  

  /*
  * Relation type fields (cf typed relation NodeTypes) must reference a vocab that is marked as a relation vocab. 
  */
  protected function validateRelationVocabTarget(): void {
    if ($this->fieldName === $this->fieldNameResolver->getRelationTypeField()) {
      if (empty($this->targetBundles)) {
        $this->errors[] = 'relation_type_field_no_targets';
        return;
      }
      
      foreach ($this->targetBundles as $vocab_name => $vocab_label) {
        if (!$this->settingsManager->isRelationVocab($vocab_name)) {
          $this->errors[] = 'invalid_relation_vocabulary';
          break;
        }
      }
    }
  }
}