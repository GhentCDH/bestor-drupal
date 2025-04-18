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
    $config = \Drupal::config('relationship_nodes.settings');
    if($config->get('relationship_type_field') != null && count($config->get('related_entity_fields')) == 2){
      $field_definition = $items->getFieldDefinition();
      if($field_definition && $field_definition->get('field_name') == $config->get('relationship_type_field')){   
        $relation_info = \Drupal::service('relationship_nodes.relationship_info_service')->getRelationInfoForCurrentForm($items->getEntity());
        $current_node_join_fields = $relation_info['current_node_join_fields'];
        $general_relationship_info = $relation_info['general_relationship_info'];
        if($current_node_join_fields && count($current_node_join_fields) == 1 && $current_node_join_fields[0] == $config->get('related_entity_fields')['related_entity_field_2'] ){
          $element['#options'] = $this->getMirrorOptions($element['#options'], $general_relationship_info);
        }
      }
    }
    return $element;
  }

  protected function getMirrorOptions($original_options, $relationshipnode_info) {
    $options = isset($original_options) ? $original_options : [];
    $config = \Drupal::config('relationship_nodes.settings');
    if(isset($options) && isset($relationshipnode_info) && isset($relationshipnode_info['relationnode']) && isset($relationshipnode_info['relationtypefield']['relationtypefield']) && isset($relationshipnode_info['relationtypefield']['mirrorfieldtype'])){
      $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $mirror_reference_field = $config->get('mirror_fields')['mirror_reference_field'];
      $mirror_string_field = $config->get('mirror_fields')['mirror_string_field'];
      foreach ($options as $term_id => $term_name) {
        $term = $taxonomy_storage->load($term_id);
        if($term){
          switch($relationshipnode_info['relationtypefield']['mirrorfieldtype']){
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
    }
    return $options;
  }
}