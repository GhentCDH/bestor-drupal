<?php

namespace Drupal\bestor_content_helper\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

class ThemeSettingsAccess implements AccessInterface {

  public function access(AccountInterface $account, RouteMatchInterface $route_match) {
    $theme = $route_match->getParameter('theme');
    
    if ($account->hasPermission('administer themes')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    
    if ($theme === 'bestor' && $account->hasPermission('administer bestor site settings')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    
    return AccessResult::forbidden();
  }
}