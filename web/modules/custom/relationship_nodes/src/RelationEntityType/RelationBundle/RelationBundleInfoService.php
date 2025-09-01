<?php


namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;

class RelationBundleInfoService {

    protected EntityFieldManagerInterface $fieldManager;
    protected EntityTypeBundleInfoInterface $bundleInfo;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationBundleSettingsManager $settingsManager;


    public function __construct(
        EntityFieldManagerInterface $fieldManager,
        EntityTypeBundleInfoInterface $bundleInfo,
        FieldNameResolver $fieldNameResolver,
        RelationBundleSettingsManager $settingsManager,
    ) {
        $this->fieldManager = $fieldManager;
        $this->bundleInfo = $bundleInfo;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->settingsManager = $settingsManager;
    }

    
    public function getRelationBundleInfo(string $bundle, array $fields = []):array {
        if (empty($fields)) {
            $fields = $this->fieldManager->getFieldDefinitions('node', $bundle);
        }

        if (!$this->settingsManager->isRelationNodeType($bundle)) {
            return [];
        }

        $related_bundles = [];

        foreach ($this->fieldNameResolver->getRelatedEntityFields() as $field_name) {
            $related_bundles[$field_name] = $this->getFieldTargetBundles($fields[$field_name]);
        }

        $info = [
            'related_bundles_per_field' => $related_bundles,
            'has_relationtype' => false
        ];

        $target_bundles = $this->getFieldTargetBundles($fields[$this->fieldNameResolver->getRelationTypeField()]);            
        
        if(count($target_bundles) != 1){
            return $info;
        }

        $vocab = $target_bundles[0];
        
        $vocab_info = $this->getRelationVocabInfo($vocab);
   
        if(empty($vocab_info)){
            return $info;
        }           

        $info['has_relationtype'] = true;
        $info['relationtypeinfo'] = $vocab_info;
        $info['relationtypeinfo']['vocabulary'] = $vocab;

        return $info;
    }
 

    public function getRelationVocabInfo(string $vocab, array $fields = []): array {
        if (empty($fields)) {
            $fields = $this->fieldManager->getFieldDefinitions('taxonomy_term', $vocab);
        }

        switch($this->settingsManager->getRelationVocabType($vocab)){
            case 'cross':
                $result = [
                    'mirror_field_type' => 'string',
                    'mirror_field_name' => $this->fieldNameResolver->getMirrorFields('cross'),
                    'referencing_type' => 'crossreferencing'
                ];
                break;
            case 'self':
                $result = [
                    'mirror_field_type' => 'entity_reference_selfreferencing',
                    'mirror_field_name' => $this->fieldNameResolver->getMirrorFields('self'),
                    'referencing_type' => 'selfreferencing'
                ];
                break;
            default:
                $result = [];
        }

        return $result ?? [];
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