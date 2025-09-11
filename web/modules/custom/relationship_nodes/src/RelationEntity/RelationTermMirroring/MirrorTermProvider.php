<?php

namespace Drupal\relationship_nodes\RelationEntity\RelationTermMirroring;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntity\RelationNode\ForeignKeyFieldResolver;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationFormHelper;


class MirrorTermProvider{

    protected EntityTypeManagerInterface $entityTypeManager;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationBundleSettingsManager $settingsManager;
    protected ForeignKeyFieldResolver $foreignKeyResolver;
    protected RelationFormHelper $formHelper;

  
    public function __construct(
        EntityTypeManagerInterface $entityTypeManager, 
        FieldNameResolver $fieldNameResolver, 
        RelationBundleSettingsManager $settingsManager, 
        ForeignKeyFieldResolver $foreignKeyResolver,
        RelationFormHelper $formHelper
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->settingsManager = $settingsManager;
        $this->foreignKeyResolver = $foreignKeyResolver;
        $this->formHelper = $formHelper; 
    }
    
    public function elementSupportsMirroring(FieldItemListInterface $items, array $form, FormStateInterface $form_state): bool {   
        if(
            !$this->formHelper->isParentFormWithIefSubforms($form, $form_state) ||
            !$this->settingsManager->isRelationNodeType($items->getEntity()->getType()) ||
            !$items->getFieldDefinition() instanceof FieldConfig
        ) {
            return false;
        }

        $field = $items->getFieldDefinition();

        if(empty($field->getSettings())){
            return false;
        }

        $field_settings = $field->getSettings();
        if(
            !isset($field_settings['target_type']) || 
            $field_settings['target_type'] != 'taxonomy_term' || 
            empty($field_settings['handler_settings']['target_bundles'])
        ){
            return false;
        }

        $target_bundles = $field_settings['handler_settings']['target_bundles'];
        $target_vocab = $this->settingsManager->ensureVocab(reset($target_bundles));
        if(
            !$target_vocab || 
            !$this->settingsManager->isRelationVocab($target_vocab) ||
            !$this->settingsManager->isMirroringVocab($target_vocab)
        ){
            return false;
        }

        return true;
    }


    public function mirroringRequired(FieldItemListInterface $items, array $form, FormStateInterface $form_state): bool {
        if(!$this->elementSupportsMirroring($items, $form, $form_state)){
            return false;
        }
        
        $foreign_key_field = $this->foreignKeyResolver->getEntityFormForeignKeyField($form, $form_state);
        
        if(!is_string($foreign_key_field) || $foreign_key_field !== $this->fieldNameResolver->getRelatedEntityFields(2)){
            return false;
        }

        return true;
    }



    public function getMirrorOptions(array $options): array {
        if(empty($options)) {
            return [];
        }

        $mirror_options = [];
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        foreach($options as $term_id => $label){

            if (!ctype_digit((string) $term_id)) {
                $mirror_options[$term_id] = $label;
                continue;
            }

            $term = $term_storage->load((int) $term_id);

            if(!$term instanceof Term){
                $mirror_options[$term_id] = $label;
                continue;
            }

            $vocab = $term->bundle();
            $mirror_field_arr = [];

            switch($this->settingsManager->getRelationVocabType($vocab )){
                case 'string':
                    $mirror_field_arr = $term->get($this->fieldNameResolver->getMirrorFields('string'))->getValue();
                    break;
                case 'entity_reference':
                    $mirror_field_arr = $term->get($this->fieldNameResolver->getMirrorFields('entity_reference'))->getValue(); 
                    break;
            }

            $mirror_label = $label;

            if(is_array($mirror_field_arr) && count($mirror_field_arr) === 1) {
                $field_val = $mirror_field_arr[0];
                if(isset($field_val['value'])){
                    $mirror_label = $field_val['value'];
                } elseif(isset($field_val['target_id'])){
                    $mirror_term = $term_storage->load((int) $field_val['target_id']);
                    if ($mirror_term instanceof Term && $mirror_term->bundle() === $vocab) {
                        $mirror_label = $mirror_term->getName();
                    }
                }
            }
            $mirror_options[$term_id] = $mirror_label;
        }
        return $mirror_options;
    }
}