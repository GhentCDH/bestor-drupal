<?php

namespace Drupal\relationship_nodes\Form;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\inline_entity_form\Form\NodeInlineForm;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntity\RelationNode\ForeignKeyFieldResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;



class RelationExtendedEntityInlineForm extends NodeInlineForm {

  protected RouteMatchInterface $routeMatch;
  protected FieldNameResolver $fieldNameResolver;
  protected ForeignKeyFieldResolver $foreignKeyResolver;

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->routeMatch = $container->get('current_route_match');
    $instance->fieldNameResolver = $container->get('relationship_nodes.field_name_resolver');
    $instance->foreignKeyResolver = $container->get('relationship_nodes.foreign_key_field_resolver');

    return $instance;
  }


  public function entityForm(array $entity_form, FormStateInterface $form_state) {
    $entity_form = parent::entityForm($entity_form, $form_state);
    if(empty($entity_form['#relation_extension_widget']) || $entity_form['#relation_extension_widget'] !== true){
      return  $entity_form;
    }

    $foreign_key = $this->foreignKeyResolver->getEntityFormForeignKeyField($entity_form, $form_state);

    if($foreign_key){
      $entity_form[$foreign_key]['#attributes']['hidden'] = 'hidden';
      $entity_form['#rn__foreign_key'] = $foreign_key;
    }
    return $entity_form;
  }


  public function entityFormSubmit(array &$entity_form, FormStateInterface $form_state) {   

    parent::entityFormSubmit($entity_form, $form_state);

    if(empty($entity_form['#relation_extension_widget']) || $entity_form['#relation_extension_widget'] !== true){
      return;
    }

    if($form_state->get('inline_entity_form') == null){
      return;
    }

    $current_node = $this->routeMatch->getParameter('node');
    if(!($current_node instanceof Node)) {
      return; // If a new node is being created, a submit handler creates the relation later.
    }

    if(empty($entity_form['#rn__parent_field']) || empty($entity_form['#rn__foreign_key'])){
      return;
    }

    $parent_field = $entity_form['#rn__parent_field'];

    if(!is_string($parent_field) || !str_starts_with($parent_field, 'computed_relationshipfield__')) {
      return;
    }
    
    $relation_node = $entity_form['#entity'];
    $foreign_key = $entity_form['#rn__foreign_key'];
    
    if(!is_string($foreign_key) || !$relation_node->hasField($foreign_key)) {
      return;
    }

    $relation_node->set($foreign_key, $current_node->id());
  }

}