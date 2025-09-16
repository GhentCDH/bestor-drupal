<?php

namespace Drupal\relationship_nodes\RelationEntity\UserInterface;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationSyncService;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationFormHelper;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;



class RelationEntityFormHandler {

    use StringTranslationTrait;

    protected FieldNameResolver $fieldNameResolver;
    protected RelationSyncService $syncService;
    protected RelationFormHelper $formHelper;


    public function __construct(
        FieldNameResolver $fieldNameResolver,
        RelationSyncService $syncService,
        RelationFormHelper $formHelper
    ) {
        $this->fieldNameResolver = $fieldNameResolver;
        $this->syncService = $syncService;
        $this->formHelper = $formHelper; 
    }


    public function handleRelationWidgetSubmit(string $field_name, array &$widget_state, array &$form, FormStateInterface $form_state): void {
        $parent_node = $this->formHelper->getParentFormNode($form_state);
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


    public function clearEmptyRelationsFromInput(array $values, array &$form, FormStateInterface $form_state, string $field_name):?array{
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