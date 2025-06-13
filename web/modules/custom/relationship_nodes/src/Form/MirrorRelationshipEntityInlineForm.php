<?php

namespace Drupal\relationship_nodes\Form;

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

    $config = \Drupal::config('relationship_nodes.settings');
    if($entity_form['#form_mode'] == $config->get('relationship_form_mode')){
      $relation_info = \Drupal::service('relationship_nodes.relationship_info_service')->getRelationInfoForCurrentForm($entity_form['#entity']);
      $current_node_join_fields = $relation_info['current_node_join_fields'];
      $related_entity_field_1 = $config->get('related_entity_fields')['related_entity_field_1'];
      $related_entity_field_2 = $config->get('related_entity_fields')['related_entity_field_2'];
      if($entity_form['#entity']->isNew()){        
        $entity_form[$related_entity_field_1]['#attributes']['hidden'] = 'hidden';     
      };
      if($current_node_join_fields && count($current_node_join_fields) == 1){     
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
    return $entity_form;
  }


 /**
   * {@inheritdoc}
   */
   public function entityFormSubmit(array &$entity_form, FormStateInterface $form_state) {
    dpm($entity_form, 'entityFormSubmit');
    parent::entityFormSubmit($entity_form, $form_state);
    $current_node = \Drupal::routeMatch()->getParameter('node');  
    if (($current_node instanceof Node)) {
      // dit moet zeker uitgewerkt worden want is zeker niet altijd related entity field 1
      $entity_form[$related_entity_field_1]['widget'][0]['target_id']['#default_value'] = $current_node;  
      
    }     
  }

    public static function getCreatedRelationIds(array &$form, FormStateInterface $form_state) {
      $updated_ids = [];
      foreach ($form_state->get('inline_entity_form') as $relation_type_form) {
        if (isset($relation_type_form['entities']) && !empty($relation_type_form['entities'])) {
          foreach ($relation_type_form['entities'] as $entity) {
            if ($entity['entity'] instanceof Node && $entity['needs_save'] == false) {
              $relation_nid = $entity['entity']->id();
              if ($relation_nid) {
                $updated_ids[] = $relation_nid;
              }
            }
          }
        }
      }
      $form_state->set('created_relation_ids', $updated_ids);
  }
} 