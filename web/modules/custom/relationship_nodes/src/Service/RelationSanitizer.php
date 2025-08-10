<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Service\ConfigManager;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Drupal\relationship_nodes\Service\ReferenceFieldHelper;


class RelationSanitizer {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected RouteMatchInterface $routeMatch;
    protected ConfigManager $configManager;
    protected RelationEntityValidator $relationEntityValidator;
    protected RelationshipInfoService $infoService;
    protected ReferenceFieldHelper $referenceFieldHelper;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        RouteMatchInterface $routeMatch,
        ConfigManager $configManager,
        RelationEntityValidator $relationEntityValidator,
        RelationshipInfoService $infoService,
        ReferenceFieldHelper $referenceFieldHelper
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->routeMatch = $routeMatch;
        $this->configManager = $configManager;
        $this->relationEntityValidator = $relationEntityValidator;
        $this->infoService = $infoService;
        $this->referenceFieldHelper = $referenceFieldHelper;
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
            foreach($this->configManager->getRelatedEntityFields() as $related_entity_field) {
                $ref_field = (array) ($ief[$related_entity_field] ?? []);
                if(empty($ref_field)){
                    continue;
                }
                foreach($ref_field as $reference) {
                    if($reference['target_id'] !== null) {
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





    public function checkRelationsValidity(Node $relation_entity): ?string {
        $related_entities = $this->infoService->getRelatedEntityValues($relation_entity); 
        if($related_entities === null) {
            return null;
        }

        $new_relation = false;
        if($relation_entity->isNew()){
            $current_node = $this->routeMatch->getParameter('node');
            $new_relation = true;
            if($current_node instanceof Node && $current_node !== $relation_entity){
                // Relation is added in a subform (IEF)
                $foreign_key_field = $this->infoService->getEntityForeignKeyField($relation_entity, $current_node);
                if($foreign_key_field){
                    $related_entities[$foreign_key_field] = [$current_node->id()];
                }
            }
        }
        if (count($related_entities) != 2 && !$new_relation) {
            return 'incomplete';
        }

        $related_entities = array_values($related_entities);   
        foreach($related_entities[0] as $reference){
            if(in_array($reference, $related_entities[1] ?? [])){
                return 'selfReferring'; 
            }
        }
        return null;
    }

    
    public function generateRelationLabel(Node $relation_node): string{
        $related_entities = $this->infoService->getRelatedEntityValues($relation_node);
        $title_parts = [];
        $node_storage = $this->entityTypeManager->getStorage('node');
        foreach($related_entities as $field_values){
            $node_titles = [];
            foreach($field_values as $nid){
                $node = $node_storage->load($nid);
                if ($node instanceof Node) {
                    $node_titles[] = $node->getTitle();
                }
            }
            if (!empty($node_titles)) {
                $title_parts[] = implode(', ', $node_titles);
            }
        }
        return 'Relationship '  . implode(' - ', $title_parts);
    }

}