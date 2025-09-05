<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;

class RelationSettingsCleanUpService {
        
    protected RelationBundleInfoService $bundleInfoService;
    protected RelationBundleSettingsManager $settingsManager;
    protected RelationFieldConfigurator $fieldConfigurator;

    public function __construct(
        RelationBundleInfoService $bundleInfoService,
        RelationBundleSettingsManager $settingsManager, 
        RelationFieldConfigurator $fieldConfigurator
    ) {
        $this->bundleInfoService = $bundleInfoService;
        $this->settingsManager = $settingsManager;
        $this->fieldConfigurator = $fieldConfigurator;
    }


    public function cleanupModuleData(): void {
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
}