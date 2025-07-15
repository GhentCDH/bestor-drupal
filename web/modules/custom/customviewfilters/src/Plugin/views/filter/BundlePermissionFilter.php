<?php

namespace Drupal\customviewfilters\Plugin\views\filter;

use Drupal\node\Entity\NodeType;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 *
 * Currently supports node types.
 *
 * @ViewsFilter("bundle_permission_filter")
 */
class BundlePermissionFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [];

      $user = \Drupal::currentUser();

      foreach (NodeType::loadMultiple() as $type) {
        $type_id = $type->id();
        $label = $type->label();

        $create_perm = "create $type_id content";
        $edit_perm = "edit any $type_id content";

        if ($user->hasPermission($create_perm) || $user->hasPermission($edit_perm)) {
          $this->valueOptions[$type_id] = $label;
        }
      }
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
public function query() {
  // Voeg user.permissions cache context toe
  if (isset($this->view->element)) {
    $this->view->element['#cache']['contexts'][] = 'user.permissions';
  }

  if (!empty($this->value)) {
    $this->ensureMyTable();

    // Check of de query een ConfigEntityQuery is
    if ($this->query instanceof \Drupal\Core\Entity\Query\QueryInterface) {
      // Voeg filterconditie toe aan ConfigEntityQuery
      $this->query->addCondition($this->realField, $this->value, $this->operator);
    }
    else {
      // Fallback SQL
      $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $this->value, $this->operator);
    }
  }
}

}
