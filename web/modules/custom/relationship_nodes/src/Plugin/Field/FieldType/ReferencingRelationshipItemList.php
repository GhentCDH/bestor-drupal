<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\relationship_nodes\Plugin\Field\FieldWidget\IefValidatedRelationsSimple;

/**
 * Defines the 'entity_reference' entity field type.
 *
 * @FieldType(
 *   id = "referencing_relationship_item_list",
 *   label = @Translation("Referencing Relationship Item List"),
 *   description = @Translation("Field type: reference in two directions (referencing and referenced)."),
 * )
 */


/**
 * Item list class for the Referencing Relationships.
 */
class ReferencingRelationshipItemList extends EntityReferenceFieldItemList {
  
  use ComputedItemListTrait;
  
  /**
   * {@inheritdoc}
   */
  protected function computeValue() : void {
    dpm($this->getParent()->getEntity(), 'this');
    $current_node = $this->getParent()->getEntity() ?? null;
    if(!($current_node instanceof Node)){
      return;
    }
    $relation_bundle = $this->definition['bundle'] ?? '';
    if(empty($relation_bundle)){
      return;
    }
    $join_fields = $this->getSettings()['join_field'] ?? [];
    if(empty($join_fields)){
      return;
    }
    $info_service = \Drupal::service('relationship_nodes.relationship_info_service');
    $related_nodes = $info_service->getReferencingRelations($current_node, $relation_bundle, $join_fields) ?? [];
    if(empty($related_nodes)){
      return;
    }

    $delta = 0;
    foreach ($related_nodes as $target_id => $related_node) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => $target_id, 'entity' => $related_node]);
      $delta++;
    }     
  }   
  


  // ONDERSTRAANDE MOET ZEKER NOG WEGGEWERKT WORDEN
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