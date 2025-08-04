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

    if($relationship_subform === true && $form["#type"] === 'inline_entity_form' && strpos($form["#bundle"], $info_service->getRelationBundlePrefix()) === 0){    
      $field_definition = $items->getFieldDefinition();
      if($field_definition && $field_definition->get('field_name') && $field_definition->get('field_name') === $info_service->getRelationTypeField()){   
        $relation_info = \Drupal::service('relationship_nodes.relationship_info_service')->getEntityConnectionInfo($items->getEntity());
        if(empty($relation_info['join_fields'])){
          return $element;
        }
        $join_fields = $relation_info['join_fields'];
        if(count($join_fields) == 1 && $join_fields[0] == $info_service->getRelatedEntityFields(2)){
          $element['#options'] = $this->getMirrorOptions($element['#options'], $relation_info['relation_info']);
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
    if(!$info_service->allConfigAvailable() ||!is_array($relationshipnode_info) || empty($relationshipnode_info) || !empty($relationshipnode_info['relationtypeinfo'])) {
        return $options;
    } 

    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $mirror_reference_field = $info_service->getMirrorFields('reference');
    $mirror_string_field = $info_service->getMirrorFields()['string'];
    foreach ($options as $term_id => $term_name) {
      $term = $taxonomy_storage->load($term_id);
      if($term){
        switch($relationshipnode_info['relationtypeinfo']['mirror_field_type']){
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