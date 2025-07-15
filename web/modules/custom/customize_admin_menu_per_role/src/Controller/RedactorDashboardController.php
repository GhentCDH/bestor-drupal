<?php

namespace Drupal\customize_admin_menu_per_role\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class RedactorDashboardController extends ControllerBase {

  public function dashboard() {
    $blocks = [];

    // Content block
    $blocks['content'] = [
      '#theme' => 'admin_block',
      '#attributes' => ['class' => ['admin-block', 'admin-block--content']],
      '#block' => [
        'title' => $this->t('Content'),
        'content' => $this->buildLinks([
          'All content overview' => 'view.content.page_2',
          'Administer choicelists' => 'view.choice_lists.page_1',
        ]),
      ],
    ];

    // Add new content block
    $blocks['add'] = [
      '#theme' => 'admin_block',
      '#attributes' => ['class' => ['admin-block', 'admin-block--config']],
      '#block' => [
        'title' => $this->t('Add new content'),
        'content' => $this->buildLinks([
          'Actua (news, publications, events)' => 'view.add_content.page_4',
          'Database lemma' => 'view.add_content.page_3',
        ]),
      ],
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['webmanager-dashboard', 'admin-blocks']],
      'blocks' => $blocks,
    ];
  }
  

  private function buildLinks(array $links): array {
    $rendered = [];

    foreach ($links as $title => $route) {
      $link = Link::fromTextAndUrl($this->t($title), Url::fromRoute($route))->toRenderable();
      $link['#attributes']['class'][] = 'admin-item__link';

      // Wrap elke link in een div
      $rendered[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['admin-item__title', 'custom-dashboard-link']],
        'link' => $link,
      ];
    }

    return $rendered;
  }
}
