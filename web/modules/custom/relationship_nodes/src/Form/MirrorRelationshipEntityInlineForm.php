<?php

namespace Drupal\relationship_nodes\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\inline_entity_form\Form\EntityInlineForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\ContentEntityInterface;


class MirrorRelationshipEntityInlineForm extends EntityInlineForm {


 /**
   * {@inheritdoc}
   */
  public function entityForm(array $entity_form, FormStateInterface $form_state) {
    $entity_form = parent::entityForm($entity_form, $form_state);
    $info_service = \Drupal::service('relationship_nodes.relationship_info_service');

    if($info_service->allConfigAvailable() == false && $entity_form['#form_mode'] != $info_service->getRelationshipFormMode()) {
      return $entity_form;
    }     
    $related_entity_fields = $info_service->getRelatedEntityFields();
    $related_entity_field_1 = $related_entity_fields['related_entity_field_1'];
    $related_entity_field_2 = $related_entity_fields['related_entity_field_2'];
    
    if($entity_form['#entity'] && !$entity_form['#entity']->isNew()){ 
      $relation_info = $info_service->getRelationInfoForCurrentForm($entity_form['#entity']);
      $current_node_join_fields = $relation_info['current_node_join_fields'];
      if($current_node_join_fields && is_array($current_node_join_fields) && count($current_node_join_fields) == 1){

          switch($current_node_join_fields[0]){
            case $related_entity_field_1:
              $entity_form[$related_entity_field_1]['#attributes']['hidden'] = 'hidden';
              break;
            case $related_entity_field_2:
              $entity_form[$related_entity_field_2]['#attributes']['hidden'] = 'hidden';
              break;
          } 
      }
    }
    if($entity_form['#entity']->isNew()){
      $form_entity = $form_state->getFormObject()->getEntity();
      if(!$form_entity instanceof EntityInterface){
        return $entity_form;
      }
      $foreign_key = $this->getForeignKeyField($entity_form, $form_entity->getType());
      $entity_form[$related_entity_field_1]['#attributes']['hidden'] = 'hidden';

      if( $foreign_key == $related_entity_field_1){ 
        $entity_form[$related_entity_field_1]['#attributes']['hidden'] = 'hidden';
      } elseif( $foreign_key == $related_entity_field_2) {
        $entity_form[$related_entity_field_2]['#attributes']['hidden'] = 'hidden';
      } 
    }
    //$entity_form['#element_validate'][] = [get_class($this), 'cleanEmptyRelationFields'];
    //dpm($entity_form, 'entity form in mirror relationship inline form');
    return $entity_form;
  }


   /**
   * {@inheritdoc}
   */
   public function entityFormSubmit(array &$entity_form, FormStateInterface $form_state) {   
    $this->cleanEmptyRelationFields($entity_form, $form_state);  
    parent::entityFormSubmit($entity_form, $form_state);
     // $this->cleanEmptyRelationFields($entity_form, $form_state);

      $parent_field_name = $entity_form['#parent_field_name'] ?? null;
      if($parent_field_name == null || !str_starts_with($parent_field_name, 'computed_relationshipfield__') || $form_state->get('inline_entity_form') == null) {
        return;
      }
      $form_state_value = $form_state->getValue($parent_field_name);
      $form_state_ief_input = $form_state->get('inline_entity_form')[$parent_field_name];

      
      $current_node = \Drupal::routeMatch()->getParameter('node');
      if (!$current_node instanceof Node) {
        return; // If a new node is being created, a submit handler creates the relation later.
      }  

      $entity_form_entity = $entity_form['#entity'];
      if(!$entity_form_entity instanceof EntityInterface){
        return;
      }

      if($entity_form['#entity']->isNew()){
        $foreign_key = $this->getForeignKeyField($entity_form, $form_state->getFormObject()->getEntity()->getType());     
        $entity_form_entity->set($foreign_key, $current_node->id()); 
      }      
    }

