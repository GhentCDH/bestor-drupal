<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\facets\Entity\Facet;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Views;

class FacetResultsProvider {

  protected $languageManager;

  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }


  public function getFacetResults(string $view_name, string $filter_id, string $display_id = 'default'): array {
    $view = Views::getView($view_name);
    
    if (!$view) {
      return [];
    }

    $view->setDisplay($display_id);
    
    $view->build();
    $view->execute();
    
    $filters = $view->display_handler->getHandlers('filter');
    
    if (!isset($filters[$filter_id])) {
      return [];
    }
    
    $filter = $filters[$filter_id];
    
    return $filter->facet_results ?? [];
  }


public function getFacetResultLinks(string $view_name, string $filter_id, string $facet_url_id, string $display_id = 'default'): array {
  $results = $this->getFacetResults($view_name, $filter_id, $display_id);
  
  $is_page = str_starts_with($display_id, 'page_');
  $links = [];
  
  if ($is_page) {
    $view_route = 'view.' . $view_name . '.' . $display_id;
    $current_params = \Drupal::request()->query->all();
  }
  
  foreach ($results as $result) {
    $tid = $result->getRawValue();
    $label = $result->getDisplayValue();
    
    if ($result->getCount() !== FALSE) {
      $label .= ' (' . $result->getCount() . ')';
    }
    
    if ($is_page) {
      $query_params = $current_params;
      
      if (isset($query_params[$facet_url_id]) && is_array($query_params[$facet_url_id])) {
        $query_params[$facet_url_id][] = $tid;
      } else {
        $query_params[$facet_url_id] = [$tid];
      }
      
      $url = Url::fromRoute(
        $view_route,
        [],
        [
          'query' => $query_params,
          'language' => $this->languageManager->getCurrentLanguage(),
        ]
      );
      
      $links[] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $url,
        '#attributes' => [
          'class' => ['facet-item', 'c-button', 'no-media', 'c-button--outline'],
        ],
      ];
    }
  }
  
  return $links;
}
}