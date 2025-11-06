<?php

namespace Drupal\relationship_nodes_search\Plugin\facets\query_type;

use Drupal\facets\Plugin\facets\query_type\SearchApiString;
use Drupal\facets\FacetInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes_search\Service\RelationSearchService;
use Drupal\relationship_nodes_search\Service\NestedAggregationService;
use Drupal\relationship_nodes_search\Service\NestedFilterExposedWidgetHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedParentFieldConditionGroup;

/**
 * Provides support for nested facets within Search API.
 *
 * @FacetsQueryType(
 *   id = "search_api_nested",
 *   label = @Translation("Search API Nested"),
 * )
 */
class SearchApiNested extends SearchApiString implements ContainerFactoryPluginInterface{

  protected RelationSearchService $relationSearchService;
  protected NestedAggregationService $nestedAggregationService;
  protected NestedFilterExposedWidgetHelper $filterWidgetHelper;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RelationSearchService $relationSearchService,
    NestedAggregationService $nestedAggregationService,
    NestedFilterExposedWidgetHelper $filterWidgetHelper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->relationSearchService = $relationSearchService;
    $this->nestedAggregationService = $nestedAggregationService;
    $this->filterWidgetHelper = $filterWidgetHelper;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('relationship_nodes_search.relation_search_service'),
      $container->get('relationship_nodes_search.nested_aggregation_service'),
      $container->get('relationship_nodes_search.nested_filter_exposed_widget_helper')
    );
  }

  /**
   * Applies active filters as a nested condition group to the Search API query.
   *//*
  public function execute() {
    $facet = $this->facet;
    $query = $this->query;
    $index = $query->getIndex();

    $parent_field = $facet->getFieldIdentifier();
    $active_items = $facet->getActiveItems();



     dpm($this->query, 'DEZE QUERY JMOET AANGEWPAFG WORDEN');
     if ($query->getProcessingLevel() === QueryInterface::PROCESSING_FULL) {
        // Set the options for the actual query.
        $options = &$query->getOptions();
        $options['search_api_facets'][ $parent_field] = $this->getFacetOptions();
      }























    if (empty($active_items)) {
      return;
    }

    // Gebruik RelationSearchService om parent/child te valideren.
    $path = $this->relationSearchService->validateNestedPath($index, $parent_field);
    if (empty($path)) {
      return;
    }

    // Bouw de nested condition group.
    $condition_group = new NestedParentFieldConditionGroup('AND');
    $condition_group->setParentFieldName($path['parent']);

    foreach ($active_items as $value) {
      $condition_group->addChildFieldCondition($path['child'], $value, '=');
    }

    $query->addConditionGroup($condition_group);
  }*/

  /**
   * Determine whether this facet uses a nested field.
   */
  public function supportsFacet(FacetInterface $facet) {
    $field_identifier = $facet->getFieldIdentifier();
    // Laat RelationSearchService beslissen.
    return strpos($field_identifier, ':') !== false;
  }


}
