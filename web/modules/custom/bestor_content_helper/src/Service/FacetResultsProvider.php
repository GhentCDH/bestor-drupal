<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\facets\Entity\Facet;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Views;
use Drupal\Core\Template\Attribute;

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


  protected function processFacetResults(string $view_name, string $filter_id, string $facet_query_id, string $display_id, bool $sort_alphabetically = FALSE): array {
    $results = $this->getFacetResults($view_name, $filter_id, $display_id);
    
    if (empty($results)) {
      return [];
    }

    if ($sort_alphabetically) {
      usort($results, function($a, $b) {
        return strcmp($a->getDisplayValue(), $b->getDisplayValue());
      });
    }
    
    $processed = [];
    
    foreach ($results as $result) {
      $count = $result->getCount();
      if (empty($count)) {
        continue;
      } 
      
      $tid = $result->getRawValue();
      $label = $result->getDisplayValue();
      $label_with_count = $label . ' (' . $count . ')';
      
      $processed[] = [
        'tid' => $tid,
        'label' => $label,
        'label_with_count' => $label_with_count,
        'url' => $this->getEnableFacetUrl($view_name, $display_id, $facet_query_id, $tid),
      ];
    }
    
    return $processed;
  }



  public function getSearchBannerFacetButtons(string $view_name, string $filter_id, string $facet_query_id, string $display_id = 'default'): array {
    $processed = $this->processFacetResults($view_name, $filter_id, $facet_query_id, $display_id);
      
    $links = [];
    foreach ($processed as $item) {
      $links[] = $this->getEnableFacetLinkRenderArray($item['url'], $item['label_with_count'], ['facet-item', 'c-button', 'no-media', 'c-button--outline']);
    }
    
    return $links;
  }


  public function getFacetResultMenuItems(string $view_name, string $filter_id, string $facet_query_id, string $display_id = 'default'): array {
    $processed = $this->processFacetResults($view_name, $filter_id, $facet_query_id, $display_id, TRUE);
    
    $items = [];
    $weight = 0;
    
    foreach ($processed as $item) {
      $items['facet_' . $item['tid']] = [
        'title' => $item['label'],
        'url' => $item['url'],
        'below' => [],
        'original_link' => NULL,
        'is_expanded' => FALSE,
        'is_collapsed' => FALSE,
        'weight' => $weight++,
        'attributes' => new Attribute([
          'class' => ['menu-item--facet'],
        ]),
      ];
    }
    
    return $items;
  }

  public function getEnableFacetUrl(string $view_id, string $view_display, string $facet_query_id, string|int $facet_value): ?Url{
    $view_route = 'view.' . $view_id . '.' . $view_display;
    $language = $this->languageManager->getCurrentLanguage();
    return Url::fromRoute(
        $view_route,
        [],
        [
          'query' => [$facet_query_id => $facet_value],
          'language' => $language,
        ]
      ) ?? NULL;
  }

  public function getEnableFacetLinkRenderArray(Url $url, string $link_text, array $classes = []): array {
    return [
      '#type' => 'link',
      '#title' => $link_text,
      '#url' => $url,
      '#attributes' => [
        'class' => $classes,
      ],
    ];
  }
}