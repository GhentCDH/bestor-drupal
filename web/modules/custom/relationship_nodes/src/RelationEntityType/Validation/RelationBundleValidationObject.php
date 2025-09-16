<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\Core\Config\StorageInterface;
use Drupal\field\FieldConfigStorage;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;


class RelationBundleValidationObject {

  protected string $entity_type_id;
  protected array $rn_settings;
  protected StorageInterface|FieldConfigStorage $fieldStorage;
  protected FieldNameResolver $fieldNameResolver;
  protected array $errors = [];


  public function __construct(
    string $entity_type_id,
    array $rn_settings,
    StorageInterface|FieldConfigStorage  $fieldStorage,
    FieldNameResolver $fieldNameResolver,
  ) {
    $this->entity_type_id = $entity_type_id;
    $this->rn_settings = $rn_settings;
    $this->fieldStorage = $fieldStorage;
    $this->fieldNameResolver = $fieldNameResolver;
  }


  public function validate(): bool {
    if(empty($this->rn_settings['enabled'])){
      // No relation entity, so cannot be misconfigured
      return true;
    }

    $this->errors = [];

    $this->validateInput();
    $this->validateFieldNameConfig();  
    return empty($this->errors);
  }


  public function getErrors(): array {
    return $this->errors;
  }

  /*
  * Checks whether the entity type is a valid relation entity type (node_type or taxonomy_vocabulary)
  */
  protected function validateInput(): void {
    if (!in_array($this->entity_type_id, ['node_type', 'taxonomy_vocabulary'], true)) {
      $this->errors[] = 'invalid_entity_type';
    }
  }


  /*
  * Checks whether the necessary field names are set in config/install/
  */
  protected function validateFieldNameConfig():void{
    if( $this->entity_type_id === 'node_type'){
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