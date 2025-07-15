<?php

namespace Drupal\customviewfilters\Plugin\views\filter;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

/**
 * Filters taxonomy vocabularies based on role or current user permissions.
 *
 * @ViewsFilter("vocabulary_permission_filter")
 */
class VocabularyPermissionFilter extends InOperator {
  /**
   * Override default form type for the value widget to radios.
   *
   * @var string
   */
  protected $valueFormType = 'radios';


  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [];

      // Voeg optie voor current user toe.
      $this->valueOptions['__current_user__'] = $this->t('Current user');

      // Voeg alle rollen toe als opties.
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
  public function query() {

    if (isset($this->view->element)) {
      $this->view->element['#cache']['contexts'][] = 'user.permissions';
    }

    if (empty($this->value) || !method_exists($this->query, 'addWhere')) {
      return;
    }

    $value = $this->value[0];
    $vocabularies = Vocabulary::loadMultiple();
    $permitted = [];

    if ($value === '__current_user__') {
      $permission_owner = \Drupal::currentUser();
    } else {
      $permission_owner = Role::load($value);
    }
    
    foreach ($vocabularies as $vocab) {
      $vid = $vocab->id();
      $create_perm = "create terms in $vid";
      $edit_perm = "edit terms in $vid";
      if ($permission_owner->hasPermission($create_perm) || $permission_owner->hasPermission($edit_perm)) {
          $permitted[] = $vid;
      }
    }

    if (!empty($permitted)) {
      $this->query->condition($this->options['group'], 'vid', $permitted, 'IN');
    }
    else {
      $this->query->condition($this->options['group'], 'vid', '_none_', '=');
    }
  }

}
