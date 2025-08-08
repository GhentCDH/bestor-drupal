<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EntityEventType;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Service\RelationEntityValidator;
use Drupal\relationship_nodes\Service\RelationSanitizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
    
    if (!($entity instanceof Node) || !$this->relationEntityValidator->isValidRelationBundle($entity->getType())) {
      return;
    }
    $entity->set('title', $this->relationSanitizer->generateRelationLabel($entity));
    
  }
}
