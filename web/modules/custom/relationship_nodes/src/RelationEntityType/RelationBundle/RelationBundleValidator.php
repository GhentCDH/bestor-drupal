<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
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


    public function validateRelationConfig(ConfigEntityBundleBase $entity): ?string {
        $errors = $this->collectValidationErrors($entity);
        if (empty($errors)) {
            return null;
        }
        return $this->formatValidationErrorsForConfig($entity, $errors);
    }


    public function validateFieldSettings(FieldConfig $field_config): bool {
        $storage = $field_config->getFieldStorageDefinition();
        if(!$storage instanceof FieldStorageConfig || !$this->validateFieldStorageConfig($storage)){
            return false;
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

        $storage_errors = $this->validateAllFieldStorageConfig() ?? [];
        $all_errors = array_merge($all_errors, $storage_errors);
        
        return $all_errors;
    }   


    protected function validateAllFieldStorageConfig(): array {
        $errors = [];
        $rn_fields = $this->fieldConfigurator->getAllRnCreatedFields('field_storage_config');
        
        foreach ($rn_fields as $field_id => $storage) {
            if ($storage instanceof FieldStorageConfig) {
                if (!$this->validateFieldStorageConfig($storage)) {
                    $errors[] = $this->t('Field storage "@field" has invalid configuration.', [
                        '@field' => $storage->getName(),
                    ]);
                }
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
                : $entity->getThirdPartySetting('relationship_nodes', 'referencing_type', FALSE);
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
                : $entity->getThirdPartySetting('relationship_nodes', 'typed_relation', FALSE);

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
                !$this->validateFieldSettings($field_config)
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