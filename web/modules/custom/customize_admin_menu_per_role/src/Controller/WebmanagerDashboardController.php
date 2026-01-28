<?php

namespace Drupal\customize_admin_menu_per_role\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class WebmanagerDashboardController extends ControllerBase {

  public function dashboard() {
    $blocks = [];

    // Content block
    $blocks['content'] = [
      '#theme' => 'admin_block',
      '#attributes' => ['class' => ['admin-block', 'admin-block--content']],
      '#block' => [
        'title' => $this->t('Content'),
        'content' => $this->buildLinks([
          'Content overview' => 'view.content.page_2',
          'Administer choicelists' => 'view.choice_lists.page_1',
          'All media' => 'entity.media.collection',
          'Overview relationships' => 'view.content.page_3',
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

    // User management block
    $blocks['users'] = [
      '#theme' => 'admin_block',
      '#attributes' => ['class' => ['admin-block', 'admin-block--users']],
      '#block' => [
        'title' => $this->t('User management'),
        'content' => $this->buildLinks([
          'Users' => 'entity.user.collection',
        ]),
      ],
    ];

    // Menus and footer block
    $blocks['menu_footer'] = [
      '#theme' => 'admin_block',
      '#attributes' => ['class' => ['admin-block', 'admin-block--menu-and-footer']],
      '#block' => [
        'title' => $this->t('Menus and footer'),
        'content' => $this->buildLinks([
          'Administer main menu' => ['entity.menu.edit_form', ['menu' => 'main']],
          "Manage partner logos in footer" => 'view.media.page_1'
        ]),
      ],
    ];

    // Site config block
    $blocks['config'] = [
      '#theme' => 'admin_block',
      '#attributes' => ['class' => ['admin-block', 'admin-block--config']],
      '#block' => [
        'title' => $this->t('Site configuration'),
        'content' => $this->buildLinks([
          'Site cache and performance' => 'system.performance_settings',
          'Maintenance mode' => 'system.site_maintenance_mode',
          'Regional settings' => 'system.regional_settings',
          'User interface translation' => 'locale.translate_page',
          'Configuration translation' => 'config_translation.mapper_list',
          'URL aliases' => 'entity.path_alias.collection',
          'Bestor specific site settings' => 'entity.bestor_site_setting.collection',
          'Contact form configuration' => ['entity.contact_form.edit_form', ['contact_form' => 'feedback']],
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
      if (is_array($route)) {
        [$route_name, $route_params] = $route;
        $url = Url::fromRoute($route_name, $route_params);
      } else {
        $url = Url::fromRoute($route);
      }
      $link = Link::fromTextAndUrl($this->t($title), $url)->toRenderable();
      $link['#attributes']['class'][] = 'admin-item__link';

      $rendered[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['admin-item__title', 'custom-dashboard-link']],
        'link' => $link,
      ];
    }

    return $rendered;
  }
}
