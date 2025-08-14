<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeForm;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList;
use Drupal\relationship_nodes\Service\ConfigManager;
use Drupal\relationship_nodes\Service\ReferenceFieldHelper;
use Drupal\relationship_nodes\Service\RelationshipInfoService;


class RelationSyncService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ConfigManager $configManager;
  protected RelationshipInfoService $infoService;
  protected ReferenceFieldHelper $referenceFieldHelper;


  public function __construct(
      EntityTypeManagerInterface $entityTypeManager,
      ConfigManager $configManager,
      RelationshipInfoService $infoService,
      ReferenceFieldHelper $referenceFieldHelper
  ) {
      $this->entityTypeManager = $entityTypeManager;
      $this->configManager = $configManager;
      $this->infoService = $infoService;
      $this->referenceFieldHelper = $referenceFieldHelper;
  }


  public function dispatchToRelationHandlers(string $field_name, array &$widget_state, array &$form, FormStateInterface $form_state): void {
    $parent_node = $this->getParentFormNode($form_state);
    if (!($parent_node instanceof Node)) {
      return;
    }

    if (!$parent_node->isNew()) {
      $removed = $this->getRemovedRelations($parent_node, $field_name);
      if (!empty($removed)) {
        $this->deleteNodes($removed);
      }
    }

    $this->saveSubformRelations($parent_node, $field_name, $widget_state, $form, $form_state);  
  }


   public function bindNewRelationsToParent(FormStateInterface $form_state): void {
    $relations = $form_state->get('created_relation_ids');
    if(empty($relations) || !is_array($relations)){
      return;
    }
    $target_node = $this->getParentFormNode($form_state);
    if (!($target_node instanceof Node)) {
      return;   
    }
    $node_storage = $this->entityTypeManager->getStorage('node');
    foreach ($relations as $relation_id => $foreign_key) {
      $relation_node = $node_storage->load($relation_id);
      if (!($relation_node instanceof Node)) {
        continue;
      }
      $relation_node->set($foreign_key, [['target_id' => $target_node->id()]]);
      $relation_node->save();    
    } 
  }


  public function getParentFormNode(FormStateInterface $form_state): ?Node{
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


  public function getRelationSubformFields(FormStateInterface $form_state): array{
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
      foreach($this->configManager->getRelatedEntityFields() as $related_entity_field) {
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
   

  public function deleteNodes(array $ids_to_remove): void {
    if (empty($ids_to_remove)) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    foreach($ids_to_remove as $id){
      $node = $storage->load($id);
      if ($node instanceof Node) {
        $node->delete();
      }
    }
  }


  private function saveSubformRelations(Node $parent_node, string $field_name, array &$widget_state, array &$form, FormStateInterface $form_state): void {        
    if (empty($widget_state['entities']) || !is_array($widget_state['entities'])) {
      return;
    }
    $new_parent = $parent_node->isNew();

    foreach ($widget_state['entities'] as $delta => &$entity_item) {
      if (!$this->relationNeedsSave($entity_item)) {
        continue;
      }
      $entity = $entity_item['entity'];
      $this->entityTypeManager->getHandler('node', 'inline_form')->save($entity);
      if ($new_parent && isset($form[$field_name])) {
        $entity_form = $this->getEntityFormByDelta($form[$field_name], $delta);
        if ($entity_form !== null) {
          $this->registerNewRelationForBinding($entity, $entity_form, $form_state);
        }
      }
      $entity_item['needs_save'] = FALSE;
    }      
  }


  private function relationNeedsSave(array $entity_item): bool {
    return !empty($entity_item['entity'])
      && $entity_item['entity'] instanceof Node
      && !empty($entity_item['needs_save']);
  }


  private function getRemovedRelations(Node $parent_node, string $field_name): array {
    $item_list = $parent_node->get($field_name) ?? null;
    if(!($item_list instanceof ReferencingRelationshipItemList)){
      return [];
    }
    $original_relations = array_keys($item_list->collectExistingRelations()) ?? [];
    $current_relations = $this->referenceFieldHelper->getFieldListTargetIds($item_list) ?? [];
    return array_diff($original_relations, $current_relations);
  }


  private function getEntityFormByDelta($form_field, $delta):?array{
      return $form_field['widget'][$delta]['inline_entity_form'] ?? null;
  }


  private function registerNewRelationForBinding(Node $entity, array $entity_form, FormStateInterface $form_state): void{  
    $foreign_key = $this->infoService->getEntityFormForeignKeyField($entity_form, $form_state) ?? '';         
    if(!$foreign_key){
      return;
    } 
    $form_state->set(['created_relation_ids', $entity->id()], $foreign_key);
  }  
}