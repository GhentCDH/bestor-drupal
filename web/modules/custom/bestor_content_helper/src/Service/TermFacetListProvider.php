<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;

class TermFacetListProvider {

  protected $languageManager;

  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  /**
   *
   * Result: [
   *   ['label' => 'geology', 'url' => '/nl/database?f[0]=Filter:4'],
   *   ['label' => 'medicine', 'url' => '/nl/database?f[0]=Filter:7'],
   *   ...
   * ]
   */

  // eg. vocab_name = disicipline, $view_route = view.database.page_1, $filter_id = disicipline
  public function getList(string $vocab_name, string $view_route, string $filter_id) {
    $current_lang = $this->languageManager->getCurrentLanguage()->getId();

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $tree = $storage->loadTree($vocab_name);

    $items = [];

    foreach ($tree as $item) {

      $term = Term::load($item->tid)->getTranslationFromContext();

      $url = Url::fromRoute(
        $view_route,
        [],
        [
          'query' => [
            $filter_id . '[' . $item->tid . ']' => $item->tid,
          ],
          'language' => $this->languageManager->getCurrentLanguage(),
        ]
      )->toString(TRUE)->getGeneratedUrl();

      $items[] = [
        'label' => $term->label(),
        'tid'   => $item->tid,
        'url'   => $url,
      ];
    }

    return $items;
  }

}
