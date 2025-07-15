<?php

namespace Drupal\customviewfilters\Plugin\views\filter;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Session\AccountProxy;
use Drupal\user\Entity\User;


/**
 *
 * @ViewsFilter("create_permission_filter")
 */

class CreatePermissionFilter extends InOperator {

  /**
   * {@inheritdoc}
   */

  function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['value'] = [
      '#type' => 'radios',
      '#options' => $this->getValueOptions(),
      '#default_value' => $this->value,
    ];
  }



  /**
   * {@inheritdoc}
   */

  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [];

      $this->valueOptions['__current_user__'] = $this->t('Current user');

      $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
      foreach ($roles as $role) {
        $this->valueOptions[$role->id()] = $role->label();
      }
    }
    return $this->valueOptions;
  }



  /**
   * {@inheritdoc}
   */

  public function validate() {
    if (!is_array($this->value) && !empty($this->value)) {
      $this->value = [$this->value];
    }
    return parent::validate();
  }



  /**
   * {@inheritdoc}
   */

  public function query() {
    if (isset($this->view->element)) {
      $this->view->element['#cache']['contexts'][] = 'user.permissions';
    }
    if (empty($this->value) || !method_exists($this->query, 'addWhere') || !isset($this->configuration['entity_type'])) {
      return;
    }
    switch($this->configuration['entity_type']) {
      case 'taxonomy_vocabulary':
        $entity_array = Vocabulary::loadMultiple();
        $id_field = 'vid';
        $create_perm_prefix = "create terms in ";
        //$edit_perm_prefix = "edit terms in ";
        $perm_suffix = '';
        break;
      case 'node_type':
        $entity_array = NodeType::loadMultiple();
        $id_field = 'type';
        $create_perm_prefix = "create ";
        //$edit_perm_prefix = "edit any ";
        $perm_suffix = " content";
        break;
      default:
        return;
    }

    $value = is_array($this->value) ? $this->value[0] : $this->value;

    $permitted = [];
    if ($value === '__current_user__') {
      $permission_owner = User::load(\Drupal::currentUser()->id());
    } else {
      $permission_owner = Role::load($value);
    } 
    if (!(($permission_owner instanceof Role) || $permission_owner instanceof User)) {
      return;
    }

    foreach ($entity_array as $type) {
      $id = $type->id();
      $create_perm = $create_perm_prefix . $id . $perm_suffix;
      //$edit_perm = $edit_perm_prefix . $id . $perm_suffix;
      if ($permission_owner->hasPermission($create_perm) /*|| $permission_owner->hasPermission($edit_perm)*/) {
          $permitted[] = $id;
      }
    }

    if (!empty($permitted)) {
      $this->query->condition($this->options['group'], $id_field, $permitted, 'IN');
    }
    else {
      $this->query->condition($this->options['group'], $id_field, '_none_', '=');
    }
  }
}