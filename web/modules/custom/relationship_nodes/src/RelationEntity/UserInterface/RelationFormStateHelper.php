<?php

namespace Drupal\relationship_nodes\RelationEntity\UserInterface;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeForm;
use Drupal\node\Entity\Node;

class RelationFormStateHelper {

    public function getParentFormNode(FormStateInterface $form_state): ?Node {
        $form_object = $form_state->getFormObject();
        if(!$form_object instanceof NodeForm){
            return null;
        }
        $build_info = $form_state->getBuildInfo();
        if(!isset($build_info['base_form_id']) || $build_info['base_form_id'] != 'node_form') {
            return null;
        }
        $form_entity = $form_object->getEntity();
        if(!$form_entity instanceof Node){
            return null;
        }
        return $form_entity;
    }


    public function getRelationSubformFields(FormStateInterface $form_state): array {
        $result = [];
        $ief_widget_state = $form_state->get('inline_entity_form');
        if(!is_array($ief_widget_state)){
            return $result;
        }
        foreach($ief_widget_state as $field_name => $form_data){
            if(str_starts_with($field_name, 'computed_relationshipfield__')){
                $result[$field_name] = $form_data;
            }
        }
        return $result;
    }


    public function isValidRelationParentForm(FormStateInterface $form_state): bool {
        return !empty($this->getParentFormNode($form_state)) && !empty($this->getRelationSubformFields($form_state));
    }
}