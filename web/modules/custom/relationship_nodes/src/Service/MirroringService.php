<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\relationship_nodes\Service\ConfigManager;
use Drupal\relationship_nodes\Service\RelationEntityValidator;
use Drupal\relationship_nodes\Service\RelationshipInfoService;



class MirroringService{

    protected EntityTypeManagerInterface $entityTypeManager;
    protected ConfigManager $configManager;
    protected RelationEntityValidator $relationEntityValidator;
    protected RelationshipInfoService $infoService;

  
    public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigManager $configManager, RelationEntityValidator $relationEntityValidator, RelationshipInfoService $infoService) {
        $this->entityTypeManager = $entityTypeManager;
        $this->configManager = $configManager;
        $this->relationEntityValidator = $relationEntityValidator;
        $this->infoService = $infoService; 
    }
    
    public function elementSupportsMirroring($items, $form): bool {
        if(!isset($form['#type']) || $form['#type'] !== 'inline_entity_form'){
            return false;
        }
    
        $all_entity_fields = $items->getEntity()->getFields();
        $items_field = $items->getName();
        
        if(empty($all_entity_fields) || empty($items_field) || !isset($all_entity_fields[$items_field])){
            return false;
        }

        $field_config = $all_entity_fields[$items_field]->getFieldDefinition();

        if(!($field_config instanceof FieldConfig)){
            return false;
        }

        if(!$this->relationEntityValidator->hasRelationTypeTargets($field_config)){
            return false;
        }

        return true;
    }



    public function mirroringRequired(array $form, FormStateInterface $form_state): bool {
        $foreign_key_field = $this->infoService->getEntityFormForeignKeyField($form, $form_state);
        
        if(!is_string($foreign_key_field) || $foreign_key_field !== $this->configManager->getRelatedEntityFields(2)){
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

            switch($this->relationEntityValidator->identifyRelationVocab($vocab )){
                case 'cross':
                    $mirror_field_arr = $term->get($this->configManager->getMirrorFields('string'))->getValue();
                    break;
                case 'self':
                    $mirror_field_arr = $term->get($this->configManager->getMirrorFields('reference'))->getValue(); 
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