<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class RelationSettingsCleanUpService {
        
    protected EntityTypeManagerInterface $entityTypeManager;
    protected RelationBundleInfoService $bundleInfoService;
    protected RelationBundleSettingsManager $settingsManager;
    protected RelationFieldConfigurator $fieldConfigurator;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        RelationBundleInfoService $bundleInfoService,
        RelationBundleSettingsManager $settingsManager, 
        RelationFieldConfigurator $fieldConfigurator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->bundleInfoService = $bundleInfoService;
        $this->settingsManager = $settingsManager;
        $this->fieldConfigurator = $fieldConfigurator;
    }


    public function removeModuleSettings(): void {
        try {
            foreach ( $this->bundleInfoService->getAllRelationEntityTypes() as $entity_type) {
                $this->settingsManager->removeRnThirdPartySettings($entity_type);
            }

            foreach ($this->fieldConfigurator->getAllRnCreatedFields() as $field) {
                $this->settingsManager->removeRnThirdPartySettings($field);
            }

            \Drupal::cache()->deleteAll();
        } catch (\Exception $e) {
            \Drupal::logger('relationship_nodes')->error('Error cleaning up Relationship Nodes data: @error', [
                '@error' => $e->getMessage(),
            ]);
        }
    }


    public function cleanFormDisplays(): void {
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
                    } elseif (!empty($settings['type']) && $settings['type'] === 'ief_validated_relations_simple') {
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
}