<?php

namespace Drupal\relationship_nodes\RelationEntity\UserInterface;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationSyncService;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationFormStateHelper;


class RelationEntityFormHandler {

    use StringTranslationTrait;

    protected FieldNameResolver $fieldNameResolver;
    protected RelationSyncService $syncService;
    protected RelationFormStateHelper $formStateHelper;


    public function __construct(
        FieldNameResolver $fieldNameResolver,
        RelationSyncService $syncService,
        RelationFormStateHelper $formStateHelper,
    ) {
        $this->fieldNameResolver = $fieldNameResolver;
        $this->syncService = $syncService;
        $this->formStateHelper = $formStateHelper; 
    }


    public function dispatchToRelationHandlers(string $field_name, array &$widget_state, array &$form, FormStateInterface $form_state): void {
        $parent_node = $this->formStateHelper->getParentFormNode($form_state);
        if (!($parent_node instanceof Node)) {
        return;
        }

        if (!$parent_node->isNew()) {
        $removed = $this->syncService->getRemovedRelations($parent_node, $field_name);
        if (!empty($removed)) {
            $this->syncService->deleteNodes($removed);
        }
        }

        $this->syncService->saveSubformRelations($parent_node, $field_name, $widget_state, $form, $form_state);  
    }


    public function validRelationWidgetState(array $widget_state): bool{
        if(!isset($widget_state['relation_extension_widget']) || $widget_state['relation_extension_widget'] !== true){
        return false;
        }
        return true;
    }


    public function addParentFieldConfig(array &$parent_form, array &$subform_fields): void{        
        foreach($subform_fields as $field_name => $form_data){
        if (!isset($parent_form[$field_name]['widget'])) {
            continue;
        }
        foreach($parent_form[$field_name]['widget'] as $i => &$widget){
            if(!is_int($i) || !is_array($widget) || !isset($widget['inline_entity_form'])) {
                continue;
            }
            $widget['inline_entity_form']['#rn__parent_field'] = $field_name;
        } 
        } 
    }


    public function clearEmptyRelationsFromInput(array $values, FormStateInterface $form_state, string $field_name){
        if($field_name == null || empty($values) || !str_starts_with($field_name, 'computed_relationshipfield__')){
            return $values;
        }

        $ief_widget_state = $form_state->get('inline_entity_form') ?? null;
        if($ief_widget_state == null || !isset($ief_widget_state[$field_name])){
            return $values;
        }
        $form_field_elements = $form_state->getValue($field_name);
        foreach($form_field_elements as $i => $element) {
            if(!is_array($element) || empty($element['inline_entity_form'])){
                continue;
            }
            $ief = $element['inline_entity_form'];
            $filled_ief = false;      
            foreach($this->fieldNameResolver->getRelatedEntityFields() as $related_entity_field) {
                $ref_field = (array) ($ief[$related_entity_field] ?? []);
                if(empty($ref_field)){
                    continue;
                }
                foreach($ref_field as $reference) {
                    if(!empty($reference['target_id'])) {
                        $filled_ief = true;  
                        break;
                    }
                }
                if($filled_ief) {
                    break;
                }
            }
            if(!$filled_ief) {
                unset($values[$i]);
            }  
        }
        return $values;
    }
}