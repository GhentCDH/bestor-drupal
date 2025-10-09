<?php

namespace Drupal\relationship_nodes\RelationEntity\UserInterface\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationExtensionWidgetSubmit;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationFormHelper;

class ParentNodeFormAlter {

  protected RelationSyncService $syncService;
  protected RelationFormHelper $formHelper;

  public function __construct(
    RelationFormHelper $formHelper
  ) {
    $this->formHelper = $formHelper;  
  }

  /*
  * Alter target node forms: add relationship nodes handling to IEF form states (if available)
  * The default IEF handling submits first the subforms, and afterwards the parent form.
  * For our case changing this order would be easiest.
  * Since the default IEF handling may also be required, another workflow was chosen.
  */
  
  public function alterForm(array &$form, FormStateInterface $form_state, $form_id) {  
    if(!$this->formHelper->isParentFormWithRelationSubforms($form, $form_state)){
      return;
    }
    
    $target_entity = $this->formHelper->getParentFormNode($form_state);
    if ($target_entity->isNew()) {
      $form['actions']['submit']['#submit'][] = [$this, 'bindNewRelationsToParent'];
    }
    RelationExtensionWidgetSubmit::updateDefaultSubmit($form, $form_state);
  }


  public function bindNewRelationsToParent(array &$form, FormStateInterface $form_state) {
    $syncService = \Drupal::service('relationship_nodes.relation_sync_service');
    $this->syncService->bindNewRelationsToParent($form_state);
  }
}