<?php

namespace Drupal\relationship_nodes\RelationEntity\UserInterface\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationExtensionWidgetSubmit;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationEntityFormHandler;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationSyncService;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationFormStateHelper;

class ParentNodeFormAlter {

  protected RelationSyncService $syncService;
  protected RelationEntityFormHandler $relationFormHandler;
  protected RelationFormStateHelper $formStateHelper;

  public function __construct(
    RelationSyncService $relationSyncService, 
    RelationEntityFormHandler $relationFormHandler,
    RelationFormStateHelper $formStateHelper,
  ) {
    $this->syncService = $relationSyncService;
    $this->relationFormHandler = $relationFormHandler;
    $this->formStateHelper = $formStateHelper;  
  }

  public function alterForm(array &$form, FormStateInterface $form_state, $form_id) {
    $entity = $this->formStateHelper->getParentFormNode($form_state);
    if (!$entity) {
      return;
    }

    $relation_subforms = $this->formStateHelper->getRelationSubformFields($form_state);
    if (empty($relation_subforms)) {
      return;
    }

    $this->relationFormHandler->addParentFieldConfig($form, $relation_subforms);

    if ($entity->isNew()) {
      $form['actions']['submit']['#submit'][] = [$this, 'syncRelations'];
    }

    if (!is_null($form_state->get('inline_entity_form'))) {
      RelationExtensionWidgetSubmit::attach($form, $form_state);
    }
  }

  public function syncRelations(array &$form, FormStateInterface $form_state) {
    $this->syncService->bindNewRelationsToParent($form_state);
  }
}