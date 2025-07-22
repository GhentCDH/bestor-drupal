<?php

namespace Drupal\relationship_nodes_search\TypedData;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\ComplexData;


class RelationInfoData extends ComplexData implements ComplexDataInterface {

  /**
   * @param mixed $values
   * @param array $definition
   */
  public function __construct($values, array $definition) {
    parent::__construct($values, $definition);
  }

  /**
   * @param string $name
   * @return mixed
   */
  public function getPropertyValue($name) {
    $values = $this->getValue();

    if (is_array($values) && array_key_exists($name, $values)) {
      return $values[$name];
    }

    return NULL;
  }

  /**
   * @param string $name
   * @param mixed $value
   */
  public function setPropertyValue($name, $value) {
    $values = $this->getValue();
    if (!is_array($values)) {
      $values = [];
    }
    $values[$name] = $value;
    $this->setValue($values);
  }
}
