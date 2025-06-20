<?php

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\SimpleWidget;

/**
 * Plugin implementation of the 'ief_validated_relations_simple' widget.
 *
 * @FieldWidget(
 *   id = "ief_validated_relations_simple",
 *   label = @Translation("IEF Validated Relations (simple)"),
 *   field_types = {
 *     "your_field_type_machine_name"
 *   }
 * )
 */
class IefValidatedRelationsSimple extends SimpleWidget {
  // Implement required methods.
}