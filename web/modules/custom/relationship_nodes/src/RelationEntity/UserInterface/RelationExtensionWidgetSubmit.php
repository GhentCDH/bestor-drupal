<?php

namespace Drupal\relationship_nodes\RelationEntity\UserInterface;

use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\WidgetSubmit;


class RelationExtensionWidgetSubmit extends WidgetSubmit{

    public static function updateDefaultSubmit(array &$form, FormStateInterface $form_state) {
        foreach ($form['#ief_element_submit'] as $i => $callback) {
            if (is_array($callback) && $callback[0] === WidgetSubmit::class && $callback[1] === 'doSubmit') {     
                $form['#ief_element_submit'][$i] = [static::class, 'doSubmit'];
                return;
            }
        } 
    }


    public static function doSubmit(array $form, FormStateInterface $form_state) {
        $relationFormHelper = \Drupal::service('relationship_nodes.relation_form_helper');
        $relation_widgets = $relationFormHelper->getRelationExtendedWidgetFields($form, $form_state);
        $widget_states =& $form_state->get('inline_entity_form');

        $relationFormHandler = \Drupal::service('relationship_nodes.relation_entity_form_handler');
        foreach ($relation_widgets as $field_name) {

            $widget_state =& $widget_states[$field_name];
            $relationFormHandler->handleRelationWidgetSubmit($field_name, $widget_state, $form, $form_state);
        }
        parent::doSubmit($form, $form_state);
    }
}