<?php

namespace Drupal\relationship_nodes;

use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\WidgetSubmit;


class RelationExtensionWidgetSubmit extends WidgetSubmit{

    public static function attach(array &$form, FormStateInterface $form_state) {
        foreach ($form['#ief_element_submit'] as $i => $callback) {
            if (is_array($callback) && $callback[0] === WidgetSubmit::class && $callback[1] === 'doSubmit') {
                $form['#ief_element_submit'][$i] = [static::class, 'doSubmit'];
                return;
            }
        }
        $form['#ief_element_submit'][] = [static::class, 'doSubmit'];  
    }

    public static function doSubmit(array $form, FormStateInterface $form_state) {
        $widget_states =& $form_state->get('inline_entity_form');
        if(empty($widget_states) || !is_array($widget_states)){
            return;
        }
        $sync_service = \Drupal::service('relationship_nodes.relation_sync_service');
        krsort($widget_states, SORT_STRING);
        foreach ($widget_states as $field_name => &$widget_state) {
            if($sync_service->validRelationWidgetState($widget_state)){
                $sync_service->dispatchToRelationHandlers($field_name, $widget_state, $form, $form_state);
            }
        }

        parent::doSubmit($form, $form_state);

    }
}