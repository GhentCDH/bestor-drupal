<?php

namespace Drupal\relationship_nodes\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationExtensionWidgetSubmit;
use Drupal\relationship_nodes\Service\RelationSyncService;

class ParentNodeFormAlter {

  protected RelationSyncService $syncService;

  public function __construct(RelationSyncService $relationSyncService) {
    $this->syncService = $relationSyncService;
  }

  public function alterForm(array &$form, FormStateInterface $form_state, $form_id) {
    $entity = $this->syncService->getParentFormNode($form_state);
    if (!$entity) {
      return;
    }

    $relation_subforms = $this->syncService->getRelationSubformFields($form_state);
    if (empty($relation_subforms)) {
      return;
    }

    $this->syncService->addParentFieldConfig($form, $relation_subforms);

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