    public static function cleanEmptyRelationFields(array &$entity_form, FormStateInterface $form_state) {
    dpm('no 1');
    $info_service = \Drupal::service('relationship_nodes.relationship_info_service');
    if(!$info_service->allConfigAvailable()) {
      return;
    }
    dpm('no 2');
    $parent_field_name = $entity_form['#parent_field_name'] ?? null;
    if($parent_field_name == null || !str_starts_with($parent_field_name, 'computed_relationshipfield__') || $form_state->get('inline_entity_form') == null) {
      return;
    }
    dpm('no 3');
    $fs_field_ief_input = $form_state->get('inline_entity_form')[$parent_field_name] ?? null;
    if($fs_field_ief_input  == null || !is_array($fs_field_ief_input) || count($fs_field_ief_input) == 0) {
      return;
    }
    dpm('no 4');
    $fs_field_values = $form_state->getValue($parent_field_name);
    $valid_items = 0;
    $i = 0;
    for($fs_field_values; isset($fs_field_values[$i]); $i++) {
      dpm($fs_field_values[$i], 'single full relation arr');
      $ief = $fs_field_values[$i]['inline_entity_form'];
      $valid_fields = 0;
      foreach($info_service->getRelatedEntityFields() as $related_entity_field) { 
        dpm($ief[$related_entity_field], 'single field value -- multiple possible');
        foreach($ief[$related_entity_field] as $reference) {
           dpm($ief[$related_entity_field], 'single field value input - one reference');
          if($reference['target_id'] != null) {
            $valid_fields++;
            break;
          }
        }
        if($valid_fields > 0) {
          break;
        }
      }
      if($valid_fields > 0) {
        $valid_items++;
        break;
      }   
    }
    if($valid_items == 0) {
      $form_state->setValue($parent_field_name, []);
      $form_state->set(['inline_entity_form', $parent_field_name], []);
      $form_state->set([
  'inline_entity_form',
  $parent_field_name,
  'entities'
], []);
    } 
    dpm($form_state->get('inline_entity_form'), 'cleaned inline entity form input');
  }



    public static function getCreatedRelationIds(array &$form, FormStateInterface $form_state) {
      $updated_ids = [];
      foreach ($form_state->get('inline_entity_form') as $field_name => $relation_type_form) {
        if (str_starts_with($field_name, 'computed_relationshipfield__') && isset($relation_type_form['entities']) && !empty($relation_type_form['entities'])) {
          foreach ($relation_type_form['entities'] as $delta => $entity) {
            $parent_entity = $form_state->getFormObject()->getEntity();
            if ($entity['entity'] instanceof Node && $entity['needs_save'] == false && $parent_entity instanceof EntityInterface) {
              $foreign_key = self::getForeignKeyField($form[$field_name]['widget'][$delta]['inline_entity_form'], $form_state->getFormObject()->getEntity()->getType());
              $relation_nid = $entity['entity']->id();
              if ($relation_nid) {
                $updated_ids[$relation_nid] =  $foreign_key;
              }
            }
          }
        }
      }
      $form_state->set('created_relation_ids', $updated_ids);
  }




  public static function getForeignKeyField($entity_form, $bundle_name){
    $result = '';
    $info_service = \Drupal::service('relationship_nodes.relationship_info_service');

    if($info_service->allConfigAvailable() == false && $entity_form['#form_mode'] != $info_service->getRelationshipFormMode()) {
      return $result;
    }     

    $fields = $info_service->getRelatedEntityFields();
    $related_entity_field_1 = $fields['related_entity_field_1'];
    $related_entity_field_2 = $fields['related_entity_field_2'];
    if(!isset($entity_form[ $related_entity_field_1 ]) || !isset($entity_form[$related_entity_field_2])){
      return $result;
    }

    $foreign_key = [];
    foreach($fields as $field){
      if(in_array($bundle_name, $entity_form[$field]['widget'][0]['target_id']['#selection_settings']['target_bundles'])){
        $foreign_key[] = $field;
      }
    }


    if(count($foreign_key) == 2 || (count($foreign_key) == 1 && $foreign_key[0] == $related_entity_field_1)){ 
        $result = $related_entity_field_1;
    } elseif(count($foreign_key) == 1 && $foreign_key[0] == $related_entity_field_2) {
        $result = $related_entity_field_2;
    }
    
    return $result;
  }


}  