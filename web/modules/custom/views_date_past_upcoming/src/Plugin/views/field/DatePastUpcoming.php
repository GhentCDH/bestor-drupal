<?php

namespace Drupal\views_date_past_upcoming\Plugin\views\field;


use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Form\FormStateInterface;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *

 *
 * @ViewsField("date_past_upcoming")
 */
class DatePastUpcoming extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['datetime_field_machinename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Datetime machine name'),
      '#description' => $this->t('This field will calculate whether the date in the entered datetime field is in the future or the past.'),
      '#required' => TRUE,
      '#default_value' => $this->options['datetime_field_machinename'] ?? '',
    ];

    $form['use_end_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use end date if available'),
      '#description' => $this->t('If there is an end date (date range field), the end date should be taken into account.'),
      '#default_value' => $this->options['use_end_date'] ?? FALSE,
    ];

    $form['label_past'] = [
    '#type' => 'textfield',
    '#title' => $this->t('Label for past dates'),
    '#default_value' => $this->options['label_past'],
    ];

    $form['label_future'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for future dates'),
      '#default_value' => $this->options['label_future'] ?? 'Upcoming',
    ];
  }
  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['datetime_field_machinename'] = ['default' => ''];
    $options['use_end_date'] = ['default' => 0];
    $options['label_past'] = ['default' => $this->t('Past')];
    $options['label_upcoming'] = ['default' => $this->t('Upcoming')];

    return $options;
  }

  public function getValue(ResultRow $values, $field = NULL) {
    $entity = $values->_entity;

    if (!$entity) {
      return NULL;
    }

    $field_name = $this->options['datetime_field_machinename'];

    if (!$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return NULL;
    }

    $field = $entity->get($field_name);

    // Start date.
    $date_value = $field->value;

    // If it's a date range and end date should be used.
    if (
      $this->options['use_end_date'] &&
      isset($field->end_value) &&
      !empty($field->end_value)
    ) {
      $date_value = $field->end_value;
    }

    $timestamp = strtotime($date_value);
    $today = strtotime('today');

    return $timestamp < $today ? $this->options['label_past'] : $this->options['label_upcoming'];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    return $value ?? '';
  }

}
