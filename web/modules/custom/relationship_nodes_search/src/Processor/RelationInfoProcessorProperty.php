<?php

namespace Drupal\relationship_nodes_search\Processor;

use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\relationship_nodes_search\TypedData\RelationInfoDefinition;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;

class RelationInfoProcessorProperty extends ProcessorProperty implements ComplexDataDefinitionInterface {

  protected $relationInfoDefinition;

  public function __construct(array $definition) {
    parent::__construct($definition);

    $bundle = $definition['definition_class_settings']['bundle'] ?? NULL;
     
    $this->relationInfoDefinition = new RelationInfoDefinition(['bundle' => $bundle]);
  }

  public function getPropertyDefinitions() {
    return $this->relationInfoDefinition->getPropertyDefinitions();
  }

    public function getMainPropertyName() {
    return NULL;
  }

  public function getPropertyDefinition($name) {
  $definitions = $this->getPropertyDefinitions();
  return $definitions[$name] ?? NULL;
}
}
