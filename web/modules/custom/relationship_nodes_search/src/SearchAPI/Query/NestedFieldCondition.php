<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\search_api\Query\ConditionGroup;

/**
 * Condition group for nested field queries.
 * 
 * This class extends the standard ConditionGroup to add support for
 * Elasticsearch nested queries by tracking the parent field path.
 */
class NestedFieldCondition extends ConditionGroup {

  protected ?string $parentFieldName = null;


  public function setParentFieldName(string $parentFieldName): self {
    $this->parentFieldName = $parentFieldName;
    return $this;
  }

  public function getParentFieldName(): ?string {
    return $this->parentFieldName;
  }

  public function isNestedField(): bool {
    return !empty($this->parentFieldName);
  }
}