<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Drupal\Core\Field\EntityReferenceFieldItemList;

/**
 *
 * @FieldWidget(
 *   id = "mirror_select_widget",
 *   label = @Translation("Mirror Select Widget"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class MirrorSelectWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $info_service = \Drupal::service('relationship_nodes.relationship_info_service');
    if(!$info_service->allConfigAvailable()) {
        return $element;
    }

    $relationship_subform = false;
    foreach ($element['#field_parents'] as $field_parent) {
      if (is_string($field_parent) && strpos($field_parent, 'computed_relationshipfield__') === 0) {
        $relationship_subform = true;
        break;
      }
    }

    if($relationship_subform === true && $form["#type"] === 'inline_entity_form' && strpos($form["#bundle"], $info_service->getRelationshipNodeBundlePrefix()) === 0){    
      $field_definition = $items->getFieldDefinition();
      if($field_definition && $field_definition->get('field_name') && $field_definition->get('field_name') === $info_service->getRelationshipTypeField()){   
        $relation_info = \Drupal::service('relationship_nodes.relationship_info_service')->getRelationInfoForCurrentForm($items->getEntity());
        $current_node_join_fields = $relation_info['current_node_join_fields'];
        if($current_node_join_fields && count($current_node_join_fields) == 1 && $current_node_join_fields[0] == $info_service->getRelatedEntityFields()['related_entity_field_2'] ){
          $element['#options'] = $this->getMirrorOptions($element['#options'], $relation_info['general_relationship_info']);
        }
      }
    }

    return $element;
  }


  protected function getMirrorOptions($options, $relationshipnode_info) {
    if(!is_array($options) || empty($options) ) {
        return [];
    }

    $info_service = \Drupal::service('relationship_nodes.relationship_info_service');
    if(!$info_service->allConfigAvailable() ||!is_array($relationshipnode_info) || empty($relationshipnode_info) || isset($relationshipnode_info['relationnode']) || isset($relationshipnode_info['relationtypeinfo']['relationtypefield']) || isset($relationshipnode_info['relationtypeinfo']['mirrorfieldtype'])) {
        return $options;
    } 

    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $mirror_reference_field = $info_service->getMirrorFields()['mirror_reference_field'];
    $mirror_string_field = $info_service->getMirrorFields()['mirror_string_field'];
    foreach ($options as $term_id => $term_name) {
      $term = $taxonomy_storage->load($term_id);
      if($term){
        switch($relationshipnode_info['relationtypeinfo']['mirrorfieldtype']){
          case 'entity_reference_selfreferencing':
            if($term->get($mirror_reference_field)->target_id != null){
              $options[$term_id] =  $taxonomy_storage->load($term->get($mirror_reference_field)->target_id)->getName();
            }
            break; 
          case 'string':
            if($term->get($mirror_string_field)->value != null){
              $options[$term_id] =  $term->get($mirror_string_field)->value;
            }
            break;
        }   
      }      
    }    
    
    return $options;
  }
  
}