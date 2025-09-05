<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationSettingsCleanUpService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleValidator;


class RelationConfigImportSubscriber implements EventSubscriberInterface {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RelationSettingsCleanUpService $cleanupService;  
  protected RelationBundleValidator $bundleValidator;


  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RelationSettingsCleanUpService $cleanupService,
    RelationBundleValidator $bundleValidator
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->cleanupService = $cleanupService;
    $this->bundleValidator = $bundleValidator;
  }

  
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::IMPORT_VALIDATE => 'onConfigImportValidate',
      ConfigEvents::IMPORT => 'onConfigImport',
    ];
  }


  public function onConfigImportValidate(ConfigImporterEvent $event): void {
    $storage_comparer = $event->getConfigImporter()->getStorageComparer();
    if ($this->isModuleDisabling($storage_comparer)) {
        return;
    }
    $source_storage = $storage_comparer->getSourceStorage();

    foreach ($storage_comparer->getAllCollectionNames() as $collection) {
      $changes = $storage_comparer->getChangelist(null, $collection);

      foreach (['create', 'update'] as $op) {
        foreach ($changes[$op] ?? [] as $config_name) {
          $config_data = $source_storage->read($config_name);

          if (str_starts_with($config_name, 'node.type.')) {
            $entity_id = substr($config_name, strlen('node.type.'));
            $this->validateEntityConfig('node_type', $entity_id, $config_data, $event);
          }
          elseif (str_starts_with($config_name, 'taxonomy.vocabulary.')) {
            $entity_id = substr($config_name, strlen('taxonomy.vocabulary.'));
            $this->validateEntityConfig('taxonomy_vocabulary', $entity_id, $config_data, $event);
          }
        }
      }
    }
  }


  public function onConfigImport(ConfigImporterEvent $event): void {
    $storage_comparer = $event->getConfigImporter()->getStorageComparer();

    if ($this->isModuleDisabling($storage_comparer)) {
      $this->cleanupService->cleanupModuleData();
    }
  }


  protected function validateEntityConfig(string $entity_type, string $entity_id, array $config_data, ConfigImporterEvent $event): void {
    $relation_settings = $config_data['third_party_settings']
    ['relationship_nodes'] ?? [];
    if (empty($relation_settings['enabled'])) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->load($entity_id) ?: $storage->create($config_data);

    $error = $this->bundleValidator->validateRelationConfig($entity);
    if ($error) {
      $event->getConfigImporter()->logError($error);
    }
  }


  protected function isModuleDisabling($storage_comparer): bool {
      $source_storage = $storage_comparer->getSourceStorage();
      $target_storage = $storage_comparer->getTargetStorage();
      $source_extensions = $source_storage->read('core.extension');
      $target_extensions = $target_storage->read('core.extension');

      return !isset($source_extensions['module']['relationship_nodes'])
          && isset($target_extensions['module']['relationship_nodes']);
  }
}