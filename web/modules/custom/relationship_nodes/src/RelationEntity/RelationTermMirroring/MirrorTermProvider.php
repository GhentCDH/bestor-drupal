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
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationEntityFormHandler;


class MirrorTermProvider{

    protected EntityTypeManagerInterface $entityTypeManager;
    protected FieldNameResolver $fieldNameResolver;
    protected RelationBundleSettingsManager $settingsManager;
    protected ForeignKeyFieldResolver $foreignKeyResolver;
    protected RelationEntityFormHandler $relationFormHandler;

  
    public function __construct(
        EntityTypeManagerInterface $entityTypeManager, 
        FieldNameResolver $fieldNameResolver, 
        RelationBundleSettingsManager $settingsManager, 
        ForeignKeyFieldResolver $foreignKeyResolver,
        RelationEntityFormHandler $relationFormHandler
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldNameResolver = $fieldNameResolver;
        $this->settingsManager = $settingsManager;
        $this->foreignKeyResolver = $foreignKeyResolver;
        $this->relationFormHandler = $relationFormHandler; 
    }
    
    public function elementSupportsMirroring(FieldItemListInterface $items, array $form, FormStateInterface $form_state): bool {
        if(
            $this->relationFormHandler->isValidRelationParentForm($form_state) ||
            !$this->settingsManager->isRelationVocab($items->getEntity()) ||
            !in_array($items->getName(), $this->fieldNameResolver->getMirrorFields())
        ) {
            return false;
        }

        return true;
    }



    public function mirroringRequired(array $form, FormStateInterface $form_state): bool {
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
                case 'cross':
                    $mirror_field_arr = $term->get($this->fieldNameResolver->getMirrorFields('cross'))->getValue();
                    break;
                case 'self':
                    $mirror_field_arr = $term->get($this->fieldNameResolver->getMirrorFields('self'))->getValue(); 
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