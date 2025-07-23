<?php

namespace Drupal\relationship_nodes_search\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use ArrayIterator;

class RelationInfoData extends TypedData implements \IteratorAggregate, \Drupal\Core\TypedData\ComplexDataInterface {

  /**
   * The data definition.
   *
   * @var \Drupal\Core\TypedData\ComplexDataDefinitionInterface|null
   */
  protected $definition;

  /**
   * The array of property data objects.
   *
   * @var \Drupal\Core\TypedData\TypedDataInterface[]
   */
  protected $properties = [];

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * Constructs a RelationInfoData object.
   *
   * @param array $values
   *   An array of property values.
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface|null $definition
   *   The complex data definition.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface|null $typed_data_manager
   *   The typed data manager.
   */
  public function __construct(array $values = [], ?ComplexDataDefinitionInterface $definition = NULL, ?TypedDataManagerInterface $typed_data_manager = NULL) { // aangepast
    $this->definition = $definition;
    $this->typedDataManager = $typed_data_manager;

    if (!$this->typedDataManager) {
      $this->typedDataManager = \Drupal::typedDataManager();
    }

    foreach ($values as $name => $value) {
      $property_definition = $definition ? $definition->getPropertyDefinition($name) : NULL;

      if (is_array($value) && $property_definition && $property_definition->getDataType() === 'complex') {
        $value = $this->typedDataManager->create($property_definition->getDataType(), $value, $property_definition);
      }
      $this->set($name, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    if (!isset($this->properties[$property_name])) {
      throw new \InvalidArgumentException("Property '$property_name' does not exist.");
    }
    return $this->properties[$property_name];
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value, $notify = TRUE) {
    // Als het value een array is, probeer dan een typed data object te maken (net als in constructor).
    if (is_array($value)) {
      $property_definition = $this->definition ? $this->definition->getPropertyDefinition($property_name) : NULL;
      if ($property_definition && $property_definition->getDataType() === 'complex') {
        $value = $this->typedDataManager->create($property_definition->getDataType(), $value, $property_definition);
      }
    }
    $this->properties[$property_name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = FALSE) {
    return $this->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $values = [];
    foreach ($this->properties as $name => $property) {
      if ($property instanceof TypedDataInterface) {
        $values[$name] = $property->getValue();
      }
      else {
        $values[$name] = $property;
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    if (!is_array($value)) {
      throw new \InvalidArgumentException('Expected an array for setValue().');
    }

    foreach ($value as $name => $val) {
      $this->set($name, $val, $notify);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    foreach ($this->properties as $property) {
      if ($property instanceof TypedDataInterface && $property->getValue() !== NULL) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable {
    return new ArrayIterator($this->properties);
  }

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    foreach ($this->properties as $property) {
      if ($property instanceof TypedDataInterface) {
        $property->applyDefaultValue(FALSE);
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($name) {
    // no-op
  }

  public function toArray() {
    $values = [];
    foreach ($this->getProperties() as $name => $property) {
      $values[$name] = $property instanceof TypedDataInterface ? $property->getValue() : $property;
    }
    return $values;
  }
}
