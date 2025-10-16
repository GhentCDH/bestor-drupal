<?php

namespace Drupal\relationship_nodes_search\Processor;

use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;


class RelationProcessorProperty extends ProcessorProperty implements ComplexDataDefinitionInterface{

  protected ?array $propertyDefinitions = NULL;
  protected ?array $drupalFieldInfo = NULL;


  public function __construct(array $definition) {
    parent::__construct($definition);
  }

 
  public function getPropertyDefinitions():array {
    if ($this->propertyDefinitions !== NULL) {
      return $this->propertyDefinitions;
    }

    $this->propertyDefinitions = [];
    $this->drupalFieldInfo = [];

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


      $drupal_type = $field_definition->getType();
      $search_api_type = $this->mapFieldTypeToSearchApiType($drupal_type);


      $label = $this->convertToString($field_definition->getLabel());
      $description = $this->convertToString($field_definition->getDescription());

      $property = DataDefinition::create($search_api_type)
        ->setLabel($label)
        ->setDescription($description);

      $this->propertyDefinitions[$field_name] = $property;

      $this->drupalFieldInfo[$field_name] = [
        'machine_name' => $field_name,
        'type' => $drupal_type,
      ];
      
      if ($drupal_type === 'entity_reference') {
        $settings = $field_definition->getSettings();
        $target_type = $settings['target_type'] ?? 'node';
        $this->drupalFieldInfo[$field_name]['target_type'] = $target_type;  
      }
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


  public function getDrupalFieldInfo(string $field_name): ?array {
    // Ensure properties are loaded
    if ($this->drupalFieldInfo === NULL) {
      $this->getPropertyDefinitions();
    }
    
    return $this->drupalFieldInfo[$field_name] ?? NULL;
  }


  public function buildNestedFieldsConfig(array $selected_fields): array {
    $config = [];
    $definitions = $this->getPropertyDefinitions();
    foreach ($selected_fields as $field_name) {
      if (!isset($definitions[$field_name])) {
        continue;
      }
      
      $definition = $definitions[$field_name];
      $config[$field_name] = [
        'type' => $definition->getDataType(),
        'label' => $this->convertToString($definition->getLabel()),
      ];

      $drupal_field_info = $this->getDrupalFieldInfo($field_name);
      if ($drupal_field_info) {
        $config[$field_name]['drupal_field'] = $drupal_field_info;
      }
    }
    return $config;
  }


  protected function mapFieldTypeToSearchApiType(string $drupal_type): string {
    $type_map = [
      'entity_reference' => 'string',
      'integer' => 'integer',
      'decimal' => 'decimal',
      'float' => 'decimal',
      'boolean' => 'boolean',
      'datetime' => 'date',
      'timestamp' => 'date',
      'string' => 'string',
      'string_long' => 'text',
      'text' => 'text',
      'text_long' => 'text',
      'text_with_summary' => 'text',
      'list_string' => 'string',
      'list_integer' => 'integer',
      'list_float' => 'decimal',
    ];
    
    return $type_map[$drupal_type] ?? 'string';
  }


  protected function convertToString($value): string {
    if (is_object($value) && method_exists($value, '__toString')) {
      return (string) $value;
    }
    return is_string($value) ? $value : '';
  }

}