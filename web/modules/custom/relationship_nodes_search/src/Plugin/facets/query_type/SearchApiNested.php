<?php

namespace Drupal\relationship_nodes_search\Plugin\facets\query_type;

use Drupal\facets\Plugin\facets\query_type\SearchApiString;
use Drupal\facets\Result\Result;
use Drupal\search_api\Query\QueryInterface;

/**
 * Provides support for string facets within the Search API scope.
 * @FacetsQueryType(
 *   id = "search_api_nested",
 *   label = @Translation("Nested"),
 * )
 */
class SearchApiNested extends SearchApiString {
 public function execute() {
   dpm('Run custom query!!!!');
    parent::execute();
  }

}
