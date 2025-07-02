<?php

namespace Drupal\customviewfilters\Plugin\views\filter;

use Drupal\config_views\Plugin\views\filter\StringEntity;

/**
 * Provides an extended string filter with extra operators.
 *
 * @ViewsFilter("custom_extended_string_filter")
 */

class CustomExtendedStringFilter extends StringEntity {

 /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();
    $operators['NOT_STARTS_WITH'] = [
      'title' => $this->t('Does not start with'),
      'short' => $this->t('not begins'),
      'method' => 'opNotStartsWith',
      'values' => 1,
    ];
    return $operators;
  }

  /**
     * Implements the 'NOT_STARTS_WITH' operator with EntityQuery workaround.
     *
     * @param string $field
     *   The field to filter on.
     */
  protected function opNotStartsWith($field) {
    $value = $this->value;
    $query = $this->query;
    $helpquery = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->getQuery();

    $matching_ids = $helpquery
    ->accessCheck(TRUE)
    ->condition($field, $value, 'STARTS_WITH')// Zoek naar waarden die met $value beginnen')
    ->execute();

    if (empty($matching_ids)) {
      return;
    }
    $query->condition($this->options['group'], $field, $matching_ids, 'NOT IN');
  }
}