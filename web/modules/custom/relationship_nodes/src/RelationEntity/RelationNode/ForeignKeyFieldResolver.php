<?php

namespace Drupal\relationship_nodes\RelationEntity\RelationNode;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;


class ForeignKeyFieldResolver {

  protected RouteMatchInterface $routeMatch;
  protected RelationBundleInfoService $bundleInfoService;
  protected RelationNodeInfoService $nodeInfoService;


  public function __construct(
    RouteMatchInterface $routeMatch,
    RelationBundleInfoService $bundleInfoService,
    RelationNodeInfoService $nodeInfoService,
  ) {
    $this->routeMatch = $routeMatch;
    $this->bundleInfoService = $bundleInfoService;
    $this->nodeInfoService = $nodeInfoService;
  }


  public function getDefaultBundleForeignKeyField(string $relation_bundle, string $target_bundle = null): ?string{       
    if(!$target_bundle){
      $target_entity = $this->ensureTargetNode();
      if(!($target_entity instanceof Node)){
        return null;
      }
      $target_bundle = $target_entity->getType();
    }        
    
    $connection_info = $this->bundleInfoService->getBundleConnectionInfo($relation_bundle, $target_bundle) ?? [];
    return $this->connectionInfoToForeignKey($connection_info);
  }


  public function getEntityForeignKeyField(Node $relation_entity, ?Node $target_entity = NULL): ?string {
    $target_entity = $this->ensureTargetNode($target_entity);
    if(!$target_entity){
      return null;
    }
    $relation_type = $relation_entity->getType();
    $target_entity_type = $target_entity->getType();
    if($relation_entity->isNew() || $target_entity->isNew()){
      $connection_info = $this->bundleInfoService->getBundleConnectionInfo($relation_type, $target_entity_type) ?? [];
    } else {
      $connection_info = $this->nodeInfoService->getEntityConnectionInfo($relation_entity, $target_entity) ?? [];
    }
    return $this->connectionInfoToForeignKey($connection_info);
  }


  public function getEntityFormForeignKeyField(array $entity_form, FormStateInterface $form_state):?string {
    if(!isset($entity_form['#entity']) || !($entity_form['#entity'] instanceof Node)){
      return null;
    }   
    $relation_entity = $entity_form['#entity'];
    $form_entity = $form_state->getFormObject()->getEntity();
    return $this->getEntityForeignKeyField($relation_entity,  $form_entity);   
  }


  private function ensureTargetNode(?Node $node = null): ?Node {
    if($node instanceof Node){
      return $node;
    }
    $current_node = $this->routeMatch->getParameter('node');
    return $current_node instanceof Node ? $current_node : null;
  }


  private function connectionInfoToForeignKey(array $connection_info): ?string{
    if(empty($connection_info['join_fields'])){
      return null;
    }
    $join_fields = $connection_info['join_fields'];

    if(!is_array($join_fields)){
      return null;
    }
    return $join_fields[0] ?? null;
  }
}