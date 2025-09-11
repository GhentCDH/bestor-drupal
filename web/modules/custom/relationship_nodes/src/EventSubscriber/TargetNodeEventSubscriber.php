<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EntityEventType;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationSyncService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TargetNodeEventSubscriber implements EventSubscriberInterface {

  protected RelationNodeInfoService $nodeInfoService;
  protected RelationSyncService $syncService;


  public function __construct(
    RelationNodeInfoService $nodeInfoService, 
    RelationSyncService $syncService
  ) {
    $this->nodeInfoService = $nodeInfoService;
    $this->syncService = $syncService;
  }

  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::DELETE => ['deleteOrphanedRelations'],
    ];
  }


  public function deleteOrphanedRelations(EntityEvent $event, string $event_name): void {
    $entity = $event->getEntity();
    if (!($entity instanceof Node)) {
      return;
    }
    $relations_per_type = $this->nodeInfoService->getAllReferencingRelations($entity) ?? [];
    if(!is_array($relations_per_type) || empty($relations_per_type)) {
      return;
    }
    $relation_ids = [];
    foreach($relations_per_type as $relations){
      $relation_ids = array_merge($relation_ids, array_keys($relations));
    }
    $this->syncService->deleteNodes($relation_ids);
  }
}
