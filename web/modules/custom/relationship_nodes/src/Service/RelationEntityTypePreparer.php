<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Link;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\field\FieldConfigStorage;

class RelationEntityTypePreparer {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }




    public function handleRelationEntitySubmission(array &$form, FormStateInterface $form_state) {
        $entity = $this->getFormEntity($form_state);
        
        if (!$entity) {
            return;
        }
        $values = $form_state->getValue('relationship_nodes') ?? [];
        $this->setRelationEntityProperties($entity, $values); 
        if (!$this->isRelationEntity($entity)) {
            return;
        }
        $fields_status = $this->getDedicatedRelationFieldsStatus($entity); 
        $existing = $fields_status['existing'];      
        $missing = $fields_status['missing'];
        $remove = $fields_status['remove'];


        if(!empty($existing)){
            $this->ensureRequiredFieldConfig($entity, $existing);
        }

        if (!empty($missing)) {
            $this->createDedicatedRelationFields($entity, $missing);
            $this->showFieldCreationMessage($entity, $missing);
        } 

        if(!empty($remove)) {
            $this->removeFieldConfig($entity, $remove);
        }     
    }

    public function removeFieldConfig(ConfigEntityBundleBase $entity, array $fields_to_remove){
        $field_config_storage = $this->entityTypeManager->getStorage('field_config');
        $entity_type_id = $this->getRelationEntityTypeId($entity);
        $bundle_name = $entity->id();
        foreach($fields_to_remove as $field_name){
            $field_config = $field_config_storage->load("$entity_type_id.{$bundle_name}.$field_name");
            $field_config->delete();
        }     
    }



    public function showFieldCreationMessage(ConfigEntityBundleBase $entity, array $missing_fields): void {
        if(empty($missing_fields)){
            return;
        }


        $url_info = $this->getDefaultRoutingInfo($this->getRelationEntityTypeId($entity));
        $url = Url::fromRoute($url_info['field_ui_fields_route'], [
            $url_info['bundle_param_key'] => $entity->id(),
        ]);

        $link = Link::fromTextAndUrl($this->t('Manage fields'), $url)->toString();

        \Drupal::messenger()->addStatus($this->t(
            'The following relationship fields were created but need to be configured: @fields. @link',
            ['@fields' => implode(', ', array_keys($missing_fields)), '@link' => $link]
        ));
    }


    public function getFormStateDedicatedRelationFields(FormStateInterface $form_state){
        if($entity instanceof NodeType && $this->isRelationNode($entity)){

        } elseif($entity instanceof Vocabulary && $this->isRelationVocab($entity)){

        }
    }

    public function getEntityDedicatedRelationFields(ConfigEntityBundleBase $entity){
        if($entity instanceof NodeType && $this->isRelationNode($entity)){

        } elseif($entity instanceof Vocabulary && $this->isRelationVocab($entity)){

        }
    }
    

    public function getDedicatedRelationFields(ConfigEntityBundleBase $entity, ?FormStateInterface $form_state = null): array {
        $enabled = $form_state 
            ? $form_state->getValue(['relationship_nodes', 'enabled']) 
            : $this->isRelationEntity($entity);

        if (empty($enabled)) {
            return [];
        }

        $fields = [];

        if ($entity instanceof NodeType) {
            $fields = [
                'related_entity_1' => ['type' => 'entity_reference', 'target_type' => 'node', 'cardinality' => 1],
                'related_entity_2' => ['type' => 'entity_reference', 'target_type' => 'node', 'cardinality' => 1],
            ];

            $typed = $form_state 
                ? !empty($form_state->getValue(['relationship_nodes', 'typed_relation'])) 
                : $this->isTypedRelationNode($entity);

            if ($typed) {
                $fields['relation_type'] = ['type' => 'entity_reference', 'target_type' => 'taxonomy_term', 'cardinality' => 1];
            }

        } elseif ($entity instanceof Vocabulary) {
            $type = $form_state 
                ? $form_state->getValue(['relationship_nodes', 'referencing_type']) 
                : $this->getRelationVocabType($entity);

            switch ($type) {
                case 'self':
                    $fields['mirror_field_self'] = ['type' => 'entity_reference', 'target_type' => 'taxonomy_term', 'cardinality' => 1];
                    break;
                case 'cross':
                    $fields['mirror_field_cross'] = ['type' => 'string', 'cardinality' => 1];
                    break;
            }
        }

        return $fields;
    }


    public function getDedicatedRelationFieldsStatus(ConfigEntityBundleBase $entity, ?FormStateInterface $form_state = null): array {
        $required = $this->getDedicatedRelationFields($entity, $form_state);
        $field_config_storage = $this->entityTypeManager->getStorage('field_config');
        $entity_type_id = $this->getRelationEntityTypeId($entity);
        $existing = [];
        $missing = [];
        $remove = [];
        foreach ($required as $field_name => $field_settings) {
            $field_config = $field_config_storage->load("$entity_type_id.{$entity->id()}.$field_name");
            if (!$field_config) {
                $missing[$field_name] = ['settings' => $field_settings];
            } else{
                $existing[$field_name] = ['settings' => $field_settings, 'field_config' => $field_config];
            }

            if($field_to_remove = $this->getIncompatibleField($field_name)){
                $field_to_remove_config = $field_config_storage->load("$entity_type_id.{$entity->id()}.$field_to_remove");
                if($field_to_remove_config){
                    $remove[] = $field_to_remove;
                }          
            } 
        }
        return ['existing' => $existing, 'missing' => $missing, 'remove' => $remove];
    }


    protected function getIncompatibleField($field_name):?string{
        switch($field_name){
            case 'mirror_field_self':
                return 'mirror_field_cross';
            case 'mirror_field_cross':
                return 'mirror_field_self';
            default:
                return null;
        }
    }

    protected function ensureRequiredFieldConfig(ConfigEntityBundleBase $entity, array $existing_fields) : void {
        foreach ($existing_fields as $field_name => $field_arr) {
            
            $field_config = $field_arr['field_config'];
            if (!$field_config->getThirdPartySetting('relationship_nodes', 'rn_created', FALSE)) {
                $field_config->setThirdPartySetting('relationship_nodes', 'rn_created', TRUE);
                $field_config->save();
            }

            $field_storage = $field_config->getFieldStorageDefinition();
            if (!$field_storage->isLocked()) {
                $field_storage->set('locked', TRUE);
                $field_storage->save();
            }
            if (!$field_storage->getThirdPartySetting('relationship_nodes', 'rn_created', FALSE)) {
                $field_storage->setThirdPartySetting('relationship_nodes', 'rn_created', TRUE);
                $field_storage->save();
            }
        }
    }

     protected function createDedicatedRelationFields(ConfigEntityBundleBase $entity, array $missing_fields) : void {

        $field_storage_config_storage = $this->entityTypeManager->getStorage('field_storage_config');
        $field_config_storage = $this->entityTypeManager->getStorage('field_config');
        $entity_type_id = $this->getRelationEntityTypeId($entity);

        foreach ($missing_fields as $field_name => $field_arr) {
            $settings = $field_arr['settings'];
            $field_storage = $field_storage_config_storage->load("$entity_type_id.$field_name");
            if (!$field_storage) {
                $field_storage = $field_storage_config_storage->create([
                    'field_name'  => $field_name,
                    'entity_type' => $entity_type_id,
                    'type'        => $settings['type'],
                    'cardinality' => $settings['cardinality'],
                    'settings'    => [
                        'target_type' => $settings['target_type'],
                        ],
                    'third_party_settings' => ['relationship_nodes' => ['rn_created'=> true]],
                ]);
                $field_storage->set('locked', true);
                $field_storage->save();
            }

            $field_config = $field_config_storage->load("$entity_type_id.{$entity->id()}.$field_name");

            if (!$field_config) {
                $field_config = $field_config_storage->create([
                    'field_name'   => $field_name,
                    'entity_type'  => $entity_type_id,
                    'bundle'       => $entity->id(),
                    'label'        => ucfirst(str_replace('_', ' ', $field_name)),
                    'required'     => true,
                    'third_party_settings' => ['relationship_nodes' => ['rn_created'=> true]],
                ]);
                $field_config->save();

            }   
        }
    }



    public function detectRelationEntityConfigConflicts(array &$form, FormStateInterface $form_state) {
        $entity = $this->getFormEntity($form_state);

        if (!$entity || !$form_state->getValue(['relationship_nodes', 'enabled'])) {
            return;
        }

        if($entity instanceof Vocabulary && empty($form_state->getValue(['relationship_nodes', 'referencing_type']))){
            $form_state->setErrorByName('relationship_nodes][referencing_type', $this->t('You must select a relation type.'));
        }

        $existing = $this->getDedicatedRelationFieldsStatus($entity, $form_state)['existing'] ?? [];
        if (empty($existing)) {
            return;
        }


        $misconfigured = [];
        foreach ($existing as $field_name => $field_arr) {
            $field_config = $field_arr['field_config'];
            if (!$field_config instanceof FieldConfig || !$this->validateRelationField($entity, $field_config, $field_arr['settings'])) {
                $misconfigured[] = $field_name;
            }
        }

        if (!empty($misconfigured)) {
            $form_state->setErrorByName('relationship_nodes', $this->t(
                'There are misconfigured relation fields: @fields. Remove or fix them first.',
                ['@fields' => implode(', ', $misconfigured)]
            ));
        }
    }



    public function validateRelationField(ConfigEntityBundleBase $entity, FieldConfig $field_config, array $required_field_settings): bool {
        $field_storage_config = $field_config->getFieldStorageDefinition();
        if (
            !$field_storage_config ||
            $field_storage_config->getType() !== $required_field_settings['type'] ||
            $field_storage_config->getCardinality() != $required_field_settings['cardinality']
        ) {
            return false;
        }

        if(isset($required_field_settings['target_type'])){
            $target_bundles = $field_config->getSetting('handler_settings')['target_bundles'] ?? [];
            if(
                $field_storage_config->getSetting('target_type') != $required_field_settings['target_type'] ||
                (!empty($target_bundles) && count($target_bundles) !== 1) ||
                ($field_config->getName() === 'mirror_field_self' && key($target_bundles) !== $entity->id())
            ){
                return false;
            }
        }
        return true;
    }






    protected function setRelationEntityProperty(ConfigEntityBundleBase $entity_type, string $property, bool $value) : void {
        $this->fillRelationEntitySetting($entity_type, $property, $value);
        $entity_type->save();
    }

    protected function setRelationEntityProperties(ConfigEntityBundleBase $entity_type, array $properties) : void {
        if(empty($properties)){
            if(!empty($this->getRelationEntityProperties($entity_type))){
                $original_properties = array_keys($this->getRelationEntityProperties($entity_type));
                foreach($original_properties as $property_to_unset){
                    $entity_type->setThirdPartySetting('relationship_nodes', $property_to_unset, 0);
                }
            }
        } else {
            foreach($properties as $property => $value){
                $this->fillRelationEntitySetting($entity_type, $property, $value);
            }
        }

        $entity_type->save();
    }


        
    public function ensureNodeType(ConfigEntityBundleBase|string $node_type):?NodeType{ 
        if(is_string($node_type)){
           $node_type = $this->entityTypeManager->getStorage('node_type')->load($node_type);
        }
        if(!$node_type instanceof NodeType){
            return null;
        }
        return $node_type;
    }

    public function ensureVocab(ConfigEntityBundleBase|string $vocab):?Vocabulary{ 
        if(is_string($vocab)){
           $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vocab);
        }
        if(!$vocab instanceof Vocabulary){
            return null;
        }
        return $vocab;
    }




    public function isRelationEntity(ConfigEntityBundleBase|string $entity): bool {
        return ($this->isRelationNode($entity) || $this->isRelationVocab($entity));
    }

    public function isRelationVocab(ConfigEntityBundleBase|string $vocab): bool{
        if(!$vocab = $this->ensureVocab($vocab)){
            return false;
        }
        $value = $this->getRelationEntityProperty($vocab, 'enabled');
        return !empty($value);
    }

    public function getRelationVocabType(Vocabulary|string $vocab): string{
        if(!$vocab = $this->ensureVocab($vocab)){
            return '';
        }
        return $this->getRelationEntityProperty($vocab, 'referencing_type') ?? '';
    }

     protected function getRelationEntityTypeId(ConfigEntityBundleBase $entity): string {
        if ($entity instanceof NodeType) {
            return 'node';
        }
        if ($entity instanceof Vocabulary) {
            return 'taxonomy_term';
        }
        return '';
    }

    public function isRelationNode(ConfigEntityBundleBase|string $node_type) : bool{
        if(!$node_type = $this->ensureNodeType($node_type)){
            return false;
        }
        $value = $this->getRelationEntityProperty($node_type, 'enabled');
        return !empty($value);
    }

    public function isTypedRelationNode(NodeType|string $node_type) : bool{
        if(!$node_type = $this->ensureNodeType($node_type)){
            return false;
        }
        $typed = $this->getRelationEntityProperty($node_type, 'typed_relation');
        return $this->isRelationNode($node_type) && !empty($typed);
    }

    public function isRnCreatedField(FieldConfig $field_config) : bool{
        return (bool) $field_config->getThirdPartySetting('relationship_nodes', 'rn_created', FALSE);
    }

    public function hasAutoTitleCreation(NodeType|string $node_type) : bool{
        if(!$node_type = $this->ensureNodeType($node_type) || !$this->isRelationNode($node_type)){
            return false;
        }
        $auto_title = $this->getRelationEntityProperty($node_type, 'auto_title');
        return !empty($auto_title);
    }


    protected function fillRelationEntitySetting(ConfigEntityBundleBase $entity_type, string $property, bool|string|null $value) : void {
        if(!$this->validRelationProperty($property)){
            return;
        }
        $entity_type->setThirdPartySetting('relationship_nodes', $property, $value);
    }


    public function getRelationEntityProperty(ConfigEntityBundleBase $entity_type, string $property) : bool|string|null {
        if(!$this->validRelationProperty($property)){
            return null;
        }
        return $entity_type->getThirdPartySetting('relationship_nodes', $property, null);
    }

    public function getRelationEntityProperties(ConfigEntityBundleBase $entity_type) : ?array {
        return $entity_type->getThirdPartySettings('relationship_nodes') ?? null;
    }


    public function validRelationProperty(string $property){
        $properties = ['rn_created', 'enabled', 'typed_relation', 'auto_title', 'referencing_type'];
        return in_array($property, $properties);
    }


    public function getFormEntity(FormStateInterface $form_state): NodeType|Vocabulary|null {
        $entity = $form_state->getFormObject()->getEntity();
        if(!($entity instanceof NodeType || $entity instanceof Vocabulary)){
             return null;
        }
        return $entity;
    }

    public function getDefaultRoutingInfo(string $entity_type_id):array{
        $mapping = [
           'node' => [
                'bundle_param_key' => 'node_type',
            ],
            'taxonomy_term' => [
                'bundle_param_key' => 'taxonomy_vocabulary',
            ]
        ];

        if(!isset($mapping[$entity_type_id])){
            return [];
        }

        return $mapping[$entity_type_id] + [
            'rn_field_edit_route' => 'relationship_nodes.relation_' . $entity_type_id . '_field_form',
            'field_edit_form_route' => 'entity.field_config.' . $entity_type_id . '_field_edit_form',
            'field_ui_fields_route' => 'entity.' . $entity_type_id . '.field_ui_fields',
            'field_edit_local_task' => 'field_ui.fields:field_edit_'. $entity_type_id,
        ];
    }

}