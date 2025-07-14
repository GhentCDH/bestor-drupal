<?php

namespace Drupal\customviewfilters\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Annotation\ViewsField;


/**
 *
 * @ViewsField("content_type_machine_name")
 */
class ContentTypeMachineName extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (isset($values->_entity)) {
      return $values->_entity->id();
    }
    elseif (isset($values->entity)) {
      return $values->entity->id();
    }
    else {
      return '';
    }
  }
}