<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\search_api\Query\Condition;

/**
 * Condition group for nested field queries.
 * 
 * This class extends the standard ConditionGroup to add support for
 * Elasticsearch nested queries by tracking the parent field path.
 */
class NestedSubFieldCondition extends Condition {

  protected ?string $parentFieldName = null;
  protected ?string $subFieldPath = null;
  

  public function getParentFieldName(): ?string {
    return $this->parentFieldName;
  }


  public function setParentFieldName(string $parentFieldName): self {
    $this->parentFieldName = $parentFieldName;
    $this->setSubFieldPath();
    return $this;
  }

  
  public function getSubFieldPath(): ?string {
    return $this->subFieldPath;
  }


  public function setSubFieldPath(string $path = null): self {
    if($path !== null){
      $this->subFieldPath = $path;
    } elseif(!empty($this->parentFieldName) && !empty($this->field)){
      $this->subFieldPath = $this->parentFieldName . '.' . $this->field;
    }
    return $this;
  }


  public function isNestedSubField(): bool {
    return !empty($this->parentFieldName);
  }
}