<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\relationship_nodes\Service\MirrorTermAutoUpdater;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MirrorTermEventSubscriber implements EventSubscriberInterface {

  protected MirrorTermAutoUpdater $mirrorUpdater;

  public function __construct(MirrorTermAutoUpdater $mirrorUpdater) {
    $this->mirrorUpdater = $mirrorUpdater;
  }

  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['addMirrorLogic'],
      EntityEventType::UPDATE => ['addMirrorLogic'],
      EntityEventType::DELETE => ['addMirrorLogic'],
    ];
  }


  public function addMirrorLogic(EntityEvent $event, string $event_name): void {
    $entity = $event->getEntity();
    if ($entity instanceof TermInterface) {
      $this->mirrorUpdater->setMirrorTermLink($entity, $this->mapEventNameToHook($event_name));
    }
  }

  private function mapEventNameToHook(string $event_name): string {
    return match ($event_name) {
      EntityEventType::INSERT => 'insert',
      EntityEventType::UPDATE => 'update',
      EntityEventType::DELETE => 'delete',
      default => 'unknown',
    };
  }
}
