<?php

namespace Drupal\relationship_nodes\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\inline_entity_form\Form\EntityInlineForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use \Drupal\node\Entity\Node;



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
    $related_entity_field_1 = $info_service->getRelatedEntityFields()['related_entity_field_1'];
    $related_entity_field_2 = $info_service->getRelatedEntityFields()['related_entity_field_2'];
    
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
    return $entity_form;
  }


 /**
   * {@inheritdoc}
   */
   public function entityFormSubmit(array &$entity_form, FormStateInterface $form_state) {
      parent::entityFormSubmit($entity_form, $form_state);
      
      $current_node = \Drupal::routeMatch()->getParameter('node');  
      if (!$current_node instanceof Node) {
        return; // If a new node is being created, a submit handler creates the relations later.
      }  
  
      $form_entity = $form_state->getFormObject()->getEntity();
      if(!$form_entity instanceof EntityInterface){
        return;
      }

      $foreign_key = $this->getForeignKeyField($entity_form, $form_entity->getType());
      if( $foreign_key  == $related_entity_field_1){ 
        $entity_form[$related_entity_field_1]['widget'][0]['target_id']['#default_value'] = $current_node;
      } elseif( $foreign_key  == $related_entity_field_2) {
        $entity_form[$related_entity_field_2]['widget'][0]['target_id']['#default_value'] = $current_node;
      }     
  }

 /**
   * {@inheritdoc}
   */
    public static function getCreatedRelationIds(array &$form, FormStateInterface $form_state) {
      $updated_ids = [];
      foreach ($form_state->get('inline_entity_form') as $field_name => $relation_type_form) {
        if (isset($relation_type_form['entities']) && !empty($relation_type_form['entities'])) {
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




 /**
   * {@inheritdoc}
   */
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