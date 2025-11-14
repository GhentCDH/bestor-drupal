<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\search_api\Query\Condition;

/**
 * Condition group for nested field queries.
 * 
 * This class extends the standard ConditionGroup to add support for
 * Elasticsearch nested queries by tracking the parent field path.
 */
class NestedChildFieldCondition extends Condition {

  protected ?string $parentFieldName = null;
  protected ?string $childFieldName = null;
  

  public function getParentFieldName(): ?string {
    return $this->parentFieldName;
  }


  public function setParentFieldName(string $parentFieldName): self {
    $this->parentFieldName = $parentFieldName;
    return $this;
  }

  
  public function getChildFieldName(): ?string {
    return $this->childFieldName;
  }


  public function setChildFieldName(string $childFieldName): self {
    $this->childFieldName = $childFieldName;
    return $this;
  }


  public function isNestedChildField(): bool {
    return !empty($this->parentFieldName) && !empty($this->childFieldName);
  }
}