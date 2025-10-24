<?php

namespace Drupal\relationship_nodes_search\Plugin\facets\processor;

use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\PreQueryProcessorInterface;

/**
 * @FacetsProcessor(
 *   id = "nested_build_processor",
 *   label = @Translation("Nested Build Processor"),
 *   description = @Translation("Process as a nested field."),
 *   stages = {
 *     "pre_query" = 0,
 *     "build" = 0,
 *   },
 * )
 */
class NestedBuildProcessor extends ProcessorPluginBase  implements BuildProcessorInterface, PreQueryProcessorInterface {


    /**
     * {@inheritdoc}
     */
    public function build(FacetInterface $facet, array $results) {
        dpm($facet, 'build facet');
        dpm($results, 'build resultsx');
        return $results;
    }

    public function preQuery(FacetInterface $facet) {
        parent::preQuery($facet);
    }


    /**
   * {@inheritdoc}
   */
    public function getQueryType() {
        return 'relationship_nodes_search_nested_relationship';
    }

}