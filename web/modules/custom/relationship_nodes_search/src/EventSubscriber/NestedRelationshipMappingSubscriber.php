<?php

namespace Drupal\relationship_nodes_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

class NestedRelationshipMappingSubscriber implements EventSubscriberInterface {

  protected EntityFieldManagerInterface $entityFieldManager;

  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  public static function getSubscribedEvents(): array {
    return [
        FieldMappingEvent::class => 'onFieldMapping',
        SupportsDataTypeEvent::class => 'onSupportsDataType',
    ];
  }


  public function onSupportsDataType(SupportsDataTypeEvent $event): void {
    if ($event->getType() === 'relationship_nodes_search_nested_relationship') {
      $event->setIsSupported(TRUE);
    }
  }

    public function onFieldMapping(FieldMappingEvent $event): void {
    $field = $event->getField();
    
    if ($field->getType() !== 'relationship_nodes_search_nested_relationship') {
      return;
    }

    $event->setParam([
      'type' => 'nested',

    ]);

    
  }


}