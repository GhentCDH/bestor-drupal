<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\entity_events\EntityEventType;
use Drupal\entity_events\Event\EntityEvent;
use Drupal\relationship_nodes\RelationEntity\RelationTermMirroring\MirrorTermAutoUpdater;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MirrorTermEventSubscriber implements EventSubscriberInterface {

  protected MirrorTermAutoUpdater $mirrorUpdater;
  protected RelationBundleSettingsManager $settingsManager;


  public function __construct(
    MirrorTermAutoUpdater $mirrorUpdater,
    RelationBundleSettingsManager $settingsManager
  ) {
    $this->mirrorUpdater = $mirrorUpdater;
    $this->settingsManager = $settingsManager;
  }

  public static function getSubscribedEvents(): array {
    return [
      EntityEventType::INSERT => ['addMirrorLogic'],
      EntityEventType::UPDATE => ['addMirrorLogic'],
      EntityEventType::DELETE => ['addMirrorLogic'],
    ];
  }


  public function addMirrorLogic(EntityEvent $event, string $event_name): void {
    $term = $event->getEntity();
    if (!$term instanceof TermInterface) {
      return;      
    }

    $vocab = $term->bundle();
    if(empty($vocab) || empty($this->settingsManager->isRelationVocab($vocab)) ||
        $this->settingsManager->getRelationVocabType($vocab) !== 'entity_reference'
    ){
        return;
    }
    $hook = $this->mapEventNameToHook($event_name);
    if ($hook === null) {
      return;
    }
    $this->mirrorUpdater->setMirrorTermLink($term, $hook);
  }

  private function mapEventNameToHook(string $event_name): ?string {
    return match ($event_name) {
      EntityEventType::INSERT => 'insert',
      EntityEventType::UPDATE => 'update',
      EntityEventType::DELETE => 'delete',
      default => null,
    };
  }
}
