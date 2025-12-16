<?php

namespace Drupal\views_date_past_upcoming\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Sort by past/upcoming date logic.
 *
 * @ViewsSort("date_past_upcoming_sort")
 */
class DatePastUpcomingSort extends SortPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $remove_options = ['order', 'expose', 'exposed'];
    foreach($remove_options as $ro){
      if(isset($options[$ro])) {
        unset($options[$ro]);
      }
    }

    $options['datetime_field_machinename'] = ['default' => ''];
    $options['use_end_date'] = ['default' => 0];
    return $options;
  }


  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    // The module forces a specific order
    if(isset($form['order'])) {
      unset($form['order']);
    }
    // There are no parameters to be updated by visitors
    if(isset($form['expose_button'])) {
      unset($form['expose_button']);
    }

    $form['datetime_field_machinename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datetime field machine name'),
      '#required' => TRUE,
      '#default_value' => $this->options['datetime_field_machinename'] ?? '',
      '#description' => $this->t('Example: field_time_period (without node__ prefix)'),
    ];

    $form['use_end_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use end date if available'),
      '#default_value' => $this->options['use_end_date'] ?? FALSE,
      '#description' => $this->t('For date range fields, sort by end date instead of start date.'),
    ];
  }


  /**
   * {@inheritdoc}
   */
   public function query() {
    $this->ensureMyTable();
    
    $field = $this->options['datetime_field_machinename'];

    if (empty($field)) {
      return;
    }

    // Build the field table name
    $entity_type = $this->view->getBaseEntityType()->id();
    $field_table = "{$entity_type}__{$field}";

    // Ensure the field table is joined
    $configuration = [
      'table' => $field_table,
      'field' => 'entity_id',
      'left_table' => $entity_type . '_field_data',
      'left_field' => $entity_type === 'node' ? 'nid' : 'id',
    ];
    
    $join = \Drupal::service('plugin.manager.views.join')
      ->createInstance('standard', $configuration);
    
    $alias = $this->query->addRelationship($field_table, $join, $entity_type . '_field_data');

    // Determine which column to use
    $column_suffix = $this->options['use_end_date'] ? 'end_value' : 'value';
    $column = "{$alias}.{$field}_{$column_suffix}";

    // CASE expression: future dates get their timestamp, past dates get inverted
    $formula = "
      CASE
        WHEN {$column} >= NOW()
          THEN UNIX_TIMESTAMP({$column})
        ELSE
          10000000000 - UNIX_TIMESTAMP({$column})
      END
    ";

    // Add the ORDER BY with the formula
    $this->query->addOrderBy(NULL, $formula, 'ASC', 'date_past_upcoming_order');
  }

}