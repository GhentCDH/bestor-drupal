<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\relationship_nodes\Service\MirrorService;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\entity_events\EntityEventType;

class MirrorTermEventSubscriber implements EventSubscriberInterface {

  protected MirrorService $mirrorService;

  public function __construct(MirrorService $mirrorService) {
    $this->mirrorService = $mirrorService;
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
      $this->mirrorService->setMirrorTermLink($entity, $this->mapEventNameToHook($event_name));
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
