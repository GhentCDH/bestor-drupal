<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationSyncService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for target node operations.
 */
class TargetNodeEventSubscriber implements EventSubscriberInterface {

  protected RelationNodeInfoService $nodeInfoService;
  protected RelationSyncService $syncService;


  /**
   * Constructs a TargetNodeEventSubscriber object.
   *
   * @param RelationNodeInfoService $nodeInfoService
   *   The node info service.
   * @param RelationSyncService $syncService
   *   The sync service.
   */
  public function __construct(
    RelationNodeInfoService $nodeInfoService, 
    RelationSyncService $syncService
  ) {
    $this->nodeInfoService = $nodeInfoService;
    $this->syncService = $syncService;
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::DELETE => ['deleteOrphanedRelations'],
    ];
  }


  /**
   * Deletes orphaned relation nodes when target nodes are deleted.
   *
   * @param EntityEvent $event
   *   The entity event.
   * @param string $event_name
   *   The event name.
   */
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
