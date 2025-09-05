<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EntityEventType;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\node\Entity\NodeType;


class SetRelationTitleEventSubscriber implements EventSubscriberInterface {

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
      EntityEventType::PRESAVE => ['setRelationTitle'],
    ];
  }


  public function setRelationTitle(EntityEvent $event, string $event_name): void {
    $entity = $event->getEntity();


    // testcode start   -- check moet op termijn verbeterd worden en compatibel zijn aan instellingen
    if($entity instanceof Node){
      $node_type_id = $entity->bundle();
      $node_type = NodeType::load($node_type_id);
    }

    // testcode stop 

 
  
    if (!($entity instanceof Node) || !$this->settingsManager->isRelationNodeType($entity->getType())) {
      return;
    }
    $entity->set('title', $this->generateRelationLabel($entity));
    
  }

  
    private function generateRelationLabel(Node $relation_node): string{
        $related_entities = $this->nodeInfoService->getRelatedEntityValues($relation_node);
        $title_parts = [];
        $node_storage = $this->entityTypeManager->getStorage('node');
        foreach($related_entities as $field_values){
            $node_titles = [];
            foreach($field_values as $nid){
                $node = $node_storage->load($nid);
                if ($node instanceof Node) {
                    $node_titles[] = $node->getTitle();
                }
            }
            if (!empty($node_titles)) {
                $title_parts[] = implode(', ', $node_titles);
            }
        }
        return 'Relationship '  . implode(' - ', $title_parts);
    }
}