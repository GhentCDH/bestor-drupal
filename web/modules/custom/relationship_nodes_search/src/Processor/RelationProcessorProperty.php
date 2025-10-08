<?php

namespace Drupal\relationship_nodes_search\Processor;

use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;


class RelationProcessorProperty extends ProcessorProperty implements ComplexDataDefinitionInterface{


  protected ?array $propertyDefinitions = NULL;


  public function __construct(array $definition) {
    parent::__construct($definition);
  }

 
  public function getPropertyDefinitions():array {
    if ($this->propertyDefinitions !== NULL) {
      return $this->propertyDefinitions;
    }

    $this->propertyDefinitions = [];
    $bundle = $this->getBundle();

    if (!$bundle) {
      return $this->propertyDefinitions;
    }

    $entity_field_manager = \Drupal::service('entity_field.manager');
    try {
      $field_definitions = $entity_field_manager->getFieldDefinitions('node', $bundle);
    }
    catch (\Exception $e) {
      \Drupal::logger('relationship_nodes_search')->error('Error loading field definitions: @message', [
        '@message' => $e->getMessage(),
      ]);
      return $this->propertyDefinitions;
    }

    foreach ($field_definitions as $field_name => $field_definition) {
      if (!$field_definition instanceof FieldConfig) {
        continue;
      }

      $label = $field_definition->getLabel();
      $description = $field_definition->getDescription();

      if (is_object($label) && method_exists($label, '__toString')) {
        $label = (string) $label;
      }
      if (is_object($description) && method_exists($description, '__toString')) {
        $description = (string) $description;
      }

      $property = DataDefinition::create('string')
        ->setLabel($label)
        ->setDescription($description);

      $this->propertyDefinitions[$field_name] = $property;
    }

    return $this->propertyDefinitions;
  }


  public function getPropertyDefinition($name):?DataDefinitionInterface {
    $definitions = $this->getPropertyDefinitions();
    return $definitions[$name] ?? NULL;
  }


  public function getMainPropertyName():?string {
    return NULL;
  }


  public function getProcessorId():?string {
    return $this->definition['processor_id'] ?? NULL;
  }


  public function getBundle():?string {
    return $this->definition['definition_class_settings']['bundle'] ?? NULL;
  }


  public function getDataType():string {
    return 'string';
  }


  public function getLabel(): string {
    $label = $this->definition['label'] ?? '';
    if (is_object($label) && method_exists($label, '__toString')) {
      return (string) $label;
    }
    return is_string($label) ? $label : '';
  }


  public function getDescription(): string {
    $description = $this->definition['description'] ?? '';
    if (is_object($description) && method_exists($description, '__toString')) {
      return (string) $description;
    }
    return is_string($description) ? $description : '';
  }
}