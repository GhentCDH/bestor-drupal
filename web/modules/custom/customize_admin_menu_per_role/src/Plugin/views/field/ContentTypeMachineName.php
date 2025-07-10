<?php

namespace Drupal\customize_admin_menu_per_role\Plugin\views\field;

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
    return $values->entity->id();
  }
}