<?php

namespace Drupal\bestor_content_helper\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('system.theme_settings_theme')) {
      $requirements = $route->getRequirements();
      
      if (isset($requirements['_permission'])) {
        unset($requirements['_permission']);
      }
      
      $requirements['_custom_access'] = '\Drupal\bestor_content_helper\Access\ThemeSettingsAccess::access';
      
      $route->setRequirements($requirements);
    }
  }
}