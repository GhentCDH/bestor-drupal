<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldWidget;

use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormSimple;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'ief_validated_relations_simple' widget.
 *
 * @FieldWidget(
 *   id = "ief_validated_relations_simple",
 *   label = @Translation("Inline entity form - Validated relations (simple)"),
 *   description = @Translation("Entity form with validation to skip incomplete relation entities."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class IefValidatedRelationsSimple extends InlineEntityFormSimple {

    public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
        parent::massageFormValues( $values, $form, $form_state);
        $info_service = \Drupal::service('relationship_nodes.relationship_info_service');
        if(!$info_service->allConfigAvailable()) {
            return $values;
        }

        $parent_field_name = $this->fieldDefinition->getName() ?? null;
        $ief_widget_state = $form_state->get('inline_entity_form') ?? null;
        if($parent_field_name == null || !str_starts_with($parent_field_name, 'computed_relationshipfield__') || $ief_widget_state == null || !isset($ief_widget_state[$parent_field_name])) {
            return $values;
        }

        $fs_field_values = $form_state->getValue($parent_field_name);
        $valid_items = 0;
        $i = 0;
        for($fs_field_values; isset($fs_field_values[$i]); $i++) {
        $ief = $fs_field_values[$i]['inline_entity_form'];
        $valid_fields = 0;
        foreach($info_service->getRelatedEntityFields() as $related_entity_field) { 
            foreach($ief[$related_entity_field] as $reference) {
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
            $values = [];
        } 
        return $values;
    }
}