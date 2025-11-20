<?php

namespace Drupal\relationship_nodes_search\SearchAPI\Query;

use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\Query\NestedQueryStructureBuilder;

/**
 * Condition group for nested parent field queries.
 *
 * Extends the standard ConditionGroup to add support for Elasticsearch nested
 * queries by tracking the parent field path and using the query builder to
 * resolve correct field paths including .keyword suffixes.
 */
class NestedParentFieldConditionGroup extends ConditionGroup {

    protected ?string $parentFieldName = null;
    protected ?Index $index = null;
    protected ?NestedQueryStructureBuilder $queryBuilder = null;


    /**
     * Sets the Search API index.
     *
     * @param Index $index
     *   The Search API index.
     *
     * @return $this
     */
    public function setIndex(Index $index): self {
        $this->index = $index;
        return $this;
    }


    /**
     * Sets the query builder for field path resolution.
     *
     * @param NestedQueryStructureBuilder $queryBuilder
     *   The query structure builder.
     *
     * @return $this
     */
    public function setQueryBuilder(NestedQueryStructureBuilder $queryBuilder): self {
        $this->queryBuilder = $queryBuilder;
        return $this;
    }


    /**
     * Sets the parent field name.
     *
     * @param string $parent_fld_nm
     *   The parent field name.
     *
     * @return $this
     */
    public function setParentFieldName(string $parent_fld_nm): self {
        $this->parentFieldName = $parent_fld_nm;
        return $this;
    }


    /**
     * Gets the parent field name.
     *
     * @return string|null
     *   The parent field name, or NULL if not set.
     */
    public function getParentFieldName(): ?string {
        return $this->parentFieldName;
    }


    /**
     * Checks if this is a nested parent field condition group.
     *
     * @return bool
     *   TRUE if parent field name is set, FALSE otherwise.
     */
    public function isNestedParentField(): bool {
        return !empty($this->parentFieldName);
    }


    /**
     * Adds a child field condition.
     *
     * Uses the query builder to resolve the correct Elasticsearch field path,
     * including .keyword suffixes where needed.
     *
     * @param string $child_fld_nm
     *   The child field name within the nested object.
     * @param mixed $value
     *   The value to filter on.
     * @param string $operator
     *   The comparison operator (=, !=, <, >, etc.).
     *
     * @return $this
     *
     * @throws \LogicException
     *   If required dependencies (parent field, index, query builder) are not set.
     */
    public function addChildFieldCondition(string $child_fld_nm, $value, string $operator = '='): self {
        if (!$this->parentFieldName || !$this->index || !$this->queryBuilder) {
            throw new \LogicException(
                'Parent field name, index, and query builder must be set before adding child field conditions.'
            );
        }

        // Resolve the full Elasticsearch field path (e.g., "field_relationships.role.keyword")
        $path = $this->queryBuilder->getElasticQueryFieldPath(
            $this->index, 
            $this->parentFieldName, 
            $child_fld_nm
        );
  
        $condition = new NestedChildFieldCondition($path, $value, $operator);
        $condition->setParentFieldName($this->parentFieldName);
        $condition->setChildFieldName($child_fld_nm);
        
        $this->conditions[] = $condition;
        return $this;
    }
}