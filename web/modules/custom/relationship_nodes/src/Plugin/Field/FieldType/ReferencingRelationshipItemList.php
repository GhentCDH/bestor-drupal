<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;


/**
 * Item list class for the Referencing Relationships.
 */
class ReferencingRelationshipItemList extends EntityReferenceFieldItemList {
  
  use ComputedItemListTrait;
  
  /**
   * {@inheritdoc}
   */
  protected function computeValue() {

    $current_nid = $this->getParent()->getEntity()->id();
    $node_bundle = $this->definition['bundle'];
    $join_field_array = $this->getSettings()['join_field'];
    $related_nodes = [];
    if (!is_array($join_field_array) || empty($join_field_array)) {
      return;
    }
    if ($current_nid && $node_bundle && $join_field_array) {
      foreach ($join_field_array as $join_field) {
        $query_result = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
          'type' => $node_bundle,
          $join_field => $current_nid,
        ]);
        if ($query_result) {
          $related_nodes += $query_result;
        }
      }
      if(count( $related_nodes) > 0) {
        $delta = 0;
        foreach ($related_nodes as $target_id =>  $related_node) {
          $this->list[$delta] = $this->createItem($delta, ['target_id' => $target_id, 'entity' => $related_node]);
          $delta++;
        }     
      }
    }   
  }
}