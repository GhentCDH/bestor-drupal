<?php

namespace Drupal\relationship_nodes;

use Drupal\field_ui\FieldConfigListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Url;

class KeepLockedFieldConfigListBuilder extends FieldConfigListBuilder {

 public function buildRow(EntityInterface $field_config) {
    $row = parent::buildRow($field_config);

    $field_storage = $field_config->getFieldStorageDefinition();

   if($field_storage->isLocked()){
    unset($row['data']['operations']);
    unset($row['class']['menu-disabled']); 
    
    $row['data'] = $row['data'] + ConfigEntityListBuilder::buildRow($field_config); 
    $node_type = '';
    $url = Url::fromRoute('relationship_nodes.relation_field_form',['node_type' => $field_config->getTargetBundle(), 'field_config' => $field_config->id(),]);
    $row['data']['operations']['data']['#links']['edit']['url'] = $url;
  }

    return $row;
  }

}


