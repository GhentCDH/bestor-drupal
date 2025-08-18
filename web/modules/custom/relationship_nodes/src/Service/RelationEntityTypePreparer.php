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

class RelationEntityTypePreparer {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }


    public function handleNodeTypeSubmission(array &$form, FormStateInterface $form_state) {
        $node_type = $this->getFormNodeType($form_state);
        if (!($node_type instanceof NodeType)) {
            return;
        }

        $values = $form_state->getValue('relationship_nodes') ?? [];
       
        $this->setRelationEntityProperties($node_type, $values); 
        
        if (!$this->isRelationNode($node_type)) {
           return;
        }

        $missing_fields = $this->getMissingDedicatedRelationFields($node_type);
        
        if (!empty($missing_fields)) {
            $this->createDedicatedRelationFields($node_type, $missing_fields);
            $this->showFieldCreationMessage($node_type, $missing_fields);
        }     
      
    }


    public function showFieldCreationMessage(NodeType $node_type, array $missing_fields): void {
        if(empty($missing_fields)){
            return;
        }
        $url = \Drupal\Core\Url::fromRoute('entity.node.field_ui_fields', [
            'node_type' => $node_type->id()
        ]);
        $link = \Drupal\Core\Link::fromTextAndUrl($this->t('Manage fields'), $url)->toString();

        \Drupal::messenger()->addStatus($this->t(
            'The following relationship fields were created but need to be configured: @fields. @link',
            ['@fields' => implode(', ', array_keys($missing_fields)), '@link' => $link]
        ));
    }
    

    public function getDedicatedRelationFields(NodeType $node_type) : array{
        $fields = [];
        if(!$this->isRelationNode($node_type)){
            return $fields;
        }
        $fields = [
            'related_entity_1' => ['type' => 'entity_reference', 'target_type' => 'node', 'cardinality' => 1],
            'related_entity_2' => ['type' => 'entity_reference', 'target_type' => 'node', 'cardinality' => 1],
        ];
        if($this->isTypedRelationNode($node_type)){
            $fields['relation_type'] = ['type' => 'entity_reference', 'target_type' => 'taxonomy_term', 'cardinality' => 1];
        }
        return $fields;

    }

    public function getMissingDedicatedRelationFields(NodeType $node_type) : array{
        $required = $this->getDedicatedRelationFields($node_type);
        $field_config_storage = $this->entityTypeManager->getStorage('field_config');
        $missing = [];
        foreach ($required as $field_name => $field_config) {
            $field = $field_config_storage->load("node.{$node_type->id()}.$field_name");
            if (!$field) {
                $missing[$field_name] = $field_config;
            }
        }
        return $missing;
    }

    // VERDER UITWERKEN, NOT NIET WERKZAAM
    public function validateDedicatedRelationFields(FieldConfig $field, array $required_config): void {
        $expected_fields = $this->getDedicatedRelationFields($node_type);
        $field_config_storage = $this->entityTypeManager->getStorage('field_config');

        foreach ($expected_fields as $field_name => $requirements) {
            /** @var \Drupal\field\Entity\FieldConfig|null $field */
            $field = $field_config_storage->load("node.{$node_type->id()}.$field_name");
            if (!$field) {
                continue; // ontbrekende velden check je al elders
            }

        $storage = $field->getFieldStorageDefinition();

        // Check type
        if ($storage->getType() !== $requirements['type']) {
            \Drupal::messenger()->addError($this->t(
                'The field %field on content type %type must be of type %expected (currently %actual).',
                [
                    '%field' => $field_name,
                    '%type' => $node_type->label(),
                    '%expected' => $requirements['type'],
                    '%actual' => $storage->getType(),
                ]
            ));
        }

        // Check cardinality
        if ($storage->getCardinality() !== $requirements['cardinality']) {
            \Drupal::messenger()->addError($this->t(
                'The field %field on content type %type must have cardinality %expected.',
                [
                    '%field' => $field_name,
                    '%type' => $node_type->label(),
                    '%expected' => $requirements['cardinality'],
                ]
            ));
        }

        // Check target_type
        if ($requirements['type'] === 'entity_reference') {
            $target_type = $storage->getSetting('target_type');
            if ($target_type !== $requirements['target_type']) {
                \Drupal::messenger()->addError($this->t(
                    'The field %field on content type %type must reference %expected, not %actual.',
                    [
                        '%field' => $field_name,
                        '%type' => $node_type->label(),
                        '%expected' => $requirements['target_type'],
                        '%actual' => $target_type,
                    ]
                ));
            }

            // Check bundles: exact 1 bundle
            $bundles = $field->getSetting('handler_settings')['target_bundles'] ?? [];
            if (empty($bundles) || count($bundles) !== 1) {
                \Drupal::messenger()->addError($this->t(
                    'The field %field on content type %type must reference exactly one node type.',
                    [
                        '%field' => $field_name,
                        '%type' => $node_type->label(),
                    ]
                ));
            }
        }
    }
}


    protected function createDedicatedRelationFields(NodeType $node_type, array $missing_fields) : void {

        $field_storage_config_storage = $this->entityTypeManager->getStorage('field_storage_config');
        $field_config_storage = $this->entityTypeManager->getStorage('field_config');

        foreach ($missing_fields as $field_name => $settings) {
            $field_storage = $field_storage_config_storage->load("node.$field_name");
            if (!$field_storage) {
                dpm('storage mist voor ' . $field_name);
                $field_storage = $field_storage_config_storage->create([
                    'field_name'  => $field_name,
                    'entity_type' => 'node',
                    'type'        => $settings['type'],
                    'cardinality' => $settings['cardinality'],
                    'settings'    => [
                        'target_type' => $settings['target_type'],
                        ],
                ]);
            
                $field_storage->set('locked', true);
                $field_storage->save();
            }

            $field_config = $field_config_storage->load("node.{$node_type->id()}.$field_name");

            if (!$field_config) {
                $field_config = $field_config_storage->create([
                    'field_name'   => $field_name,
                    'entity_type'  => 'node',
                    'bundle'       => $node_type->id(),
                    'label'        => ucfirst(str_replace('_', ' ', $field_name)),
                    'required'     => true,
                ]);
                $field_config->save();

            }   
        }
    }


    protected function setRelationEntityProperty(ConfigEntityBundleBase $entity_type, string $property, bool $value) : void {
        $this->fillRelationshipNodeSetting($entity_type, $property, $value);
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
        }
        foreach($properties as $property => $value){
            $this->fillRelationshipNodeSetting($entity_type, $property, $value);
        }
        $entity_type->save();
    }

    public function isRelationNode(NodeType $node_type) : bool{
        $value = $this->getRelationEntityProperty($node_type, 'enabled');
        return !empty($value);
    }

    public function isTypedRelationNode(NodeType $node_type) : bool{
        $typed = $this->getRelationEntityProperty($node_type, 'typed_relation');
        return $this->isRelationNode($node_type) && !empty($typed);
    }

    public function hasAutoTitleCreation(NodeType $node_type) : bool{
        $auto_title = $this->getRelationEntityProperty($node_type, 'auto_title');
        return !empty($auto_title);
    }


    protected function fillRelationshipNodeSetting(ConfigEntityBundleBase $entity_type, string $property, bool $value) : void {
        if(!$this->validRelationProperty($property)){
            return;
        }
        $entity_type->setThirdPartySetting('relationship_nodes', $property, $value);
    }


    public function getRelationEntityProperty(ConfigEntityBundleBase $entity_type, string $property) : ?bool {
        if(!$this->validRelationProperty($property)){
            return null;
        }
        return $entity_type->getThirdPartySetting('relationship_nodes', $property, null);
    }


    public function getRelationEntityProperties(ConfigEntityBundleBase $entity_type) : ?array {
        return $entity_type->getThirdPartySettings('relationship_nodes') ?? null;
    }


    public function validRelationProperty(string $property){
        $properties = ['enabled', 'typed_relation', 'auto_title'];
        return in_array($property, $properties);
    }


    public function getFormNodeType(FormStateInterface $form_state): ?NodeType{
        $node_type = $form_state->getFormObject()->getEntity();
        return $node_type instanceof NodeType ?  $node_type : null;
    }
}