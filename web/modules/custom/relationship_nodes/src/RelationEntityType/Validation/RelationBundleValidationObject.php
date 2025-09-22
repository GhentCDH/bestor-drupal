<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;


class RelationBundleValidationObject {

  protected ?string $entity_type_id;
  protected array $rn_settings;
  protected array $dependent_relation_bundles;
  protected FieldNameResolver $fieldNameResolver;
  protected array $errors = [];


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


  public function validate(): bool {
    if(!$this->isRelevantEntityType()){
      // No RN Entity Type, so cannot be invalid
      return true;
    }

    $this->errors = [];

    
    if(empty($this->rn_settings['enabled'])){
      $this->validateDependencies();
    } else {
      $this->validateFieldNameConfig(); 
      $this->validateMirrorType(); 
    }
    return empty($this->errors);
  }


  public function getErrors(): array {
    return $this->errors;
  }

  /*
  * Checks whether the entity type is a valid relation entity type (node_type or taxonomy_vocabulary)
  */
  protected function isRelevantEntityType(): bool {
    if (!in_array($this->entity_type_id, ['node_type', 'taxonomy_vocabulary'], true)) {
      return false;
    }
    return true;
  }


  /*
  * Checks whether the entity type is a valid relation entity type (node_type or taxonomy_vocabulary)
  */
  protected function validateMirrorType(): void {
    if($this->entity_type_id !== 'taxonomy_vocabulary'){
      return;
    }

    $options = ['none', 'entity_reference', 'string'];
    if (!empty($this->rn_settings['referencing_type']) && in_array($this->rn_settings['referencing_type'], $options)) {
      return;
    }

    $this->errors[] = 'invalid_mirror_type';
    return;
  }
  

  /*
  * Checks whether the necessary field names are set in config/install/
  */
  protected function validateFieldNameConfig():void{
    if($this->entity_type_id === 'node_type'){
      if (!$this->validBasicRelationConfig()) {
        $this->errors[] = 'missing_field_name_config';
        return;
      }
      if (!empty($this->rn_settings['typed_relation']) && !$this->validTypedRelationConfig()){
        $this->errors[] = 'missing_field_name_config';
        return;
      }
    } elseif($this->entity_type_id === 'taxonomy_vocabulary'){
      if (!$this->validRelationVocabConfig()){
        $this->errors[] = 'missing_field_name_config';
        return;
      }
    }
  }


  /**
  * Validates whether a relation entity with dependencies gets un-relationed (which is invalid). 
  */
  protected function validateDependencies(): void{  
    if(
      $this->entity_type_id === 'taxonomy_vocabulary' &&
      empty($this->rn_settings['enabled']) &&
      !empty($this->dependent_relation_bundles)
    ){
      $this->errors[] = 'disabled_with_dependencies';
      return;
    }
  }


  /**
  * Validates whether the related entity field names have been filled in the module config (cf /config/install) 
  */
  protected function validBasicRelationConfig(): bool{  
    if(!$this->validChildFieldConfig($this->fieldNameResolver->getRelatedEntityFields(), 'related_entity_fields')){
      return false;
    }
    return true;
  }


  /**
  * Validates whether the related type field name has been filled in the module config (cf /config/install) 
  */
  protected function validTypedRelationConfig(): bool{
    if (empty($this->fieldNameResolver->getRelationTypeField()) || !$this->validRelationVocabConfig()) {
      return false;
    }  
    return true;
  }


  /**
  * Validates whether the mirror field names have been filled in the module config (cf /config/install) 
  */
  protected function validRelationVocabConfig():bool{
    if(!$this->validChildFieldConfig($this->fieldNameResolver->getMirrorFields(), 'mirror_fields')){
      return false;
    }
    return true;
  }


  /**
  * Helper function to check arrays of (sub)fields in the module config (cf /config/install) 
  */
  protected function validChildFieldConfig(array $array, string $parent_key): bool {
    if (!is_array($array)) {
      return false;
    }
    $subfields = $this->fieldNameResolver->getConfig()->get($parent_key);
    if (empty($subfields) || !is_array($subfields)) {
      return false;
    }

    foreach (array_keys($subfields) as $subfield) {
      if (!array_key_exists($subfield, $array) || empty($array[$subfield])) {
        return false;
      }
    }
    return true;
  }
}