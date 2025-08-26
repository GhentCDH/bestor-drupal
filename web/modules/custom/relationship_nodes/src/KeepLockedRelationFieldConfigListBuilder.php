<?php

namespace Drupal\relationship_nodes;

use Drupal\field_ui\FieldConfigListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;


class KeepLockedRelationFieldConfigListBuilder extends FieldConfigListBuilder {

 public function buildRow(EntityInterface $field_config) {
    $row = parent::buildRow($field_config);
    $original_operations = ConfigEntityListBuilder::buildRow($field_config) ?? [];
    $field_config_helper = \Drupal::service('relationship_nodes.relation_field_config_helper');
    $field_config_helper->overrideOperationsEdit($row, $field_config, $original_operations);
    return $row;
  }
}


