<?php

namespace Drupal\relationship_nodes_search\Form;

use Drupal\search_api\Form\IndexAddFieldsForm;
use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Utility\Utility;

class ExtendedIndexAddFieldsForm extends IndexAddFieldsForm {

  protected function getPropertiesList(
    array $properties,
    string $active_property_path,
    $base_url,
    ?string $datasource_id,
    string $parent_path = '',
    string $label_prefix = '',
    int $depth = 0,
    array $rows = []
  ): array {

    $rows = parent::getPropertiesList($properties, $active_property_path, $base_url, $datasource_id, $parent_path, $label_prefix, $depth, $rows);
    $remove_add_button = [];
    $related_entity_fields = \Drupal::service('relationship_nodes.field_name_resolver')->getRelatedEntityFields();
    foreach ($rows as $key => &$row) {
      $machine_name = $row['machine_name']['data'] ?? '';
      if (!str_starts_with($machine_name, 'relationship_info__')) {
        continue;
      }
  
      if (substr_count($machine_name, ':') === 0) {
         $this->addFieldButtons[] = [
          '#type' => 'submit',
          '#name' => Utility::createCombinedId($datasource_id, $machine_name),
          '#value' => $this->t('Add'),
          '#submit' => ['::addRelationshipGroup', '::save'],
          '#limit_validation_errors' => [],
          '#attributes' => [
            'class' => ['button', 'button--primary', 'button--extrasmall', 'relationship-parent-add'],
            'disabled' => 'disabled',
          ],
          '#ajax' => [
            'wrapper' => $this->formIdAttribute,
          ],
          '#property' => isset($properties[$machine_name])? $properties[$machine_name] : null ,
          '#row_key' => $key,
          '#datasource_key' => 'datasource_entity:node'
        ];

      }
      
      else {

        $attributes = ['class' => ['relationship-child-checkbox']];
        if (in_array(end(explode(':', $machine_name)), $related_entity_fields)) {
          $attributes['class'][] = 'is-disabled';
          $attributes['checked'] = 'checked';
          $attributes['disabled'] = 'disabled';
        } 

        $row['relation_property']['data'] = [
          '#type' => 'checkbox',
          '#title' => '',
          '#name' => 'relationship_nested_' . str_replace(':', '__', $machine_name),
          '#attributes' => $attributes,
        ];  
        $remove_add_button[] = $key;
      }
      
      
    }
    if(!empty($remove_add_button)){
        foreach($this->addFieldButtons as $i => $add_button){
            if(in_array($add_button['#row_key'], $remove_add_button)){
                unset($this->addFieldButtons[$i]);
                $remove_add_button = array_diff($remove_add_button, [$i]);
            }
            if(empty($remove_add_button)){
                break;
            }
        }
    }
    return $rows;
  }


    public function preRenderForm(array $form): array {
      $form = parent::preRenderForm($form);
      $form['#attached']['library'][] = 'relationship_nodes_search/relationship_form';
      return $form;
    }

    public function addRelationshipGroup(array $form, FormStateInterface $form_state) {
        $button = $form_state->getTriggeringElement();
        if (!$button || !isset($button['#property'])) {
            return;
        }

        $property = $button['#property'];
        $row_key = $button['#row_key'] ?? null;
        [$datasource_id, $property_path] = Utility::splitCombinedId($button['#name']);

        if (!$property_path) {
            return;
        }

        $all_table_rows = $form_state->getCompleteForm()['datasources']['datasource_entity:node']['table']['#rows'];
         

        $nested_properties = [];

        $i = $row_key + 1;

        while(strtok($all_table_rows[$i]['machine_name']['data'], ':') == $property_path && isset($all_table_rows[$i]['relation_property'])){
          if($all_table_rows[$i]['relation_property']['data']['#attributes']['checked'] == 'checked'){
            $nested_properties[] = str_replace( $property_path . ':', '', $all_table_rows[$i]['machine_name']['data']);
          }     
          $i++;
        }

        $all_user_input = $form_state->getUserInput();
        $field_prefix = 'relationship_nested_' . str_replace(':', '__', $property_path) . '__';

        foreach ($all_user_input as $input_field => $user_input) {
            if(str_starts_with($input_field, $field_prefix) && $user_input == 1){
                $nested_field_name = str_replace( $field_prefix, '', $input_field);
                $nested_properties[] = $nested_field_name;
            }   
        }
        
        if(empty($nested_properties)){
          return;
        }

        $nested_properties = array_unique($nested_properties);
        
        /** @var \Drupal\search_api\Item\Field $field */
        $field = $this->fieldsHelper->createFieldFromProperty($this->entity, $property, $datasource_id, $property_path , $property_path . '__nested', 'relationship_nodes_search_nested_relationship');
        $field->setConfiguration(['nested_fields' => $nested_properties]);


        $this->entity->addField($field);
        $this->messenger()->addStatus($this->t('Added relationship group with selected fields.'));
    }
}
