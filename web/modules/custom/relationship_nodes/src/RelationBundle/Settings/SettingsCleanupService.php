<?php

namespace Drupal\relationship_nodes\RelationBundle\Settings;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\RelationBundle\BundleInfoService;
use Drupal\relationship_nodes\RelationField\RelationshipFieldManager;


/**
 * Service for cleaning up relationship nodes settings.
 *
 * Removes module settings and cleans up form displays when module is uninstalled.
 */
class SettingsCleanupService {
      
  protected EntityTypeManagerInterface $entityTypeManager;
  protected BundleInfoService $bundleInfoService;
  protected RelationshipFieldManager $relationFieldManager;


  /**
   * Constructs a SettingsCleanupService object.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param BundleInfoService $bundleInfoService
   *   The bundle info service.
   * @param RelationshipFieldManager $relationFieldManager
   *   The field configurator.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    BundleInfoService $bundleInfoService, 
    RelationshipFieldManager $relationFieldManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfoService = $bundleInfoService;
    $this->relationFieldManager = $relationFieldManager;
  }


  /**
   * Removes all module settings.
   */
  public function removeModuleSettings(): void {
    $this->unsetRnEntitySettings();
    $this->cleanFormDisplays();
  }


  /**
   * Unsets relationship nodes settings from entities.
   */
  protected function unsetRnEntitySettings(): void {
    try {
      foreach ($this->bundleInfoService->getAllRelationBundles() as $entity_type) {
        $updated = $this->unsetRnThirdPartySettings($entity_type) ?? [];
      }
      foreach ($this->relationFieldManager->getAllRnCreatedFields() as $field) {
        $updated = $this->unsetRnThirdPartySettings($field) ?? [];
      }
      \Drupal::cache()->deleteAll();
    } 
    catch (\Exception $e) {
      \Drupal::logger('relationship_nodes')->error('Error cleaning up Relationship Nodes data: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }


  /**
   * Cleans form and view displays of relationship node widgets.
   */
  protected function cleanFormDisplays(): void {
    $display_storages = [
      'form' => $this->entityTypeManager->getStorage('entity_form_display'), 
      'view' => $this->entityTypeManager->getStorage('entity_view_display')
    ];

    foreach ($display_storages as $mode => $storage) {
      $displays = $storage->loadMultiple();

      foreach ($displays as $display) {
        $changed = FALSE;
        $content = $display->get('content');

        foreach ($content as $field_name => $settings) {
          if (str_starts_with($field_name, 'computed_relationshipfield__')) {
            unset($content[$field_name]);
            $changed = TRUE;
          } elseif (!empty($settings['type']) && $settings['type'] === 'relation_extended_ief_widget') {
            $content[$field_name]['type'] = 'entity_reference_autocomplete';
            $content[$field_name]['settings'] = [
              'match_operator' => 'CONTAINS',
              'size' => 60,
              'placeholder' => '',
            ];
            $changed = TRUE;
          }
        }
        if ($changed) {
          $display->set('content', $content);
          $display->save();
        }
      }
    }
  }


  /**
   * Unsets third-party settings for a config entity.
   *
   * @param ConfigEntityBase $entity
   *   The config entity.
   */
  protected function unsetRnThirdPartySettings(ConfigEntityBase $entity): void {
    $rn_settings = $entity->getThirdPartySettings('relationship_nodes');
    foreach ($rn_settings as $key => $value) {
      $entity->unsetThirdPartySetting('relationship_nodes', $key);
    }
    if (method_exists($entity, 'setLocked')) {
      $entity->setLocked(FALSE);
    }
    $entity->save();
  }
}