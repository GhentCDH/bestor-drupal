<?php

namespace Drupal\relationship_nodes\Form;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\inline_entity_form\Form\EntityInlineForm;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Service\ConfigManager;
use Drupal\relationship_nodes\Service\RelationSyncService;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Symfony\Component\DependencyInjection\ContainerInterface;



class RelationExtendedEntityInlineForm extends EntityInlineForm {

  protected RouteMatchInterface $routeMatch;
  protected RelationshipInfoService $infoService;
  protected RelationSyncService $syncService;
  protected ConfigManager $configManager;

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->routeMatch = $container->get('current_route_match');
    $instance->infoService = $container->get('relationship_nodes.relationship_info_service');
    $instance->syncService = $container->get('relationship_nodes.relation_sync_service');
    $instance->configManager = $container->get('relationship_nodes.config_manager');

    return $instance;
  }


  public function entityForm(array $entity_form, FormStateInterface $form_state) {
    $entity_form = parent::entityForm($entity_form, $form_state);
    
    if($entity_form['#form_mode'] != $this->configManager->getRelationFormMode()){
      return $entity_form;
    } 

    $foreign_key = $this->infoService->getEntityFormForeignKeyField($entity_form, $form_state);

    if($foreign_key){
      $entity_form[$foreign_key]['#attributes']['hidden'] = 'hidden';
      $entity_form['#rn__foreign_key'] = $foreign_key;
    }
    return $entity_form;
  }


  public function entityFormSubmit(array &$entity_form, FormStateInterface $form_state) {   

    parent::entityFormSubmit($entity_form, $form_state);

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