<?php

namespace Drupal\relationship_nodes_search\EventSubscriber;

use Drupal\search_api_elasticsearch_client\Event\FieldMappingEvent;
use Drupal\search_api_elasticsearch_client\Event\SupportsDataTypeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NestedRelationshipMappingSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
        FieldMappingEvent::class => 'onFieldMapping',
        SupportsDataTypeEvent::class => 'onSupportsDataType',
    ];
  }

    public function onFieldMapping(FieldMappingEvent $event): void {
    $field = $event->getField();
    
    if ($field->getType() === 'relationship_nodes_search_nested_relationship' && 1 == 2) { //DIT WORDT DUS NIET UITGEVOERD

      $event->setParam([
        'type' => 'nested',
       /* 'properties' => [
          'id' => ['type' => 'keyword'],
          'title' => ['type' => 'text'],
        ],*/
      ]);
    }
  }

   public function onSupportsDataType(SupportsDataTypeEvent $event) {
    $type = $event->getType();
    if ($type === 'relationship_nodes_search_nested_relationship') {
      $event->setIsSupported(TRUE);
    }
  }
}