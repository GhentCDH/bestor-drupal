<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedChildFieldCondition;
use Drupal\relationship_nodes_search\Service\ElasticMappingInspector;

/**
 * Condition group for nested field queries.
 * 
 * This class extends the standard ConditionGroup to add support for
 * Elasticsearch nested queries by tracking the parent field path.
 */
class NestedParentFieldConditionGroup extends ConditionGroup {

  protected ?string $parentFieldName = null;
  protected ?Index $index = null;
  protected ?ElasticMappingInspector $mappingInspector = null;



  public function setIndex(Index $index): self {
    $this->index = $index;
    return $this;
  }


  public function setMappingInspector(ElasticMappingInspector $mappingInspector): self {
    $this->mappingInspector = $mappingInspector;
    return $this;
  }


  public function setParentFieldName(string $parent_fld_nm): self {
    $this->parentFieldName = $parent_fld_nm;
    return $this;
  }


  public function getParentFieldName(): ?string {
    return $this->parentFieldName;
  }


  public function isNestedParentField(): bool {
    return !empty($this->parentFieldName);
  }


  public function addChildFieldCondition(string $child_fld_nm, $value, $operator = '='): self {
    if (!$this->parentFieldName || !$this->index || !$this->mappingInspector) {
      throw new \LogicException('Parent field, index and mapping inspector must be set before adding subfield conditions.');
    }

    $path = $this->mappingInspector->getElasticQueryFieldPath($this->index, $this->parentFieldName, $child_fld_nm);
  
    $condition = new NestedChildFieldCondition($path, $value, $operator);
    $condition->setParentFieldName($this->parentFieldName);
    $condition->setChildFieldName($child_fld_nm);
    
    $this->conditions[] = $condition;
    return $this;
  }
}