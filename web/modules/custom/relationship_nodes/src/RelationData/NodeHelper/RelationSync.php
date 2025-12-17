<?php

namespace Drupal\relationship_nodes\RelationData\NodeHelper;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList;
use Drupal\relationship_nodes\RelationData\NodeHelper\ForeignKeyResolver;
use Drupal\relationship_nodes\RelationData\NodeHelper\RelationInfo;
use Drupal\relationship_nodes\Form\Entity\RelationFormHelper;
use Drupal\relationship_nodes\RelationData\NodeHelper\RelationWeightManager;


/**
 * Service for synchronizing relationship nodes.
 *
 * Handles binding relations to parent nodes, saving subform relations,
 * and removing orphaned relations.
 */
class RelationSync {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RelationInfo $nodeInfoService;
  protected ForeignKeyResolver $foreignKeyResolver;
  protected RelationFormHelper $formHelper;
  protected RelationWeightManager $relationWeightManager;


  /**
   * Constructs a RelationSync object.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param RelationInfo $nodeInfoService
   *   The node info service.
   * @param ForeignKeyResolver $foreignKeyResolver
   *   The foreign key field resolver.
   * @param RelationFormHelper $formHelper
   *   The form helper.
   * @param RelationWeightManager $relationWeightManager
   *   The relation subform weight manager.
   */
  public function __construct(
      EntityTypeManagerInterface $entityTypeManager,
      RelationInfo $nodeInfoService,
      ForeignKeyResolver $foreignKeyResolver,
      RelationFormHelper $formHelper,
      RelationWeightManager $relationWeightManager
  ) {
      $this->entityTypeManager = $entityTypeManager;
      $this->nodeInfoService = $nodeInfoService;
      $this->foreignKeyResolver = $foreignKeyResolver;
      $this->formHelper = $formHelper;
      $this->relationWeightManager = $relationWeightManager;
  }

  
  /**
   * Binds newly created relations to their parent node.
   *
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function bindNewRelationsToParent(FormStateInterface $form_state): void {
    $relations = $form_state->get('created_relation_ids');
    if (empty($relations) || !is_array($relations)) {
      return;
    }
    $target_node = $this->formHelper->getParentFormNode($form_state);
    if (!($target_node instanceof NodeInterface)) {
      return;   
    }
    $node_storage = $this->entityTypeManager->getStorage('node');
    foreach ($relations as $relation_id => $foreign_key) {
      $relation_node = $node_storage->load($relation_id);
      if (!($relation_node instanceof NodeInterface)) {
        continue;
      }
      $relation_node->set($foreign_key, [['target_id' => $target_node->id()]]);
      $relation_node->save();    
    } 
  }


  /**
   * Deletes nodes by their IDs.
   *
   * @param array $ids_to_remove
   *   Array of node IDs to delete.
   */
  public function deleteNodes(array $ids_to_remove): void {
    if (empty($ids_to_remove)) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    foreach ($ids_to_remove as $id) {
      $node = $storage->load($id);
      $this->relationWeightManager->deleteAllWeights($id);
      if ($node instanceof NodeInterface) {
        $node->delete();
      }
    }
  }


  /**
   * Saves relation nodes from subforms.
   *
   * @param NodeInterface $parent_node
   *   The parent node.
   * @param string $field_name
   *   The field name.
   * @param array $widget_state
   *   The widget state array (passed by reference).
   * @param array $form
   *   The form array (passed by reference).
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function saveSubformRelations(
    NodeInterface $parent_node, 
    string $field_name, 
    array &$widget_state, 
    array &$form, 
    FormStateInterface $form_state
  ): void {        
    if (empty($widget_state['entities']) || !is_array($widget_state['entities'])) {
      return;
    }
    $new_parent = $parent_node->isNew();

    foreach ($widget_state['entities'] as $delta => &$entity_item) {
      if (!$this->relationNeedsSave($entity_item)) {
        continue;
      }

      $entity = $entity_item['entity'];
      $entity_form = $this->getEntityFormByDelta($form[$field_name], $delta);
      $foreign_key = $this->foreignKeyResolver->getEntityFormForeignKeyField($entity_form, $form_state) ?? '';   

      $this->entityTypeManager->getHandler('node', 'inline_form')->save($entity);
      $relation_id = $entity->id();
      $this->relationWeightManager->setWeight($relation_id, $foreign_key, $delta);
      if ($new_parent && $entity_form && $foreign_key && isset($form[$field_name])) {
        $form_state->set(['created_relation_ids', $relation_id], $foreign_key);
      }
      $entity_item['needs_save'] = FALSE;
    }      
  }


  /**
   * Gets relation nodes that were removed from a parent node.
   *
   * @param NodeInterface $parent_node
   *   The parent node.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   Array of removed relation node IDs.
   */
  public function getRemovedRelations(NodeInterface $parent_node, string $field_name): array {
    $item_list = $parent_node->get($field_name) ?? null;
    if(!($item_list instanceof ReferencingRelationshipItemList)){
      return [];
    }
    $original_relations = array_keys($item_list->collectExistingRelations()) ?? [];
    $current_relations = $this->nodeInfoService->getFieldListTargetIds($item_list) ?? [];
    return array_diff($original_relations, $current_relations);
  }


  /**
   * Checks if a relation entity item needs to be saved.
   *
   * @param array $entity_item
   *   The entity item array.
   *
   * @return bool
   *   TRUE if the item needs saving, FALSE otherwise.
   */
  private function relationNeedsSave(array $entity_item): bool {
    return !empty($entity_item['entity'])
      && $entity_item['entity'] instanceof NodeInterface
      && !empty($entity_item['needs_save']);
  }


  /**
   * Gets an entity form by delta from a form field.
   *
   * @param array $form_field
   *   The form field array.
   * @param int $delta
   *   The delta.
   *
   * @return array|null
   *   The entity form array or NULL.
   */
  private function getEntityFormByDelta($form_field, $delta): ?array {
    return $form_field['widget'][$delta]['inline_entity_form'] ?? null;
  }


  /**
   * Registers a new relation for binding to parent node.
   *
   * @param NodeInterface $entity
   *   The relation node entity.
   * @param string $foreign_key
   *   The foreign key field name.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  private function registerNewRelationForBinding(NodeInterface $entity, ?string $foreign_key, FormStateInterface $form_state): void{        
    if (!$foreign_key) {
      return;
    } 
    $form_state->set(['created_relation_ids', $entity->id()], $foreign_key);
  }  
}