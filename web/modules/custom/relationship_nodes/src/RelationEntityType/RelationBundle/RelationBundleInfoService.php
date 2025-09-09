<?php


namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;

class RelationBundleInfoService {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected EntityFieldManagerInterface $fieldManager;
    protected EntityTypeBundleInfoInterface $bundleInfo;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationBundleSettingsManager $settingsManager;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $fieldManager,
        EntityTypeBundleInfoInterface $bundleInfo,
        FieldNameResolver $fieldNameResolver,
        RelationBundleSettingsManager $settingsManager,
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldManager = $fieldManager;
        $this->bundleInfo = $bundleInfo;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->settingsManager = $settingsManager;
    }

    
    public function getRelationBundleInfo(string $bundle, array $fields = []):array {
        if (!$this->settingsManager->isRelationNodeType($bundle)) {
            return [];
        }

        if (empty($fields)) {
            $fields = $this->fieldManager->getFieldDefinitions('node', $bundle);
        }

        $related_bundles = [];


        foreach ($this->fieldNameResolver->getRelatedEntityFields() as $field_name) {
            if(!$fields[$field_name]){ 
                continue;
            }
            $related_bundles[$field_name] = $this->getFieldTargetBundles($fields[$field_name]);
        }

        $info = [
            'related_bundles_per_field' => $related_bundles,
            'has_relationtype' => false
        ];

        if(!isset($fields[$this->fieldNameResolver->getRelationTypeField()])){
            return $info;
        }
        $target_bundles = $this->getFieldTargetBundles($fields[$this->fieldNameResolver->getRelationTypeField()]);            
        
        if(count($target_bundles) != 1){
            return $info;
        }

        $vocab = $target_bundles[0];
        
        $info['has_relationtype'] = true;
        $info['vocabulary'] = $vocab;

        return $info;
    }
 

    public function getRelationInfoForTargetBundle(string $target_bundle): array { 
        $all_bundles_info = $this->bundleInfo->getBundleInfo('node');
        $relation_info = [];

        foreach($all_bundles_info as $bundle_id => $bundle_array){
            if (empty($bundle_array['relation_bundle']) || empty($bundle_array['relation_bundle']['related_bundles_per_field'])) {
                continue;
            }

            $related_bundles_per_field = $bundle_array['relation_bundle']['related_bundles_per_field'];
            $join_fields = [];
            $other_bundles = [];

            foreach($related_bundles_per_field as $field_name => $related_bundles){   
                if(in_array($target_bundle, $related_bundles)){
                    $join_fields[] = $field_name; 
                } else{
                    $other_bundles = $related_bundles;
                }
            } 

            if (empty($join_fields)) {
                continue;
            }

            $relation_info[$bundle_id] = [
                'join_fields' => $join_fields,
                'related_bundles' =>  count($join_fields) == 1 ? $other_bundles : [$target_bundle],
                'relation_bundle_info' => $bundle_array['relation_bundle'],
            ];
        }

        return $relation_info;
    }


    public function getBundleConnectionInfo(string $relation_bundle, string $target_bundle):array{
        $relation_info = $this->getRelationBundleInfo($relation_bundle);
        if(empty($relation_info) || empty($relation_info['related_bundles_per_field'])){
            return [];
        }

        $join_fields = [];
        foreach($relation_info['related_bundles_per_field'] as $field => $bundles_arr){
            if(in_array($target_bundle, $bundles_arr)){
                $join_fields[] = $field;
            }
        }

        return empty($join_fields) ? [] : ['join_fields' => $join_fields, 'relation_info' => $relation_info];
    }


    public function getAllRelationEntityTypes(?string $entity_type_id = null):array{
        $entity_types = ['node_type', 'taxonomy_vocabulary'];
        if($entity_type_id !== null  && !in_array($entity_type_id, $entity_types)){
            return [];
        }

        $input = $entity_type_id !== null ? [$entity_type_id] : $entity_types;

        $result = []; 
        foreach($input as $entity_type){
         $storage = $this->entityTypeManager->getStorage($entity_type);
            if(!$storage instanceof EntityStorageInterface){
                continue;
            }

            $all = $storage->loadMultiple();
            foreach ($all as $type) {
                if($type instanceof ConfigEntityBundleBase && $this->settingsManager->isRelationEntity($type)){            
                    $result[$type->id()] = $type;
                }
            }
        }
        return $result;
    }


    private function getFieldTargetBundles(FieldConfig $field_config):array {
        if($field_config->getType() != 'entity_reference'){
            return [];
        }

        $settings = $field_config->get('settings') ?? [];
        $handler_settings = $settings['handler_settings'] ?? [];
        $target_bundles = $handler_settings['target_bundles'] ?? [];
        
        return is_array($target_bundles) ? array_values($target_bundles) : [];
    }
}