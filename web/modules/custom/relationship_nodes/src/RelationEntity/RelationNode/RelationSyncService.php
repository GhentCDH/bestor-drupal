<?php

namespace Drupal\relationship_nodes\RelationEntity\RelationNode;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Plugin\Field\FieldType\ReferencingRelationshipItemList;
use Drupal\relationship_nodes\RelationEntity\RelationNode\ForeignKeyFieldResolver;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationFormHelper;


/**
 * Service for synchronizing relationship nodes.
 *
 * Handles binding relations to parent nodes, saving subform relations,
 * and removing orphaned relations.
 */
class RelationSyncService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RelationNodeInfoService $nodeInfoService;
  protected ForeignKeyFieldResolver $foreignKeyResolver;
  protected RelationFormHelper $formHelper;


  /**
   * Constructs a RelationSyncService object.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param RelationNodeInfoService $nodeInfoService
   *   The node info service.
   * @param ForeignKeyFieldResolver $foreignKeyResolver
   *   The foreign key field resolver.
   * @param RelationFormHelper $formHelper
   *   The form helper.
   */
  public function __construct(
      EntityTypeManagerInterface $entityTypeManager,
      RelationNodeInfoService $nodeInfoService,
      ForeignKeyFieldResolver $foreignKeyResolver,
      RelationFormHelper $formHelper
  ) {
      $this->entityTypeManager = $entityTypeManager;
      $this->nodeInfoService = $nodeInfoService;
      $this->foreignKeyResolver = $foreignKeyResolver;
      $this->formHelper = $formHelper;
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
      if ($node instanceof Node) {
        $node->delete();
      }
    }
  }


  /**
   * Saves relation nodes from subforms.
   *
   * @param Node $parent_node
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
    Node $parent_node, 
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


  /**
   * Gets relation nodes that were removed from a parent node.
   *
   * @param Node $parent_node
   *   The parent node.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   Array of removed relation node IDs.
   */
  public function getRemovedRelations(Node $parent_node, string $field_name): array {
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
      && $entity_item['entity'] instanceof Node
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
   * @param Node $entity
   *   The relation node entity.
   * @param array $entity_form
   *   The entity form array.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  private function registerNewRelationForBinding(Node $entity, array $entity_form, FormStateInterface $form_state): void{  
    $foreign_key = $this->foreignKeyResolver->getEntityFormForeignKeyField($entity_form, $form_state) ?? '';         
    if (!$foreign_key) {
      return;
    } 
    $form_state->set(['created_relation_ids', $entity->id()], $foreign_key);
  }  
}