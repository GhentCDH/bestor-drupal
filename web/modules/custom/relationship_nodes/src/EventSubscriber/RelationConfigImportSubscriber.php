<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageComparerInterface;
use Drupal\Core\Config\ConfigImporterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationSettingsCleanUpService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleValidator;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;


class RelationConfigImportSubscriber implements EventSubscriberInterface {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RelationSettingsCleanUpService $cleanupService;  
  protected RelationBundleInfoService $bundleInfoService;
  protected RelationBundleValidator $bundleValidator;
  protected RelationFieldConfigurator $fieldConfigurator;


  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,     
    RelationSettingsCleanUpService $cleanupService,
    RelationBundleInfoService $bundleInfoService,
    RelationBundleValidator $bundleValidator,
    RelationFieldConfigurator $fieldConfigurator
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->cleanupService = $cleanupService;
    $this->bundleInfoService = $bundleInfoService;
    $this->bundleValidator = $bundleValidator;
    $this->fieldConfigurator = $fieldConfigurator;
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
             $this->bundleValidator->validateEntityConfig('node_type', $entity_id, $config_data, $event);
          }
          elseif (str_starts_with($config_name, 'taxonomy.vocabulary.')) {
            $entity_id = substr($config_name, strlen('taxonomy.vocabulary.'));
             $this->bundleValidator->validateEntityConfig('taxonomy_vocabulary', $entity_id, $config_data, $event);
          }
        }
      }
    }
  }


  public function onConfigImport(ConfigImporterEvent $event): void {
    $storage_comparer = $event->getConfigImporter()->getStorageComparer();

    if ($this->isModuleDisabling($storage_comparer)) {
      $this->cleanupService->cleanupModuleData();
    } else {
      foreach ($this->getChangedRelationEntities($storage_comparer) as $entity) {
        $this->fieldConfigurator->implementFieldUpdates($entity);
      }
    }
  }


  protected function isModuleDisabling(StorageComparerInterface $storage_comparer): bool {
      $source_storage = $storage_comparer->getSourceStorage();
      $target_storage = $storage_comparer->getTargetStorage();
      $source_extensions = $source_storage->read('core.extension');
      $target_extensions = $target_storage->read('core.extension');

      return isset($source_extensions['module']['relationship_nodes'])
          && !isset($target_extensions['module']['relationship_nodes']);
  }


  protected function getChangedRelationEntities(StorageComparerInterface $storage_comparer): array{
    $changes = $storage_comparer->getChangelist();
    $changed_entities = [];
    $relation_config_names = $this->getAllRelationEntityConfigNames();
    
    foreach (['create', 'update'] as $op) {
      foreach ($changes[$op] ?? [] as $config_name) {
        if (in_array($config_name, $relation_config_names)) {
          if (str_starts_with($config_name, 'node.type.')) {
            $entity_id = substr($config_name, strlen('node.type.'));
            $storage = $this->entityTypeManager->getStorage('node_type');
          } elseif (str_starts_with($config_name, 'taxonomy.vocabulary.')) {
            $entity_id = substr($config_name, strlen('taxonomy.vocabulary.'));
            $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
          }      
          if (isset($storage)) {
            $entity = $storage->load($entity_id);
            if ($entity) {
              $changed_entities[] = $entity;
            }
          }
        }
      }
    }
    return $changed_entities;
  }

  
  protected function getAllRelationEntityConfigNames(): array{   
    $relation_entities = $this->bundleInfoService->getAllRelationEntityTypes();
    $relation_config_names = [];
    
    foreach ($relation_entities as $entity) {
      $entity_type = $entity->getEntityTypeId();
      $entity_id = $entity->id();
      if ($entity_type === 'node_type') {
        $relation_config_names[] = "node.type.{$entity_id}";
      } elseif ($entity_type === 'taxonomy_vocabulary') {
        $relation_config_names[] = "taxonomy.vocabulary.{$entity_id}";
      }
    }

    return  $relation_config_names;
  }
}