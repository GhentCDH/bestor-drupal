<?php

namespace Drupal\relationship_nodes\RelationEntity\RelationTermMirroring;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\relationship_nodes\RelationEntity\RelationNode\ForeignKeyFieldResolver;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationFormHelper;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;



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
        if(empty($target_bundles)){
            return false;
        }
        
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

            $mirror_label = reset($this->getMirrorArray($term_storage, $term_id, $label));


      
            $mirror_options[$term_id] = $mirror_label;
            
        }
        return $mirror_options;
    }

public function getMirrorArray(TermStorageInterface $term_storage, string $term_id, string $default_label=null):array{
    $term = $term_storage->load((int) $term_id);

    if(!$term instanceof Term){
        return [$term_id => $default_label ?? ''];
    }
    
    if($default_label === null){
        $default_label = $term->getName() ?? '';

    }
    
    $result = [$term_id => $default_label];
    
    

    
    $vocab = $term->bundle();
    $vocab_type = $this->settingsManager->getRelationVocabType($vocab);

    switch ($vocab_type){
        case 'string':
            $mirror_lookup = $this->getStringMirror($term);
            break;
        case 'entity_reference':
            $mirror_lookup = $this->getReferenceMirror($term, $vocab);
            break;
        default:
            return $result;
    }

    if(!is_array($mirror_lookup)){
        return $result;
    }

    return $mirror_lookup;

}

    public function getStringMirror(Term $term):?array{
        $values = $term->get($this->fieldNameResolver->getMirrorFields('string'))->getValue();
        if(empty($values)) {
            return null;
        }
        $value = reset($values) ?? [];
        $mirror_label = $value['value'] ?? null;
        return  $mirror_label !== null ? [$term->id() => $mirror_label] : null;
    }

    public function getReferenceMirror(Term $term, string $vocab):?array{

        $values = $term->get($this->fieldNameResolver->getMirrorFields('entity_reference'))->getValue();   
        if(empty($values)){
            return null;
        }  
        $value = reset($values) ?? [];
        $id_value = $value['target_id'];
        if(empty($id_value)){
            return null;
        }
        $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $mirror_term = $term_storage->load((int) $id_value);
        if (!($mirror_term instanceof Term) || $mirror_term->bundle()!== $vocab) {
            return null;
        }

        $mirror_label = $mirror_term->getName();
        
        return $mirror_label !== null ? [$mirror_term->id() => $mirror_label] : null;
    }

}