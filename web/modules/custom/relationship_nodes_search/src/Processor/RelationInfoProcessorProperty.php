<?php

namespace Drupal\relationship_nodes_search\Processor;

use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\relationship_nodes_search\TypedData\RelationInfoDefinition;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;

class RelationInfoProcessorProperty extends ProcessorProperty implements ComplexDataDefinitionInterface{

  protected $relationInfoDefinition;

  public function __construct(array $definition) {
    parent::__construct($definition);

    $bundle = $definition['definition_class_settings']['bundle'] ?? NULL;
     
    $this->relationInfoDefinition = new RelationInfoDefinition(['bundle' => $bundle]);
  }

  public function getProcessorId() {
    return $this->definition['processor_id'];
  }

  public function getPropertyDefinitions() {
    return $this->relationInfoDefinition->getPropertyDefinitions();
  }

  public function getMainPropertyName() {
    //return $this->fieldDefinition->getFieldStorageDefinition()->getMainPropertyName();
    return;
  }

  public function getPropertyDefinition($name) {
  $definitions = $this->getPropertyDefinitions();
  return $definitions[$name] ?? NULL;
}
/*
addConsstraint moet ook gekopieerd worden als dit wordt behouden vanuit https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21TypedData%21EntityDataDefinition.php/class/EntityDataDefinition/8.9.x
  public function setEntityTypeId($entity_type_id) {
    return $this->addConstraint('EntityType', $entity_type_id);
  }
    */
}
