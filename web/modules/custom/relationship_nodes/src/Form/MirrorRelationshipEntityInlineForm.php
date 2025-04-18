<?php

namespace Drupal\relationship_nodes\Form;

use Drupal\inline_entity_form\Form\EntityInlineForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relationship_nodes\Service\RelationshipInfoService;



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
    \Drupal::logger('CUSTOM MODULE')->notice('RUN ENTITY FORM');
    return $entity_form;
  }
}