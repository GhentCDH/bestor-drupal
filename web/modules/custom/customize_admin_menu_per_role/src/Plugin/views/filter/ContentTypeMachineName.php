<?php

namespace Drupal\customize_admin_menu_per_role\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Filters by content type machine name.
 *
 * @ViewsFilter("content_type_machine_name")
 */
class ContentTypeMachineName extends FilterPluginBase {

  protected $acceptMultipleValues = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
      foreach ($types as $machine_name => $type) {
        $this->valueOptions[$machine_name] = $type->label();
      }
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => $this->getValueOptions(),
      '#default_value' => $this->value,
      '#multiple' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
 public function query() {
  $table_alias = $this->ensureMyTable();

  if (!$table_alias) {
    $table_alias = $this->view->query->addTable('config_node_type');
  }
  //werkt niet verder doen hiermee
  
  dpm($this->view->query, 'View query');
  dpm($table_alias, 'Table alias for content type machine name');
  $field = "$table_alias.content_type_machine_name";

  if (is_array($this->value)) {
    $this->query->addWhere($this->options['group'], $field, $this->value, 'IN');
  }
  else {
    $this->query->addWhere($this->options['group'], $field, $this->value, $this->operator);
  }
}

}