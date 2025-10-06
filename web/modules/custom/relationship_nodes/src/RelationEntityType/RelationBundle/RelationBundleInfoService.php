<?php


namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigStorage;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;

class RelationBundleInfoService {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected EntityFieldManagerInterface $fieldManager;
    protected EntityTypeBundleInfoInterface $bundleInfo;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationBundleSettingsManager $settingsManager;
    protected RelationFieldConfigurator $fieldConfigurator;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $fieldManager,
        EntityTypeBundleInfoInterface $bundleInfo,
        FieldNameResolver $fieldNameResolver,
        RelationBundleSettingsManager $settingsManager,
        RelationFieldConfigurator $fieldConfigurator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldManager = $fieldManager;
        $this->bundleInfo = $bundleInfo;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->settingsManager = $settingsManager;
        $this->fieldConfigurator = $fieldConfigurator;
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
            if(!isset($fields[$field_name])){ 
                continue;
            }
            $related_bundles[$field_name] = $this->getFieldTargetBundles($fields[$field_name]);
        }

        $info = [
            'related_bundles_per_field' => $related_bundles,
            'has_relationtype' => false
        ];

        if(!$this->settingsManager->isTypedRelationNodeType($bundle)){
            return $info;
        }

        $target_bundles = $this->getFieldTargetBundles($fields[$this->fieldNameResolver->getRelationTypeField()]);            
        
        if(count($target_bundles) != 1){
            return $info;
        }

        $vocab = reset($target_bundles);
        
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


    public function getAllRelationBundles(?string $entity_type_id = null):array{
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


    public function getAllCimRelationBundles(StorageInterface $config_storage, ?string $entity_type_id = null):array{
        $entity_types = ['node_type', 'taxonomy_vocabulary'];
        if($entity_type_id !== null  && !in_array($entity_type_id, $entity_types)){
            return [];
        }

        $input = $entity_type_id !== null ? [$entity_type_id] : $entity_types;

        $result = []; 
        foreach($input as $entity_type){
            $prefix = $this->settingsManager->getEntityTypeConfigPrefix($entity_type);
            $all = $config_storage->listAll($prefix);
            foreach ($all as $config_name) {
                $config_data = $config_storage->read($config_name);
                if($this->settingsManager->isCimRelationEntity($config_data)){
                    $result[$config_name] = $config_data;
                }
            }
        }
        return $result;
    }


    public function getAllTypedRelationNodeTypes():array{
        $result = [];
        $relation_node_types = $this->getAllRelationBundles('node_type');
        foreach($relation_node_types as $bundle_id => $node_type){
            if($this->settingsManager->isTypedRelationNodeType($node_type)){
                $result[$bundle_id] = $node_type;
            }
        }
        return $result;
    }


    public function getAllCimTypedRelationNodeTypes(StorageInterface $config_storage):array{
        $result = [];
        $all_cim_bundles = $this->getAllCimRelationBundles($config_storage, 'node_type');
        foreach($all_cim_bundles as $config_name => $config_data){
            if($this->settingsManager->isCimTypedRelationNodeType($config_data)){
                $result[$config_name] = $config_data;
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
        
        return is_array($target_bundles) ? $target_bundles : [];
    }


    public function getNodeTypesLinkedToVocab(ConfigEntityBundleBase $vocab):array{
        if(!$this->settingsManager->isRelationVocab($vocab)){
            return [];
        }

        $node_types = $this->getAllTypedRelationNodeTypes();


        if(empty($node_types)){
            return [];
        }
        
        $relation_type_field = $this->fieldNameResolver->getRelationTypeField() ?? null;
        $field_storage = $this->entityTypeManager->getStorage('field_config') ?? null;

        if(!is_string($relation_type_field) || !($field_storage instanceof FieldConfigStorage)){
            return [];
        }
        $result = [];
        foreach($node_types as $node_type_id => $node_type){
            $field_config = $field_storage->load("node.{$node_type->id()}.$relation_type_field");
            if(!($field_config instanceof FieldConfig)){
                continue;
            }
            $target_bundle = reset($this->getFieldTargetBundles($field_config));
            if($target_bundle === $vocab->id()){
                $result[$node_type_id] = $node_type;
            }
        }
        return $result;
    }


    public function getAllCimRelationVocabs(StorageInterface $storage, string $type=null):array{
        $all_vocabs = $this->getAllCimRelationBundles($storage, 'taxonomy_vocabulary') ?? [];
        if($type === null){
            return $all_vocabs;
        }
        $result = [];
        foreach($all_vocabs as $config_name => $config_data){
            if($this->settingsManager->getCimProperty($config_data, 'referencing_type') === $type){
                $result[$config_name] = $config_data;
            }
        }
        return $result;
    }



    public function getCimNodeTypesLinkedToVocab(string $config_name, StorageInterface $storage):array{
        $entity_classes = $this->settingsManager->getConfigFileEntityClasses($config_name);
        if(empty($entity_classes['entity_type_id']) || $entity_classes['entity_type_id'] !== 'taxonomy_vocabulary'){
            return [];
        } 

        $node_types = $this->getAllCimTypedRelationNodeTypes($storage);
        if(empty($node_types)){
            return [];
        }
        
        $relation_type_field = $this->fieldNameResolver->getRelationTypeField() ?? null;

        if(!is_string($relation_type_field)){
            return [];
        }
        $result = [];
        
        foreach($node_types as $node_config_name => $node_config_data){
            $node_classes = $this->settingsManager->getConfigFileEntityClasses($node_config_name);
            $field_prefix = $this->fieldConfigurator->getFieldConfigNamePrefix(
                'node',
                $node_classes['bundle'],
                true
            );

            $field_config = $storage->read($field_prefix . $relation_type_field);
            
            if(!($field_config) || empty($field_config['settings']['handler_settings']['target_bundles'])){
                continue;
            }
            
            $target_bundle = reset($field_config['settings']['handler_settings']['target_bundles']);
            if($target_bundle === $vocab->id()){
                $result[$node_config_name] = $node_config_data;
            }
        }
        return $result;
    }
}