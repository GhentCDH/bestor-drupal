<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Drupal\node\Entity\Node;


class RelationSyncService {


    protected EntityTypeManagerInterface $entityTypeManager;
    protected RelationshipInfoService $infoService;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        RelationshipInfoService $infoService
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->infoService = $infoService;
    }



    private function registerCreatedRelations(array &$form, FormStateInterface $form_state): void {
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
                $foreign_key = $this->infoService->getEntityFormForeignKeyField($entity_form, $form_state) ?? '';         
                if(empty($foreign_key)){
                    continue;
                }
                $updated_ids[$entity['entity']->id()] = $foreign_key;
            }     
        }
        $form_state->set('created_relation_ids', $updated_ids);
    }



    private function dispatchToRelationHandlers(array &$form, FormStateInterface $form_state): void {
        $form_object = $form_state->getFormObject();
        $entity = $form_object->getEntity();
        if (!($entity instanceof Node)) {
            return;
        }
        if ($entity->isNew()) {
            $this->bindCreatedRelations($form, $form_state);
        } else {
            $this->syncRelationsOnSubmit($form, $form_state);
        }
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
  


  public function syncRelationsOnSubmit(array &$form, FormStateInterface $form_state): void {
    dpm('relati syncer');
       
    $form_object = $form_state->getFormObject();
    if (!($form_object instanceof EntityForm) || $form_state->getBuildInfo()['base_form_id'] != 'node_form') {
      return;
    }
    
    $form_entity = $form_object->getEntity();
    if (!($form_entity instanceof Node)) {
      return;
    }

    $ief_widget_state = $form_state->get('inline_entity_form') ?? [];
    if (!is_array($ief_widget_state) || empty($ief_widget_state)) {
      return; 
    }

    $original_relations = [];
    $current_relations = [];

    foreach ($ief_widget_state as $field_name => $form_data) {
        if(!str_starts_with($field_name, 'computed_relationshipfield__') || !$form_entity->hasField($field_name)) {
            continue;
        }

        $items = $form_entity->get($field_name) ?? [];

        if(!($items instanceof ReferencingRelationshipItemList)){
            return;
        }

        $db_relations = ReferencingRelationshipItemList::getRelations($items);
        if (is_array($db_relations) && !empty($db_relations)) {
        $original_relations = array_merge($original_relations, array_keys($db_relations));
        }
        foreach ($items->getValue() as $field_results) {
            foreach ($field_results as $itemValue) {
                $current_relations[] = intval($itemValue); 
            }
        }
        
     
    }

    sort($original_relations);
    sort($current_relations);
    

    if (empty($original_relations) || $original_relations == $current_relations) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    foreach ($original_relations as $relation_id) {
      if (!in_array($relation_id, $current_relations)) {
        $relation_node = $storage->load($relation_id);
        if ($relation_node instanceof EntityInterface) {
          $relation_node->delete();
        }
      }
    }
  }



  public function cleanEmptyRelationFields(array &$form, FormStateInterface $form_state) {
    if (!$this->infoService->allConfigAvailable()) {
      return;
    }

    $ief_widget_state = $form_state->get('inline_entity_form') ?? null;
    if ($ief_widget_state == null || !is_array($ief_widget_state) || count($ief_widget_state) == 0) {
      return;
    }
    
    foreach ($ief_widget_state as $field_name => $fs_field_ief_input) {
      if (str_starts_with($field_name, 'computed_relationshipfield__') && is_array($fs_field_ief_input) && isset($fs_field_ief_input['entities']) && is_array($fs_field_ief_input['entities']) && count($fs_field_ief_input['entities']) > 0) {
        $fs_field_values = $form_state->getValue($field_name);
        $valid_items = 0;
        $i = 0;
        for ($fs_field_values; isset($fs_field_values[$i]); $i++) {
          $ief = $fs_field_values[$i]['inline_entity_form'];
          $valid_fields = 0;
          foreach ($this->infoService->getRelatedEntityFields() as $related_entity_field) { 
            foreach ($ief[$related_entity_field] as $reference) {
              if ($reference['target_id'] != null) {
                $valid_fields++;
                break;
              }
            }
            if ($valid_fields > 0) {
              break;
            }
          }
          if ($valid_fields > 0) {
            $valid_items++;
            break;
          }   
        }
        if ($valid_items == 0) {
          $form_state->setValue($field_name, []);
          $form_state->set(['inline_entity_form', $field_name], []);
          $form_state->set(['inline_entity_form', $field_name, 'entities'], []);
        } 
      } 
    }   
  }
}
