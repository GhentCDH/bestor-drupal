<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EntityEventType;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Drupal\relationship_nodes\Service\RelationSyncService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeleteTargetNodeEventSubscriber implements EventSubscriberInterface {

  protected RelationshipInfoService $infoService;
  protected RelationSyncService $syncService;


  public function __construct(
    RelationshipInfoService $infoService, 
    RelationSyncService $syncService
  ) {
    $this->infoService = $infoService;
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
    $relations_per_type = $this->infoService->getAllReferencingRelations($entity) ?? [];
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
