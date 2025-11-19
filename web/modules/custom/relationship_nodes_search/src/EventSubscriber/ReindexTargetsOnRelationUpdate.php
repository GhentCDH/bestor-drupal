<?php

namespace Drupal\relationship_nodes_search\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Event subscriber that triggers Search API reindexing
 * when a relation node (linking two entities) is created, updated, or deleted.
 */
class ReindexTargetsOnRelationUpdate implements EventSubscriberInterface {

  protected EntityTypeManagerInterface $entityTypeManager; 
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;
  protected LoggerChannelFactoryInterface $loggerFactory; 
  protected RelationBundleSettingsManager $settingsManager;
  protected RelationNodeInfoService $nodeInfoService;


  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    LoggerChannelFactoryInterface $loggerFactory,
    RelationBundleSettingsManager $settingsManager,
    RelationNodeInfoService $nodeInfoService
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->loggerFactory = $loggerFactory;
    $this->settingsManager = $settingsManager;
    $this->nodeInfoService = $nodeInfoService;
  }


  /**
   * Registers the entity events (cf entity_events contrib module) this subscriber listens to.
   *
   * Whenever a relation node is inserted, updated, or deleted,
   * the `trackRelatedEntitiesForReindexing()` method will be executed.
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['trackRelatedEntitiesForReindexing'],
      EntityEventType::UPDATE => ['trackRelatedEntitiesForReindexing'],
      EntityEventType::PREDELETE => ['trackRelatedEntitiesForReindexing'],
    ];
  }


  public function trackRelatedEntitiesForReindexing(EntityEvent $event, string $event_name): void {
    
    // Only process if the entity is a recognized relation node type.
    $entity = $event->getEntity();
    if (!$entity instanceof Node || !$this->settingsManager->isRelationNodeType($entity->bundle())) {
        return;
    }

    // Get the currently related entity IDs from this relation node.
    $field_values = $this->nodeInfoService->getRelatedEntityValues($entity);

    // Prepare array to hold all IDs (old + new) for reindexing.
    $all_ids = [];

    // If this is an UPDATE event, include IDs from the original entity as well.
    if ($event_name === EntityEventType::UPDATE && property_exists($entity, 'original')) {
        $old_values = $this->nodeInfoService->getRelatedEntityValues($entity->original) ?? [];
        foreach ($old_values as $ids) {
            if (!empty($ids) && is_array($ids)) {
                $all_ids = array_merge($all_ids, $ids);
            }
        }
    }

    // Merge the new/current related IDs.
    if (!empty($field_values)) {
        foreach ($field_values as $ids) {
            if (!empty($ids) && is_array($ids)) {
                $all_ids = array_merge($all_ids, $ids);
            }
        }
    }

    // Remove duplicates and ensure we have IDs to reindex.
    $unique_ids = array_unique($all_ids);
    if (empty($unique_ids)) {
        return;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $target_nodes = $node_storage->loadMultiple($unique_ids);

    //Search api ids, are strings of the format nid:lang_code (eg '101:en')
    $sapi_ids = [];
    foreach($target_nodes as $nid => $target_node){
      //Get the languages that are available for a specific target node
      $node_languages = array_keys($target_node->getTranslationLanguages());
      foreach($node_languages as $language_code){
        $sapi_ids[] = $nid . ':' . $language_code;
      }
    }  

    if(empty($sapi_ids)){
      return;
    }

    $this->trackItemsInIndexes($sapi_ids);
    
    // Invalidate cache for this specific relation bundle
    $relation_bundle = $entity->bundle();
    $this->invalidateRelationshipCache($relation_bundle, $unique_ids);
    $this->logReindexOperation($event_name, $entity->id(), $relation_bundle, count($sapi_ids));
  }


  /**
   * Track items in all active Search API indexes.
   */
  protected function trackItemsInIndexes(array $sapi_ids): void {
    $index_storage = $this->entityTypeManager->getStorage('search_api_index');
    $indexes = $index_storage->loadMultiple();

    foreach ($indexes as $index) {
      if (!$index->status() || !$index->isValidDatasource('entity:node')) {
        continue;
      }
      $index->trackItemsUpdated('entity:node', $sapi_ids);
    }
  }


  /**
   * Invalidate dropdown option caches for affected relationships.
   */
  protected function invalidateRelationshipCache(string $relation_bundle, array $affected_node_ids): void {
    // Invalidate general relationship options cache
    $cache_tags = ['relationship_filter_options'];
    
    // Add specific tags for this relation bundle
    $cache_tags[] = 'relationship_filter_options:' . $relation_bundle;
    
    // Add tags for affected nodes (if they have relationship fields displayed)
    foreach ($affected_node_ids as $nid) {
      $cache_tags[] = 'relationship_filter_options:node:' . $nid;
    }
    
    $this->cacheTagsInvalidator->invalidateTags($cache_tags);
  }


  /**
   * Log reindex operation for debugging.
   */
  protected function logReindexOperation(string $event_type, int $relation_id, string $bundle, int $affected_count): void {
    $this->loggerFactory->get('relationship_nodes_search')->info(
      'Reindexing triggered by @event on relation node @id (bundle: @bundle). Affected items: @count',
      [
        '@event' => $event_type,
        '@id' => $relation_id,
        '@bundle' => $bundle,
        '@count' => $affected_count,
      ]
    );
  }
}