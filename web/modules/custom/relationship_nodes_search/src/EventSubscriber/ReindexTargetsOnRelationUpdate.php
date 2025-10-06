<?php

namespace Drupal\relationship_nodes_search\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class ReindexTargetsOnRelationUpdate implements EventSubscriberInterface {

  protected EntityTypeManagerInterface $entityTypeManager;  
  protected RelationBundleSettingsManager $settingsManager;
  protected RelationNodeInfoService $nodeInfoService;


  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RelationBundleSettingsManager $settingsManager,
    RelationNodeInfoService $nodeInfoService
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->settingsManager = $settingsManager;
    $this->nodeInfoService = $nodeInfoService;
  }


  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['trackRelatedEntitiesForReindexing'],
      EntityEventType::UPDATE => ['trackRelatedEntitiesForReindexing'],
      EntityEventType::DELETE => ['trackRelatedEntitiesForReindexing'],
    ];
  }


  public function trackRelatedEntitiesForReindexing(EntityEvent $event, string $event_name): void {
    
    $entity = $event->getEntity();
    if (
      !$entity instanceof Node || 
      !$this->settingsManager->isRelationNodeType($entity->bundle()) 
    ) {
      return;
    }

    $field_values = $this->nodeInfoService->getRelatedEntityValues($entity);
    
    if(empty($field_values)){
      return;
    }

    $ids_for_reindexing = [];
    foreach($field_values as $field_value){
      if(!empty($field_value)){
        $ids_for_reindexing = array_merge($ids_for_reindexing, $field_value);
      }
    }

    if(empty($ids_for_reindexing)){
      return;
    }

    $index_storage = $this->entityTypeManager->getStorage('search_api_index');
    $indexes = $index_storage->loadMultiple();
    
    foreach ($indexes as $index) {
      if (!$index->status() || !$index->isValidDatasource('entity:node')) {
        continue;
      }
      
      $index->trackItemsUpdated('entity:node', $ids_for_reindexing);
    }
  }
}