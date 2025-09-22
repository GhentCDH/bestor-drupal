<?php

namespace Drupal\relationship_nodes\RelationEntityType;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;


class RelationSettingsCleanUpService {
        
    protected EntityTypeManagerInterface $entityTypeManager;
    protected RelationBundleInfoService $bundleInfoService;
    protected RelationFieldConfigurator $fieldConfigurator;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        RelationBundleInfoService $bundleInfoService, 
        RelationFieldConfigurator $fieldConfigurator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->bundleInfoService = $bundleInfoService;
        $this->fieldConfigurator = $fieldConfigurator;
    }

     public function removeModuleSettings(): void {
        $this->unsetRnEntitySettings();
        $this->cleanFormDisplays();
     }


    protected function unsetRnEntitySettings(): void {
        try {
            foreach ( $this->bundleInfoService->getAllRelationBundles() as $entity_type) {
                $updated = $this->unsetRnThirdPartySettings($entity_type) ?? [];
            }
            foreach ($this->fieldConfigurator->getAllRnCreatedFields() as $field) {
                $updated = $this->unsetRnThirdPartySettings($field) ?? [];
            }
            \Drupal::cache()->deleteAll();
        } catch (\Exception $e) {
            \Drupal::logger('relationship_nodes')->error('Error cleaning up Relationship Nodes data: @error', [
                '@error' => $e->getMessage(),
            ]);
        }
    }


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