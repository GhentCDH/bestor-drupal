<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationValidationObjectFactory;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationBundleValidationObject;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationFieldConfigValidationObject;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationFieldStorageValidationObject;
use Drupal\relationship_nodes\RelationEntityType\RelationBundleRelationBundleInfoService;
use Drupal\relationship_nodes\RelationEntityType\Validation\ValidationErrorFormatter;


class RelationValidationService {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationFieldConfigurator $fieldConfigurator;
    protected RelationBundleInfoService $bundleInfoService;
    protected RelationBundleSettingsManager $settingsManager;
    protected RelationValidationObjectFactory $validationFactory;
    protected ValidationErrorFormatter $errorFormatter;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        FieldNameResolver $fieldNameResolver,
        RelationFieldConfigurator $fieldConfigurator,
        RelationBundleInfoService $bundleInfoService,
        RelationBundleSettingsManager $settingsManager,
        RelationValidationObjectFactory $validationFactory,
        ValidationErrorFormatter $errorFormatter
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->fieldConfigurator = $fieldConfigurator;
        $this->bundleInfoService = $bundleInfoService;
        $this->settingsManager = $settingsManager;
        $this->validationFactory = $validationFactory;
        $this->errorFormatter = $errorFormatter;
    }


    /**
     * 1. VALIDATE A SINGLE RELATION BUNDLE (vocab or node type)
     */
    
    /**
     * 1.1. entity validator (for node types and vocabs)
     */
    protected function getBundleValidationErrors(ConfigEntityBundleBase $entity):array {
        $errors = [];
        $validator = $this->validationFactory->fromEntity($entity);
        if(!$validator instanceof RelationBundleValidationObject){
            return [];
        }
        if(!$validator->validate()){
            foreach($validator->getErrors() as $error_code){
                $errors[] = [
                    'error_code' => $error_code,
                    'context' => [
                        '@bundle' => $entity->id()
                    ]
                ];
            }
        }

        $field_errors = $this->validateEntityExistingFields($entity);
        return array_merge($errors, $field_errors);
    }
    
    
    /**
    * 1.2. Form submit validator for relation-enabled node types and vocabularies
    */
    protected function getFormStateValidationErrors(FormStateInterface $form_state):array {
        $errors = [];

        $validator = $this->validationFactory->fromFormState($form_state);
        if(!$validator instanceof RelationBundleValidationObject){
            return [];
        }

        $entity = $form_state->getFormObject()->getEntity();
        if(!$validator->validate()){
            foreach($validator->getErrors() as $error_code){
                $errors[] = [
                    'error_code' => $error_code,
                    'context' => [
                        '@bundle' => $entity->id()
                    ]
                ];
            }
        }    
        $rn_settings = $form_state->getValue('relationship_nodes') ?? null;
        $field_errors = $this->validateEntityExistingFields($entity, $rn_settings);
        return array_merge($errors, $field_errors);
    }


    public function displayFormStateValidationErrors(array &$form, FormStateInterface $form_state):void{
        $errors = $this->getFormStateValidationErrors($form_state);
        if(empty($errors)){
            return;
        }

        $error_message = $this->errorFormatter->formatValidationErrors($form_state->getFormObject()->getEntity()->id(), $errors);


        $form_state->setErrorByName('relationship_nodes', $error_message);      
        
    }


    /**
    * 1.3. Validates a single entity config import file
    */
    protected function getBundleCimValidationErrors(string $config_name, StorageInterface $storage): array {
        $errors = []; 
        $validator = $this->validationFactory->fromBundleConfigFile($config_name, $storage);
        if(!$validator instanceof RelationBundleValidationObject){
            return [];
        }
        if(!$validator->validate()){
            foreach($validator->getErrors() as $error_code){
                $entity_classes = $this->settingsManager->getConfigFileEntityClasses($config_name);
                $errors[] = [
                        'error_code' => $error_code,
                        'context' => ['@bundle' => !empty($entity_classes['bundle']) ? $entity_classes['bundle'] : '']
                    ];
            }
        } 
        $field_errors = $this->validateCimExistingFields($config_name, $storage);
        return array_merge($errors, $field_errors);
    }


    public function displayBundleCimValidationErrors(string $config_name, ConfigImporterEvent $event, StorageInterface $storage): void {
        $errors = $this->getBundleCimValidationErrors($config_name, $storage);
        if(empty($errors)){
            return;
        }

        $error_message = $this->errorFormatter->formatValidationErrors($config_name, $errors);

        $event->getConfigImporter()->logError($error_message); 
    }



    /**
     * 2. VALIDATE A SINGLE FIELD (field storage config or field config)
     */

    /**
    * 2.1. Validates a single field storage
    */
    public function getFieldStorageValidationErrors(FieldStorageConfig $storage):array{
        $errors = [];
        $validator = $this->validationFactory->fromFieldStorage($storage);
        if(!$validator instanceof RelationFieldStorageValidationObject){
            return [];
        }
        if(!$validator->validate()){
            foreach($validator->getErrors() as $error_code){
                $errors[] = [
                    'error_code' => $error_code,
                    'context' => [
                        '@field' =>  $storage->getName(),
                    ]
                ];
            }
        } 
        return $errors;   
    }


    /**
    * 2.2. Validates a single field storage import file
    */
    public function getFieldStorageCimValidationErrors(array $config_data):array{
        $errors = [];
        $validator = $this->validationFactory->fromFieldStorageConfigFile($config_data);
        if(!$validator instanceof RelationFieldStorageValidationObject){
            return [];
        }
        if(!$validator->validate()){
            foreach($validator->getErrors() as $error_code){
                $errors[] = [
                    'error_code' => $error_code,
                    'context' => [
                        '@field' => !empty($config_data['field_name']) ? $config_data['field_name'] : '',
                    ]
                ];
            }
        } 
        return $errors;   
    }


    /**
    * 2.3. Validates a single field config (with or without its storage)
    */
    public function getFieldConfigValidationErrors(FieldConfig $field_config, bool $include_storage_validation = true): array {
        $errors = []; 
        $context = [
            '@field' =>  $field_config->getName(),
            '@bundle' => $field_config->getTargetBundle()
        ];
        if($include_storage_validation == true){
            $storage = $field_config->getFieldStorageDefinition();
            if(!$storage instanceof FieldStorageConfig){
                $errors[] = [
                    'error_code' => 'no_field_storage',
                    'context' => $context
                ];
                return $errors;
            }
            $storage_errors = $this->getFieldStorageValidationErrors($storage);
            if(!empty($storage_errors)){
                $errors = $storage_errors;
            }
        }

        $validator = $this->validationFactory->fromFieldConfig($field_config);
        if(!$validator instanceof RelationFieldConfigValidationObject){
            return $errors;
        }
        if(!$validator->validate()){
            foreach($validator->getErrors() as $error_code){
                $errors[] = [
                        'error_code' => $error_code,
                        'context' => $context 
                ];
            }
        }   
        
        return $errors;
    }


    /**
    * 2.4. Validates a single field config import file
    */
    protected function getFieldConfigCimValidationErrors(array $config_data, StorageInterface $storage): array {
        $errors = []; 
        $validator = $this->validationFactory->fromFieldConfigConfigFile($config_data, $storage);
        if(!$validator instanceof RelationFieldConfigValidationObject){
            return [];
        }
        if(!$validator->validate()){
            foreach($validator->getErrors() as $error_code){
                $errors[] = [
                    'error_code' => $error_code,
                    'context' => [
                        '@bundle' =>  $config_data['bundle'],
                        '@field' =>  $config_data['field_name'],
                    ]
                ];
            }
        } 
        return $errors; 
    }


    /**
    * 3. VALIDATE EXISTING ENTITIES
    */

    /**
    * 3.1. Validates a node type/vocab's existing fields
    */
    protected function validateEntityExistingFields(ConfigEntityBundleBase $entity, ?array $rn_settings = null): array{
        $errors = [];
        $existing_fields = $this->fieldConfigurator->getBundleFieldsStatus($entity, $rn_settings)['existing'];
        foreach($existing_fields as $field => $field_info){
            if(!isset($field_info['field_config'])){
                $errors[] = [
                    'error_code' => 'missing_field_config',
                    'context' => [
                        '@field' => $field,
                        '@bundle' => $entity->id()
                    ]
                ];
                continue;
            }
            $field_errors = $this->getFieldConfigValidationErrors($field_info['field_config']);
            if(!empty($field_errors)){
                $errors = array_merge($errors, $field_errors);
            }
        }
        return $errors; 
    }

    /**
    * 3.2. Validates the fields in a config import
    */
    protected function validateCimExistingFields(string $config_name, StorageInterface $storage):array{
        $errors = [];
        $existing_fields = $this->fieldConfigurator->getCimFieldsStatus($config_name, $storage)['existing'] ?? [];
        foreach($existing_fields as $field => $field_info){
            $entity_classes = $this->settingsManager->getConfigFileEntityClasses($config_name);
            $error_context = [
                '@field' => $field,
                '@bundle' => !empty($entity_classes['bundle']) ? $entity_classes['bundle'] : '',
            ];
            if(!isset($field_info['config_file_data'])){
                $errors[] = [
                    'error_code' => 'missing_config_file_data',
                    'context' => $error_context
                ];
                continue;
            }

            $field_storage_config = $this->getFieldStorageCimForFieldCim($field_info['config_file_data'], $storage);
            if(!empty($field_storage_config)){
                $field_storage_errors = $this->getFieldStorageCimValidationErrors($field_storage_config);
                if(!empty($field_storage_errors)){
                    $errors = array_merge($errors, $field_storage_errors);
                }  
            } else {
                $errors[] = [
                    'error_code' => 'no_field_storage',
                    'context' => $error_context
                ];
            }
            

            $field_errors = $this->getFieldConfigCimValidationErrors($field_info['config_file_data'], $storage);
            if(!empty($field_errors)){
                $errors = array_merge($errors, $field_errors);
            }
        }
        return $errors; 
    }


    public function validateCimFieldDependencies(string $config_name, StorageInterface $storage){

        if(!empty($storage->read($config_name))){
            return [];
        }

        $field_info = $this->fieldConfigurator->getConfigFileFieldClasses($config_name);

        if(!$field_info){
            return [[
                'error_code' => 'no_field_config_file',
                'context' => []
            ]];
        }

        $field_name =  $field_info['field_name'];    
        if($field_info['field_entity_class'] === 'storage'){
            if(!empty($this->getFieldCimForFieldStorageCim($config_name, $storage))){
                return [[
                    'error_code' => 'no_field_storage',
                    'context' => ['@field' => $field_name]
                ]];
            }
            return [];          
        } 
        

        $entity_type_id = $field_info['entity_type_id'];

        if(!in_array($entity_type_id, ['node_type', 'taxonomy_vocabulary'])){
            return [];
        }

        $bundles_to_check = [];
        if($field_name == $this->fieldNameResolver->getRelationTypeField()){
            $bundles_to_check = $this->bundleInfoService->getAllCimTypedRelationNodeTypes($storage);
        } elseif(in_array($field_name, $this->fieldNameResolver->getRelatedEntityFields())) {
            $bundles_to_check = $this->bundleInfoService->getAllCimRelationBundles($storage, $entity_type_id);
        } elseif($field_name == $this->fieldNameResolver->getMirrorFields('string')){
            $bundles_to_check = $this->bundleInfoService->getAllCimRelationVocabs($storage, 'string');
        } elseif($field_name == $this->fieldNameResolver->getMirrorFields('entity_reference')){
            $bundles_to_check = $this->bundleInfoService->getAllCimRelationVocabs($storage, 'entity_reference');
        }
        $removed_fields_bundle =  $field_info['bundle'];
        $errors = [];
        foreach($bundles_to_check as $config_name => $config_data){
            $existing_rn_bundle = $this->settingsManager->getConfigFileEntityClasses($config_name)['bundle'];
            
            if($existing_rn_bundle == $removed_fields_bundle){
                $errors[] = [
                    'error_code' => 'field_has_dependency',
                    'context' => [
                        '@field' => $field_name,
                        '@bundle' => $removed_fields_bundle, 
                    ]
                ];
            }
        }
        return $errors;
    }


    public function displayCimFieldDependencieValidationErrors(string $config_name, ConfigImporterEvent $event, StorageInterface $storage): void {

        $errors = $this->validateCimFieldDependencies($config_name, $storage);

        if(empty($errors)){
            return;
        }

        $error_message = $this->errorFormatter->formatValidationErrors($config_name, $errors);
        
        $event->getConfigImporter()->logError($error_message); 
    }

    /**
    * 4. VALIDATE ALL ENTITIES
    */

    /**
    * 4.1. Validates the config of all node types and vocabs and returns all collected errors
    */  
    protected function validateAllRelationBundles():array{
        $all_errors = [];
        
        foreach ($this->bundleInfoService->getAllRelationBundles() as $bundle_name => $entity) {
            $errors = $this->getBundleValidationErrors($entity);
            if (!empty($errors)) {
                $all_errors = array_merge($all_errors, $errors);
            }
        }
        return $all_errors;
    }


    /**
    * 4.2. Validates the config of all fields (both storage and field config) and returns all collected errors
    */
    protected function validateAllRelationFields(): array {
        $all_errors = [];
        $rn_fields = $this->fieldConfigurator->getAllRnCreatedFields();
        $relation_field_names = $this->fieldNameResolver->getAllRelationFieldNames();
        foreach ($rn_fields as $field_id => $field) {
            $field_config = false;
            $field_name = $field->getName();
            if ($field instanceof FieldStorageConfig) {
                $storage_errors = $this->getFieldStorageValidationErrors($field);
                if(!empty($storage_errors)){
                    $all_errors = array_merge($all_errors, $storage_errors);
                }   
            } elseif ($field instanceof FieldConfig){
                $field_config = true;
                $config_errors = $this->getFieldConfigValidationErrors($field);
                if(!empty($config_errors)){
                    $all_errors = array_merge($all_errors, $config_errors);
                }       
            }
            if (!in_array($field_name, $relation_field_names)){
                $context = ['@field' => $field_name];
                if($field_config){
                    $context['@bundle'] = $field->getTargetBundle();
                }
                $all_errors[] = [
                    'error_code' => 'orphaned_rn_field_settings',
                    'context' => $context
                ];
            }
        }
        return $all_errors;
    }

    protected function validateAllCimRelationBundles(StorageInterface $storage):array{
        $all_errors = [];
        
        foreach ($this->bundleInfoService->getAllCimRelationBundles($storage) as $config_name => $config_data) {
            $errors = $this->getBundleCimValidationErrors($config_name, $storage);
            if (!empty($errors)) {
                $all_errors = array_merge($all_errors, $errors);
            }
        }
        return $all_errors;
    }

    protected function validateAllCimRelationFields(StorageInterface $storage): array{
        $all_errors = [];
        $rn_fields = $this->fieldConfigurator->getAllCimRnCreatedFields($storage);
        $relation_field_names = $this->fieldNameResolver->getAllRelationFieldNames();
        foreach ($rn_fields as $config_name => $config_data) {
            $field_info = $this->fieldConfigurator->getConfigFileFieldClasses($config_name);
            $field_config = false;
            if(empty($field_info) || empty($field_info['field_entity_class'])){
                return [[
                    'error_code' => 'missing_config_file_data',
                    'context' => ['@field' => $config_name]
                ]];
            }
            $field_entity_class = $field_info['field_entity_class'];
            if($field_entity_class === 'storage'){
                $storage_errors = $this->getFieldStorageCimValidationErrors($config_data);
                if(!empty($storage_errors)){
                    $all_errors = array_merge($all_errors, $storage_errors);
                }

            } elseif($field_entity_class === 'field'){
                $field_config = true;
                $config_errors = $this->getFieldConfigCimValidationErrors($config_data, $storage);
                if(!empty($config_errors)){
                    $all_errors = array_merge($all_errors, $config_errors);
                } 
            }
            $field_name = $field_info['field_name']; 
            if (!in_array($field_name, $relation_field_names)){
                $context = ['@field' => $field_name];
                if($field_config){
                    $context['@bundle'] = $config_data['bundle'];
                }
                $all_errors[] = [
                    'error_code' => 'orphaned_rn_field_settings',
                    'context' => $context
                ];
            }
        }
        return $all_errors;
    }


    /*
    * 4.3 Validates all the required config of this module: nodetypes, vocabs, fields (both storage and config)
    */    
    public function validateAllRelationConfig(): array {      
        return array_merge(
            // Validate bundles config and existing fields related to this bundle
            $this->validateAllRelationBundles(), 
            // Validate all fields marked as created by this module (find orphaned fields)
            $this->validateAllRelationFields()
        );
    }   


    public function validateAllCimRelationConfig(StorageInterface $storage): array {      
        return array_merge(
           $this->validateAllCimRelationBundles($storage), 
           $this->validateAllCimRelationFields($storage)
        );
    }   

    public function displayAllCimValidationErrors(ConfigImporterEvent $event, StorageInterface $storage): void {
        $errors = $this->validateAllCimRelationConfig($storage);
        if(empty($errors)){
            return;
        }

        $error_message = $this->errorFormatter->formatValidationErrors('Relationship nodes', $errors);
        $event->getConfigImporter()->logError($error_message); 
    }

    

    /*
    * 5. Helper function(s)
    */ 
    protected function getFieldStorageCimForFieldCim(array $field_config_data, StorageInterface $storage):?array{
        $dependency_config = [];
        if(!empty($field_config_data['dependencies']['config'])){
            $dependency_config = $field_config_data['dependencies']['config'];
        }

        $field_storage_config_name = '';
        foreach($dependency_config as $dependency){
            if(str_starts_with($dependency,'field.storage.')){
                $field_storage_config_name = $dependency;
                break;
            }
        }

        if(empty($field_storage_config_name) || empty($storage->read($field_storage_config_name))){
            return null;
        }
        
        return $storage->read($field_storage_config_name) ?? [];
    }


    protected function getFieldCimForFieldStorageCim(string $storage_config_name, StorageInterface $storage):?array{
        $dependent_field_config = [];
        $all_fields = $storage->listAll('field.field.');
        foreach($all_fields as $field){
            $field_data = $storage->read($field);
            $dependencies = $field_data['dependencies']['config'];
            if(in_array($storage_config_name, $dependencies)){
                $dependent_field_config[$field] = $field_data;
            }
        }       
        return $dependent_field_config;
    }
}    