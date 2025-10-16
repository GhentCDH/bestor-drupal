<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\search_api\Query\ConditionGroup;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedSubFieldCondition;

/**
 * Condition group for nested field queries.
 * 
 * This class extends the standard ConditionGroup to add support for
 * Elasticsearch nested queries by tracking the parent field path.
 */
class NestedParentFieldConditionGroup extends ConditionGroup {

  protected ?string $parentFieldName = null;


  public function setParentFieldName(string $parentFieldName): self {
    $this->parentFieldName = $parentFieldName;
    return $this;
  }

  public function getParentFieldName(): ?string {
    return $this->parentFieldName;
  }

  public function isNestedParentField(): bool {
    return !empty($this->parentFieldName);
  }

  public function addSubFieldCondition(string $subFieldName, $value, $operator = '=') {
    if (empty($this->parentFieldName)) {
      throw new \LogicException('Parent field name must be set before adding subfield conditions.');
    }

    $condition = new NestedSubFieldCondition($subFieldName, $value, $operator);
    $condition->setParentFieldName($this->parentFieldName);
    
    $this->conditions[] = $condition;
    return $this;
  }
}