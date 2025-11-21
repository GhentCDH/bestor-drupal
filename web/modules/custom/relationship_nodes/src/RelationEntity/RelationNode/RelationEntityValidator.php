<?php

namespace Drupal\relationship_nodes\RelationEntity\RelationNode;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;


class RelationEntityValidator {

  protected RouteMatchInterface $routeMatch;
  protected RelationNodeInfoService $nodeInfoService;
  protected ForeignKeyFieldResolver $foreignKeyResolver;

  public function __construct(
    RouteMatchInterface $routeMatch,
    RelationNodeInfoService $nodeInfoService,
    ForeignKeyFieldResolver $foreignKeyResolver
  ) {
    $this->routeMatch = $routeMatch;
    $this->nodeInfoService = $nodeInfoService;
    $this->foreignKeyResolver = $foreignKeyResolver;
  }


  public function checkRelationsValidity(Node $relation_entity): ?string {
    $related_entities = $this->nodeInfoService->getRelatedEntityValues($relation_entity); 
    if($related_entities === null) {
      return null;
    }

    $new_relation = false;
    if($relation_entity->isNew()){
      $current_node = $this->routeMatch->getParameter('node');
      $new_relation = true;
      if($current_node instanceof Node && $current_node !== $relation_entity){
        // Relation is added in a subform (IEF)
        $foreign_key_field = $this->foreignKeyResolver->getEntityForeignKeyField($relation_entity, $current_node);
        if($foreign_key_field){
          $related_entities[$foreign_key_field] = [$current_node->id()];
        }
      }
    }
    if (count($related_entities) != 2 && !$new_relation) {
      return 'incomplete';
    }

    $related_entities = array_values($related_entities);   
    foreach($related_entities[0] as $reference){
      if(in_array($reference, $related_entities[1] ?? [])){
        return 'selfReferring'; 
      }
    }
    return null;
  }
}