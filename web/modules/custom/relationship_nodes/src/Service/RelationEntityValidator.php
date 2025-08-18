<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\relationship_nodes\Service\ConfigManager;
use Drupal\relationship_nodes\Service\ReferenceFieldHelper;
use Drupal\taxonomy\Entity\Vocabulary;


class RelationEntityValidator {

    protected EntityFieldManagerInterface $fieldManager;
    protected ConfigManager $configManager;
    protected ReferenceFieldHelper $referenceFieldHelper;
    
    public function __construct(EntityFieldManagerInterface $fieldManager, ConfigManager $configManager, ReferenceFieldHelper $referenceFieldHelper) {
        $this->fieldManager = $fieldManager;
        $this->configManager = $configManager;
        $this->referenceFieldHelper = $referenceFieldHelper;
    }


    public function isValidRelationBundle(string $bundle, $fields=[], bool $omit_fields_check = false): bool{
       
 
       
       
        if(!$this->configManager->validBasicRelationConfig() || !$this->nodeTypeExists($bundle)){
            return false;
        }

        $bundle_prefix = $this->configManager->getRelationBundlePrefix();
        if(!str_starts_with($bundle, $bundle_prefix)) {
            return false;
        }

        if($omit_fields_check){
            return true;
        }

        if (empty($fields)) {
            $fields = $this->fieldManager->getFieldDefinitions('node', $bundle);
        }

        foreach( $this->configManager->getRelatedEntityFields() as $related_entity_field){
            if(!isset($fields[$related_entity_field])){
                return false;
            }

            $field = $fields[$related_entity_field];

            if ($field->getType() != 'entity_reference') {
                return false;
            }

            $target_bundles = $this->referenceFieldHelper->getFieldTargetBundles($field);
           
            if(count($target_bundles) != 1 || str_starts_with($target_bundles[0], $bundle_prefix)){
                return false;
            }               
        }   

        return true;
    }



    public function isValidTypedRelationBundle(string $bundle, array $fields = []): bool{
        if(!$this->configManager->validTypedRelationConfig()){
            return false;
        }

        if (empty($fields)) {
            $fields = $this->fieldManager->getFieldDefinitions('node', $bundle);
        }
          
        if(!$this->hasRelationTypeTargets($fields[$this->configManager->getRelationTypeField()])){
                return false;
        }

        return true;
    }



    public function hasRelationTypeTargets(FieldConfig $field_config){
        $target_bundles = $this->referenceFieldHelper->getFieldTargetBundles($field_config);
        
        if(empty($target_bundles)){
            return false;
        }
        
        foreach($target_bundles as $vocab){
            if(!$this->identifyRelationVocab($vocab)){
                return false;
            }
        }

        return true;
    }


    public function identifyRelationVocab(string $vocab, array $fields = []): ?string{
        if(!$this->configManager->validTypedRelationConfig() || !$this->vocabExists($vocab)){
            return null;
        }

        $is_self = str_starts_with($vocab, $this->configManager->getRelationTypeVocabPrefixes('self'));
        $is_cross = str_starts_with($vocab, $this->configManager->getRelationTypeVocabPrefixes('cross')); 
 
        if (!$is_self && !$is_cross) {
            return null;
        }

        if (empty($fields)) {
            $fields = $this->fieldManager->getFieldDefinitions('taxonomy_term', $vocab);
        }

        $mirror_fields_config = $this->configManager->getMirrorFields();
        $mirror_reference_field = $mirror_fields_config['mirror_reference_field'];
        $mirror_string_field = $mirror_fields_config['mirror_string_field'];

        if($is_self && isset($fields[$mirror_reference_field]) && !isset($fields[$mirror_string_field])){
            $field_config = $fields[$mirror_reference_field];
        } elseif($is_cross && isset($fields[$mirror_string_field]) && !isset($fields[$mirror_reference_field])){
            $field_config = $fields[$mirror_string_field];
        } else {
            return null;
        }

        if (!($field_config instanceof FieldConfig)) {
            return null;
        }

        $field_type = $field_config->getType();
       
        if($is_self){
            $target_bundles = $this->referenceFieldHelper->getFieldTargetBundles($field_config) ?? [];
            if(!is_array($target_bundles) || !in_array($vocab, $target_bundles)){
                return null;
            }
            return 'self';
        } elseif($is_cross && $field_type == 'string'){
            return 'cross';
        } else {
            return null;
        }
    }



    public function vocabExists(string $vocab_name): bool{
        return Vocabulary::load($vocab_name) !== null;
    }



    public function nodeTypeExists(string $node_type_name): bool{
        return NodeType::load($node_type_name) !== null;
    }
}