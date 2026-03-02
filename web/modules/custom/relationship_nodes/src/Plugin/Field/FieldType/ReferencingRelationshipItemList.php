<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationData\NodeHelper\RelationInfo;
use Drupal\relationship_nodes\RelationData\NodeHelper\RelationWeightManager;



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
  

  protected function computeValue() : void {
    $related_nodes = $this->collectExistingRelations();
    if(empty($related_nodes)){
      return;
    }

    $delta = 0;
    foreach ($related_nodes as $target_id => $related_node) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => $target_id, 'entity' => $related_node]);
      $delta++;
    }     
  }   
  

  public function collectExistingRelations(): array{
    $current_node = $this->getParent()->getEntity() ?? null;
    if(!($current_node instanceof Node) || $current_node->isNew()){
      return [];
    }
    $relation_bundle = $this->definition['bundle'] ?? '';
    if(empty($relation_bundle)){
      return [];
    }
    $join_fields = $this->getSettings()['join_field'] ?? [];
    if(empty($join_fields)){
      return [];
    }
    $relations_by_field = $this->getRelationInfoService()->getReferencingRelations($current_node, $relation_bundle, $join_fields, TRUE) ?? [];
    if (empty($relations_by_field)) {
      return [];
    }
    
    // Sort each group by weight and flatten
    return $this->getRelationWeightManager()->sortByWeight($relations_by_field);
  }


  protected function getRelationInfoService(): RelationInfo {
    return \Drupal::service('relationship_nodes.relation_info');
  }


  protected function getRelationWeightManager(): RelationWeightManager {
    return \Drupal::service('relationship_nodes.relation_weight_manager');
  }
}