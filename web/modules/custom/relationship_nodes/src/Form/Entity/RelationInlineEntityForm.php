<?php

namespace Drupal\relationship_nodes\Form\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\inline_entity_form\Form\NodeInlineForm;
use Drupal\relationship_nodes\RelationData\NodeHelper\ForeignKeyResolver;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Extended inline entity form for relationship nodes.
 *
 * Handles automatic population of foreign key fields in inline entity forms.
 */
class RelationInlineEntityForm extends NodeInlineForm {


  protected RouteMatchInterface $routeMatch;
  protected FieldNameResolver $fieldNameResolver;
  protected ForeignKeyResolver $foreignKeyResolver;


  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->routeMatch = $container->get('current_route_match');
    $instance->fieldNameResolver = $container->get('relationship_nodes.field_name_resolver');
    $instance->foreignKeyResolver = $container->get('relationship_nodes.foreign_key_field_resolver');

    return $instance;
  }


  /**
   * {@inheritdoc}
   */
  public function entityForm(array $entity_form, FormStateInterface $form_state) {
    $entity_form = parent::entityForm($entity_form, $form_state);
    if (empty($entity_form['#relation_extended_widget']) || $entity_form['#relation_extended_widget'] !== true) {
      return  $entity_form;
    }
    $relation_entity = $entity_form['#entity'];
    if(!$relation_entity instanceof NodeInterface) {
      return $entity_form;
    }
    $foreign_key = $this->foreignKeyResolver->getEntityFormForeignKeyField($relation_entity, $form_state);

    if ($foreign_key) {
      $entity_form[$foreign_key]['#attributes']['hidden'] = 'hidden';
      $entity_form['#rn__foreign_key'] = $foreign_key;
    }
    
    return $entity_form;
  }


  /**
   * {@inheritdoc}
   */
  public function entityFormSubmit(array &$entity_form, FormStateInterface $form_state) {   
    parent::entityFormSubmit($entity_form, $form_state);

    if (empty($entity_form['#relation_extended_widget']) || $entity_form['#relation_extended_widget'] !== true) {
      return;
    }

    if ($form_state->get('inline_entity_form') == null) {
      return;
    }

    $current_node = $this->routeMatch->getParameter('node');
    if (!($current_node instanceof NodeInterface)) {
      return; // If a new node is being created, a submit handler creates the relation later.
    }

    if (empty($entity_form['#rn__foreign_key'])) {
      return;
    }
   
    $relation_node = $entity_form['#entity'];
    $foreign_key = $entity_form['#rn__foreign_key'];
    
    if (!is_string($foreign_key) || !$relation_node->hasField($foreign_key)) {
      return;
    }

    $relation_node->set($foreign_key, $current_node->id());
  }
}