<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;


class RelationBundleValidator {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationFieldConfigurator $fieldConfigurator;
    protected RelationBundleInfoService $bundleInfoService;
    protected RelationBundleSettingsManager $settingsManager;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        FieldNameResolver $fieldNameResolver,
        RelationFieldConfigurator $fieldConfigurator,
        RelationBundleInfoService $bundleInfoService,
        RelationBundleSettingsManager $settingsManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->fieldConfigurator = $fieldConfigurator;
        $this->bundleInfoService = $bundleInfoService;
        $this->settingsManager = $settingsManager;
    }


    /**
    * Form submit validator for relation-enabled node types and vocabularies
    */
    public function validateRelationFormState(array &$form, FormStateInterface $form_state) {
        $entity = $form_state->getFormObject()->getEntity();

        if (
        !($entity instanceof NodeType || $entity instanceof Vocabulary) || 
        !$form_state->getValue(['relationship_nodes', 'enabled'])
        ) {
        return;
        }

        $errors = $this->collectValidationErrors($entity, $form_state);
        foreach ($errors as $error) {
            $form_state->setErrorByName('relationship_nodes', $error);
        }
    }


    /**
    * Config import validator
    */
    public function validateRelationConfig(ConfigEntityBundleBase $entity): ?string {
        $errors = $this->collectValidationErrors($entity);
        if (empty($errors)) {
            return null;
        }
        return $this->formatValidationErrorsForConfig($entity, $errors);
    }


    /**
    * Validates a single relation-enabled node type or vocabulary
    */
    public function validateEntityConfig(string $entity_type, string $entity_id, array $config_data, ConfigImporterEvent $event): void {
        $relation_settings = $config_data['third_party_settings']
        ['relationship_nodes'] ?? [];
        if (empty($relation_settings['enabled'])) {
            return;
        }

        $storage = $this->entityTypeManager->getStorage($entity_type);
        $entity = $storage->load($entity_id) ?: $storage->create($config_data);

        $error = $this->validateRelationConfig($entity);
        if ($error) {
            $event->getConfigImporter()->logError($error);
        }
    }


    public function validateFieldConfig(FieldConfig $field_config, bool $include_storage_validation = true): bool {
        if($include_storage_validation == true){
            $storage = $field_config->getFieldStorageDefinition();
            if(!$storage instanceof FieldStorageConfig || !$this->validateFieldStorageConfig($storage)){
                return false;
            }
        }
        $target_bundles = $field_config->getSetting('handler_settings')['target_bundles'] ?? [];
        if (
            (!empty($target_bundles) && count($target_bundles) !== 1) || (
                $field_config->getName() === $this->fieldNameResolver->getMirrorFields('self') && 
                key($target_bundles) !== $field_config->getTargetBundle()
            )
        ) {
            return false;
        }
        
        if ($field_config->getName() === $this->fieldNameResolver->getRelationTypeField()){
            foreach($target_bundles as $vocab_name => $vocab_label){
                if(!$this->settingsManager->isRelationVocab($vocab_name)){
                    return false;
                }
            }
        }
        return true;
    }


    public function validateFieldStorageConfig(FieldStorageConfig $storage){
        $required_settings = $this->fieldConfigurator->getRequiredFieldConfiguration($storage->getName());
        if (
            !$required_settings ||
            $storage->getType() !== $required_settings['type'] ||
            $storage->getCardinality() != $required_settings['cardinality'] || (
                isset($required_settings['target_type']) && 
                $storage->getSetting('target_type') != $required_settings['target_type']
            )
        ){
            return false;
        }
        return true;
    }


    
    public function validateAllRelationConfig(): array {
        $all_errors = [];
        
        foreach ($this->bundleInfoService->getAllRelationEntityTypes() as $entity) {
            $error = $this->validateRelationConfig($entity);
            if ($error) {
                $all_errors[] = $error;
            }
        }

        $storage_errors = $this->validateAllRelationFieldConfig() ?? [];
        $all_errors = array_merge($all_errors, $storage_errors);
        
        return $all_errors;
    }   


    protected function validateAllRelationFieldConfig(): array {
        $errors = [];
        $rn_fields = $this->fieldConfigurator->getAllRnCreatedFields();
        
        foreach ($rn_fields as $field_id => $field) {
            $field_name = $field->getName();
            if ($field instanceof FieldStorageConfig && !$this->validateFieldStorageConfig($field)) {
                $errors[] = $this->t('Field storage "@field" has invalid configuration.', [
                    '@field' => $field_name,
                ]);

            } elseif ($field instanceof FieldConfig && !$this->validateFieldConfig($field, false)){
                $errors[] = $this->t('Field config "@field" has invalid configuration.', [
                    '@field' => $field_name,
                ]);
            }
            if (!in_array($field_name, $this->fieldNameResolver->getAllRelationFieldNames())){
                $errors[] = $this->t('Field "@field" has Relationship Nodes configuration, but is not a dedicated field of this module.', [
                    '@field' => $field_name,
                ]);
            }
        }
        return $errors;
    }


    protected function collectValidationErrors(ConfigEntityBundleBase $entity, ?FormStateInterface $form_state = NULL): array {
        $errors = [];
        
        if ($entity instanceof Vocabulary) {
            if (!$this->validRelationVocabConfig()){
                $errors[] = $this->t('Vocabulary @id is missing valid mirror fields config.', [
                    '@id' => $entity->id(),
                ]);
            }
            $vocab_referencing_type = $form_state 
                ? $form_state->getValue(['relationship_nodes', 'referencing_type']) 
                : $this->settingsManager->getProperty($entity, 'referencing_type');
            if(!$vocab_referencing_type){
                $errors[] = $this->t('Vocabulary @id has no referencing type (which is required).', [
                    '@id' => $entity->id(),
                ]);
            }
        } elseif ($entity instanceof NodeType) {
            if (!$this->validBasicRelationConfig()) {
            $errors[] = $this->t('Node type @id is missing valid related entity fields config.', [
                '@id' => $entity->id(),
            ]);
            }
            $typed_relation_enabled = $form_state
                ? $form_state->getValue(['relationship_nodes', 'typed_relation'])
                : $this->settingsManager->getProperty($entity, 'typed_relation');

            if ($typed_relation_enabled && !$this->validTypedRelationConfig()) {
                $errors[] = $this->t('Node type @id is missing valid typed relation config.', [
                    '@id' => $entity->id(),
                ]);
            }
        }

        $existing = $this->fieldConfigurator->getFieldStatus($entity)['existing'] ?? [];
        foreach ($existing as $field_name => $field_arr) {
            $field_config = $field_arr['field_config'];
            if (
                !$field_config instanceof FieldConfig ||
                !$this->validateFieldConfig($field_config)
            ) {
            $errors[] = $this->t('Field @field in bundle @bundle is misconfigured.', [
                '@field' => $field_name,
                '@bundle' => $entity->id(),
            ]);
            }
        }

        return $errors;
    }


    protected function validBasicRelationConfig(): bool{  
        if(!$this->validChildFieldConfig($this->fieldNameResolver->getRelatedEntityFields(), 'related_entity_fields')){
            return false;
        }
        return true;
    }


    protected function validTypedRelationConfig(): bool{
        if (empty($this->fieldNameResolver->getRelationTypeField()) || !$this->validRelationVocabConfig()) {
            return false;
        }  
        return true;
    }


    protected function validRelationVocabConfig():bool{
        if(!$this->validChildFieldConfig($this->fieldNameResolver->getMirrorFields(), 'mirror_fields')){
            return false;
        }
        return true;
    }


    protected function validChildFieldConfig(array $array, string $parent_key): bool {
        if (!is_array($array)) {
            return false;
        }
        $subfields = $this->fieldNameResolver->getConfig()->get($parent_key);
        if (empty($subfields) || !is_array($subfields)) {
            return false;
        }

        foreach (array_keys($subfields) as $subfield) {
            if (!array_key_exists($subfield, $array) || empty($array[$subfield])) {
            return false;
            }
        }
        return true;
    }


    protected function formatValidationErrorsForConfig(ConfigEntityBundleBase $entity, array $errors): string {
        $entity_type = $entity->getEntityTypeId();
        $entity_id = $entity->id();
        $config_name = "{$entity_type}.{$entity_id}";
        
        $message = "Validation errors for {$config_name}:\n";
        foreach ($errors as $error) {
            $message .= "- {$error}\n";
        }
        return rtrim($message, "\n");
    }
}