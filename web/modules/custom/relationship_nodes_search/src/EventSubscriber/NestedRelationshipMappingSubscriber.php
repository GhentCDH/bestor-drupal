<?php

namespace Drupal\relationship_nodes_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\FieldMappingEvent;
use Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class NestedRelationshipMappingSubscriber implements EventSubscriberInterface {


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
    $sapi_fld = $event->getField();
    
    if ($sapi_fld->getType() !== 'relationship_nodes_search_nested_relationship') {
      return;
    }

    $event->setParam([
      'type' => 'nested',
    ]);

    
  }


}