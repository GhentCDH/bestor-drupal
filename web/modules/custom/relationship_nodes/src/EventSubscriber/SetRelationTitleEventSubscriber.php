<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EntityEventType;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Service\RelationEntityValidator;
use Drupal\relationship_nodes\Service\RelationSanitizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
//verplaats onderstaande
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

class SetRelationTitleEventSubscriber implements EventSubscriberInterface {

  protected RelationEntityValidator $relationEntityValidator;
  protected RelationSanitizer $relationSanitizer;


  public function __construct(
    RelationEntityValidator $relationEntityValidator,
    RelationSanitizer $relationSanitizer
  ) {
    $this->relationEntityValidator = $relationEntityValidator;
    $this->relationSanitizer = $relationSanitizer;
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
      dpm($node_type);
    }

    // testcode stop 

 
  
    if (!($entity instanceof Node) || !$this->relationEntityValidator->isValidRelationBundle($entity->getType())) {
      return;
    }
      dpm("save entity " . $entity->getType());
    $entity->set('title', $this->relationSanitizer->generateRelationLabel($entity));
    
  }
}
