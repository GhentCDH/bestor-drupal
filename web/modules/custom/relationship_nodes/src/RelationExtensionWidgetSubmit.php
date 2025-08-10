<?php

namespace Drupal\relationship_nodes;

use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Element\InlineEntityForm;
use Drupal\inline_entity_form\WidgetSubmit;
use Drupal\inline_entity_form\ReferenceUpgrader;
use Drupal\relationship_nodes\Service\RelationSanitizer;


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
        $widget_states = $widget_states ?? [];
        krsort($widget_states, SORT_STRING);
        foreach ($widget_states as $key => &$widget_state) {
            if(isset($widget_state['relation_extension_widget']) && $widget_state['relation_extension_widget'] == true){
                unset($widget_state[$key]);
                dpm($widget_state, $key);
                if(!empty($widget_state['entities'])){
                    foreach ($widget_state['entities'] as &$entity_item) {
                        if (
                            empty($entity_item['entity']) || 
                            !($entity_item['entity'] instanceof Node) ||
                            !isset($entity_item['needs_save']) ||
                            $entity_item['needs_save'] !== false
                        ) {
                            continue;
                        }
                        $entity = $entity_item['entity'];
                        dpm($entity->getEntityTypeId());
                        $inline_form_handler = \Drupal::entityTypeManager()->getHandler('node', 'inline_form');
                        $inline_form_handler->save($entity);
                        $entity_item['needs_save'] = FALSE;
                 
                    }
                }
                
                if(!empty($widget_state['delete'])){
                    foreach ($widget_state['delete'] as $entity) {
                        $entity->delete();
                    }
                    unset($widget_state['delete']);
                }
            }
        }
        if(!empty($widget_states)){
            parent::doSubmit($form, $form_state);
        }
    }
}