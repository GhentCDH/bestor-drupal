<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList;
use Drupal\relationship_nodes\Service\ReferenceFieldHelper;
use Drupal\node\Entity\Node;


class RelationSyncService {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected RelationshipInfoService $infoService;
    protected ReferenceFieldHelper $referenceFieldHelper;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        RelationshipInfoService $infoService,
        ReferenceFieldHelper $referenceFieldHelper
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->infoService = $infoService;
        $this->referenceFieldHelper = $referenceFieldHelper;
    }



    public static function registerCreatedRelations(array &$form, FormStateInterface $form_state): void {
      dpm('register start')  ;
      $updated_ids = [];
        $parent_entity = $form_state->getFormObject()->getEntity();
        if(!($parent_entity instanceof Node)){
            return;
        }
        foreach ($form_state->get('inline_entity_form') as $field_name => $relation_type_form) {
            if (!str_starts_with($field_name, 'computed_relationshipfield__') || empty($relation_type_form['entities'])) {
                continue;
            }
            foreach ($relation_type_form['entities'] as $delta => $entity) {
                if (
                    empty($entity['entity']) || 
                    !($entity['entity'] instanceof Node) ||
                    !isset($entity['needs_save']) ||
                    $entity['needs_save'] !== false
                ) {
                    continue;
                }
                $entity_form = $form[$field_name]['widget'][$delta]['inline_entity_form'];   
                $info_service = \Drupal::service('relationship_nodes.relationship_info_service');      
                $foreign_key = $info_service->getEntityFormForeignKeyField($entity_form, $form_state) ?? '';         
                if(!$foreign_key){
                    continue;
                }
                $updated_ids[$entity['entity']->id()] = $foreign_key;
            }     
        }
        $form_state->set('created_relation_ids', $updated_ids);
        dpm($updated_ids);
        dpm('regtister stop');
    }



    public function dispatchToRelationHandlers(array &$form, FormStateInterface $form_state): void {        
      dpm('dispatch steart');
      $entity = $this->getParentFormEntity($form_state);
        if ($entity->isNew()) {
            $this->bindCreatedRelations($form, $form_state);
        } else {
            $removed = $this->getRemovedRelations($form, $form_state);
            if(!empty($removed)){
              $this->deleteNodes($removed);
            }   
        }
        dpm('dispatch satop');
    }


  
    private function bindCreatedRelations(array &$form, FormStateInterface $form_state): void {
        $relations = $form_state->get('created_relation_ids');
        if(empty($relations) || !is_array($relations)){
            return;
        }
        $target_node = $form_state->getFormObject()->getEntity();
        if (!($target_node instanceof Node)) {
        return;   
        }
        $node_storage = $this->entityTypeManager->getStorage('node');
        foreach ($relations as $relation_id => $foreign_key) {
            $relation_node = $node_storage->load($relation_id);
            if (!($relation_node instanceof Node)) {
                continue;
            }
            $relation_node->set($foreign_key, [['target_id' => $target_node->id()]]);
            $relation_node->save();    
        } 
    }



    public function getParentFormEntity(FormStateInterface $form_state): ?EntityInterface{
      $form_object = $form_state->getFormObject();
      if(!$form_object instanceof EntityForm){
        return null;
      }
      $build_info = $form_state->getBuildInfo();
      if(!isset($build_info['base_form_id']) || $build_info['base_form_id'] != 'node_form') {
        return null;
      }
      $form_entity = $form_object->getEntity();
      if(!$form_entity instanceof EntityInterface){
        return null;
      }
      return $form_entity;
    }



    public function getRelationSubformFields(FormStateInterface $form_state): array{
      $result = [];
      $ief_widget_state = $form_state->get('inline_entity_form');
      if(!is_array($ief_widget_state)){
        return $result;
      }
      foreach($ief_widget_state as $field_name => $form_data){
        if(str_starts_with($field_name, 'computed_relationshipfield__')){
          $result[$field_name] = $form_data;
        }
      }
      return $result;
    }



    public function addParentFieldConfig(array &$parent_form, array &$subform_fields): void{        
      foreach($subform_fields as $field_name => $form_data){
        if (!isset($parent_form[$field_name]['widget'])) {
          continue;
        }
        foreach($parent_form[$field_name]['widget'] as $i => &$widget){
          if(!is_int($i) || !is_array($widget) || !isset($widget['inline_entity_form'])) {
            continue;
          }
          $widget['inline_entity_form']['#rn__parent_field'] = $field_name;
        } 
      } 
    }
   


    public function getRemovedRelations(array &$form, FormStateInterface $form_state):array {
      
      $parent_entity = $this->getParentFormEntity($form_state);
      if(!$parent_entity){
        return [];
      }
      $subform_fields = $this->getRelationSubformFields($form_state);
      $original_relations = [];
      $current_relations = [];
      foreach($subform_fields as $field_name => $form_data){
        $item_list = $parent_entity->get($field_name) ?? null;
        if(!($item_list instanceof ReferencingRelationshipItemList)){
            continue;
        }
        $collected_original = $item_list->collectExistingRelations() ?? [];
        if (!empty($collected_original)) {
          $original_relations = array_merge($original_relations, array_keys($collected_original));
        }
        $collected_current = $this->referenceFieldHelper->getFieldListTargetIds($item_list) ?? [];
        if (!empty($collected_current)) {
          $current_relations = array_merge($current_relations, $collected_current);
        }
      }
      sort($original_relations);
      sort($current_relations);
      $result = [];
      if($original_relations != $current_relations) {
        $result = array_diff($original_relations, $current_relations);
      }
      return $result;
    }


    public function deleteNodes(array $ids_to_remove): void {
      if (empty($ids_to_remove)) {
        return;
      }
      $storage = $this->entityTypeManager->getStorage('node');
      foreach($ids_to_remove as $id){
        $node = $storage->load($id);
        if ($node instanceof Node) {
          $node->delete();
        }
      }
    }
}