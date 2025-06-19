<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityFieldManagerInterface;


/**
 * Item list class for the Referencing Relationships.
 */
class ReferencingRelationshipItemList extends EntityReferenceFieldItemList {
  
  use ComputedItemListTrait;
  
  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $related_nodes = self::getRelations($this);
    if(count( $related_nodes) > 0) {
      $delta = 0;
      foreach ($related_nodes as $target_id =>  $related_node) {
        $this->list[$delta] = $this->createItem($delta, ['target_id' => $target_id, 'entity' => $related_node]);
        $delta++;

      }     
    }
  }   
  

  /**
   *
   * @param Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList $ReferencingRelationshipItemList
   *
   * @return array
   */
  public static function getRelations($ReferencingRelationshipItemList){
    $related_nodes = [];
    $current_nid = $ReferencingRelationshipItemList->getParent()->getEntity()->id();
    $node_bundle = $ReferencingRelationshipItemList->definition['bundle'];
    $join_field_array = $ReferencingRelationshipItemList->getSettings()['join_field'];
    $related_nodes = [];
    if (!is_array($join_field_array) || empty($join_field_array)) {
      return $related_nodes;
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
    }
    return $related_nodes;
  }
}