<?php

namespace Drupal\relationship_nodes\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\StorageComparerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationSettingsCleanUpService;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationValidationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Subscriber for configuration import events related to relationship nodes.
 *
 * Validates and processes relationship node configuration during config import,
 * including cleanup when the module is disabled.
 */
class RelationConfigImportSubscriber implements EventSubscriberInterface {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RelationSettingsCleanUpService $cleanupService;  
  protected RelationBundleSettingsManager $settingsManager;
  protected RelationValidationService $validationService;
  protected RelationFieldConfigurator $fieldConfigurator;


  /**
   * Constructs a RelationConfigImportSubscriber object.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param RelationSettingsCleanUpService $cleanupService
   *   The cleanup service.
   * @param RelationBundleSettingsManager $settingsManager
   *   The settings manager.
   * @param RelationValidationService $validationService
   *   The validation service.
   * @param RelationFieldConfigurator $fieldConfigurator
   *   The field configurator.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,     
    RelationSettingsCleanUpService $cleanupService,
    RelationBundleSettingsManager $settingsManager,
    RelationValidationService $validationService,
    RelationFieldConfigurator $fieldConfigurator
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->cleanupService = $cleanupService;
    $this->settingsManager = $settingsManager;
    $this->validationService = $validationService;
    $this->fieldConfigurator = $fieldConfigurator;
  }


  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::IMPORT_VALIDATE => 'onConfigImportValidate',
      // Respond to configuration import.
      ConfigEvents::IMPORT => 'onConfigImport',
    ];
  }


  /**
   * Validates configuration before import.
   *
   * @param ConfigImporterEvent $event
   *   The config import event.
   */
  public function onConfigImportValidate(ConfigImporterEvent $event): void {
    $storage_comparer = $event->getConfigImporter()->getStorageComparer();

    if ($this->getModuleStateChange($storage_comparer) === 'disabling') {
        return;
    }
    $source_storage = $storage_comparer->getSourceStorage();
    
    foreach ($this->getUpdatedBundleConfigsToValidate($storage_comparer) as $bundle_config_name) {   
      // Validate all relation node bundles and their linked fields.
      $this->validationService->displayBundleCimValidationErrors($bundle_config_name, $event, $source_storage);
    }
    foreach ($this->getDeletedFieldsToValidate($storage_comparer) as $field_config_name) {   
      // Prevent deletion of fields used by the module.
      $this->validationService->displayCimFieldDependenciesValidationErrors($field_config_name, $event, $source_storage);
    }
  }


  /**
   * Processes configuration after import.
   *
   * @param ConfigImporterEvent $event
   *   The config import event.
   */
  public function onConfigImport(ConfigImporterEvent $event): void {
    $storage_comparer = $event->getConfigImporter()->getStorageComparer();

    if ($this->getModuleStateChange($storage_comparer) === 'disabling') {
      $this->cleanupService->removeModuleSettings();
      return;
    }

    foreach ($this->fromConfigToEntities($this->getUpdatedRelationBundleConfigs($storage_comparer)) as $entity) {
      $this->fieldConfigurator->implementFieldUpdates($entity);
    }
  }


  /**
   * Determines if the module is being enabled or disabled.
   *
   * @param StorageComparerInterface $storage_comparer
   *   The storage comparer.
   *
   * @return string|null
   *   Returns 'enabling', 'disabling', or NULL.
   */
  protected function getModuleStateChange(StorageComparerInterface $storage_comparer): ?string {
      $source_storage = $storage_comparer->getSourceStorage();
      $target_storage = $storage_comparer->getTargetStorage();
      $source_extensions = $source_storage->read('core.extension');
      $target_extensions = $target_storage->read('core.extension');


      if (isset($source_extensions['module']['relationship_nodes']) && !isset($target_extensions['module']['relationship_nodes'])) {
        return 'disabling';
      }
      if (!isset($source_extensions['module']['relationship_nodes']) && isset($target_extensions['module']['relationship_nodes'])) {
        return 'enabling';
      }
      return NULL;
  }


  /**
   * Gets bundle configurations that need validation.
   *
   * @param StorageComparerInterface $storage_comparer
   *   The storage comparer.
   *
   * @return array
   *   Array of configuration names.
   */
   protected function getUpdatedBundleConfigsToValidate(StorageComparerInterface $storage_comparer): array {
    $result = [];
    $operations = ['create', 'update'];
    foreach ($storage_comparer->getAllCollectionNames() as $collection) {
      foreach ($operations as $op) {
        $change_list = $storage_comparer->getChangelist($op, $collection) ?? [];
        foreach ($change_list as $config_name) {
          if (str_starts_with($config_name, 'taxonomy.vocabulary.') || str_starts_with($config_name, 'node.type.')) {
            $result[] = $config_name;
          }
        }
      }
    }
    return $result;
   }


   /**
   * Gets updated relation bundle configurations.
   *
   * @param StorageComparerInterface $storage_comparer
   *   The storage comparer.
   *
   * @return array
   *   Array of configuration data keyed by configuration name.
   */
  protected function getUpdatedRelationBundleConfigs(StorageComparerInterface $storage_comparer): array {
    $result = [];
    $all_updated_bundles = $this->getUpdatedBundleConfigsToValidate($storage_comparer);
    $source_storage = $storage_comparer->getSourceStorage();
    foreach ($all_updated_bundles as $bundle_config_name) {
      $config_data = $source_storage->read($bundle_config_name);
      if ($config_data && $this->settingsManager->isCimRelationEntity($config_data)) {
        $result[$bundle_config_name] = $config_data;
      }        
    }
    return $result;
  }


  /**
   * Converts configuration data to loaded entities.
   *
   * @param array $config_list
   *   Array of configuration data keyed by configuration name.
   *
   * @return array
   *   Array of loaded entities.
   */
  protected function fromConfigToEntities(array $config_list){
    $load = ['node_type' => [], 'taxonomy_vocabulary' => [],];
    $result = [];
    foreach ($config_list as $config_name => $config_data) {
      $class_names = $this->settingsManager->getConfigFileEntityClasses($config_name);
      $entity_type = $class_names['entity_type_id'];
      if (isset($load[$entity_type])) {
        $load[$entity_type][] = $class_names['bundle'];
      }
    }
    foreach ($load as $entity_type => $entities){
      if (empty($entities)) {
        continue;
      }
      $entity_list = $this->entityTypeManager->getStorage($entity_type)->loadMultiple($entities);
      $result = array_merge($result, $entity_list);
    }
    return $result;
  }


  /**
   * Gets field configurations that are being deleted and need validation.
   *
   * @param StorageComparerInterface $storage_comparer
   *   The storage comparer.
   *
   * @return array
   *   Array of field configuration names.
   */
  protected function getDeletedFieldsToValidate(StorageComparerInterface $storage_comparer): array {
    $result = [];
    foreach ($storage_comparer->getAllCollectionNames() as $collection) {
      $change_list = $storage_comparer->getChangelist('delete', $collection) ?? [];
      foreach ($change_list as $config_name) {
        if (
          str_starts_with($config_name, 'field.storage.taxonomy_term.') || 
          str_starts_with($config_name, 'field.storage.node.') || 
          str_starts_with($config_name, 'field.field.taxonomy_term') ||
          str_starts_with($config_name, 'field.field.node.')
        ) {
           $result[] = $config_name;
        }
      }
    }
    return $result;
  }
}