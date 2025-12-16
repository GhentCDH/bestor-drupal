<?php

namespace Drupal\bestor_content_helper\Access;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class BestorSiteSettingAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess($entity, $operation, AccountInterface $account) {
    if ($operation === 'delete') {
      return AccessResult::forbidden();
    }
    return parent::checkAccess($entity, $operation, $account);
  }
}
