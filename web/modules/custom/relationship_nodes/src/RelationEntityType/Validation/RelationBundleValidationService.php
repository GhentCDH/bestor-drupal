<?php

namespace Drupal\relationship_nodes\RelationEntityType\Validation;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationValidationObjectFactory;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationBundleValidationObject;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationFieldConfigValidationObject;
use Drupal\relationship_nodes\RelationEntityType\Validation\RelationFieldStorageValidationObject;


class RelationBundleValidationService {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationFieldConfigurator $fieldConfigurator;
    protected RelationBundleInfoService $bundleInfoService;
    protected RelationBundleSettingsManager $settingsManager;
    protected RelationValidationObjectFactory $validationFactory;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        FieldNameResolver $fieldNameResolver,
        RelationFieldConfigurator $fieldConfigurator,
        RelationBundleInfoService $bundleInfoService,
        RelationBundleSettingsManager $settingsManager,
        RelationValidationObjectFactory $validationFactory
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->fieldConfigurator = $fieldConfigurator;
        $this->bundleInfoService = $bundleInfoService;
        $this->settingsManager = $settingsManager;
        $this->validationFactory = $validationFactory;
    }


    private const ERROR_MESSAGES = [
        'invalid_entity_type' => 'Invalid entity type for relation configuration: only node_type and taxonomy_vocabulary are allowed.',
        'missing_field_name_config' => 'The relationship node module lacks the required field name configuration. (Cf. config/install.)',
        'multiple_target_bundles' => 'Field "@field" in bundle "@bundle" can only target one bundle.',
        'field_cannot_be_required' => 'Field "@field" in bundle "@bundle" cannot be required due to IEF widget conflicts.',
        'mirror_field_bundle_mismatch' => 'Mirror field "@field" in bundle "@bundle" must reference the same vocabulary.',
        'relation_type_field_no_targets' => 'Relation type field "@field" in bundle "@bundle" must have target bundles.',
        'invalid_relation_vocabulary' => 'Field "@field" in bundle "@bundle" targets invalid relation vocabulary.',
        'invalid_field_type' => 'Field "@field" has an invalid field type.',
        'invalid_cardinality' => 'Field "@field" has an invalid cardinality.',
        'invalid_target_type' => 'Field "@field" has an invalid target type.',
        'orphaned_rn_field_settings' => 'Field "@field" has relation settings but is not in module configuration.',
        'missing_field_config' => 'Field "@field" exists, but its field configuration is not found.',
        'missing_config_file_data' => 'Field "@field" has a configuration file, but its content cannot be found.',
        'no_field_storage' => 'The field "@field" of bundle "@bundle" has no valid field storage.',
    ];


    /**
     * 1. VALIDATE A SINGLE RELATION BUNDLE (vocab or node type)
     */
    
    /**
     * 1.1. entity validator (for node types and vocabs)
     */
    public function getBundleValidationErrors(ConfigEntityBundleBase $entity):array {
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
    public function getFormStateValidationErrors(FormStateInterface $form_state):array {
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
        $field_errors = $this->validateEntityExistingFields($entity);
        return array_merge($errors, $field_errors);
    }


    public function displayFormStateValidationErrors(array &$form, FormStateInterface $form_state):void{
        $errors = $this->getFormStateValidationErrors($form_state);
        if(empty($errors)){
            return;
        }

        foreach ($errors as $error) {
            $form_state->setErrorByName('relationship_nodes', $this->errorCodeToMessage($error['error_code'], $error['context']));      
        }
    }


    /**
    * 1.3. Validates a single entity config import file
    */
    public function getBundleCimValidationErrors(string $config_name, StorageInterface $storage): array {
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

        $error_message = $this->formatValidationErrors($config_name, $errors);

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
    * 2.2. Validates a single field config (with or without its storage)
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
    * 2.3. Validates a single field config import file
    */
    public function getFieldConfigCimValidationErrors(array $config_data): array {
        $errors = []; 
        $validator = $this->validationFactory->fromFieldConfigConfigFile($config_data);
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
    protected function validateEntityExistingFields($entity): array{
        $errors = [];
        $existing_fields = $this->fieldConfigurator->getBundleFieldsStatus($entity)['existing'];
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
        $existing_fields = $this->fieldConfigurator->getCimFieldsStatus($config_name, $storage)['existing'];
        foreach($existing_fields as $field => $field_info){
            if(!isset($field_info['config_file_data'])){
                $entity_classes = $this->settingsManager->getConfigFileEntityClasses($config_name);
                $errors[] = [
                    'error_code' => 'missing_config_file_data',
                    'context' => [
                        '@field' => $field,
                        '@bundle' => !empty($entity_classes['bundle']) ? $entity_classes['bundle'] : '',
                    ]
                ];
                continue;
            }
            $field_errors = $this->getFieldConfigCimValidationErrors($field_info['config_file_data']);
            if(!empty($field_errors)){
                $errors = array_merge($errors, $field_errors);
            }
        }
        return $errors; 
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


    /*
    * 4.3 Validates all the required config of this module: nodetypes, vocabs, fields (both storage and config)
    */    
    public function validateAllRelationConfig(): array {      
        return array_merge(
           $this->validateAllRelationBundles(), 
           $this->validateAllRelationFields()
        );
    }

  
    /*
    * 5. ERROR MESSAGE FORMATTING
    */

    protected function errorCodeToMessage(string $error_code, array $context): string{
        $error_message = self::ERROR_MESSAGES[$error_code] ?? $error_code;
        return $this->t($error_message, $context);
    }


    protected function formatValidationErrors(string $name, array $errors): string {
        $message = "Validation errors for {$name}:\n";
        foreach ($errors as $error) {
            if (isset($error['error_code']) && isset($error['context'])) {
                $message .= "- " . $this->errorCodeToMessage($error['error_code'], $error['context']) . "\n";   
            }   
        }  
        return rtrim($message, "\n");
    }
}