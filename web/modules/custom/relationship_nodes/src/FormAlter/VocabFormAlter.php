<?php

namespace Drupal\relationship_nodes\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;


class VocabFormAlter {

  use StringTranslationTrait;

  public function alterForm(array &$form, FormStateInterface $form_state, $form_id) {
    $relationEntityPreparer = \Drupal::service('relationship_nodes.relation_entity_type_preparer');
    $vocab = $relationEntityPreparer->getFormEntity($form_state);
    if (!$vocab instanceof Vocabulary) {
      return;
    }

    $form['relationship_nodes'] = [
      '#type' => 'details',
      '#title' => $this->t('Relation type settings'),
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#tree' => TRUE,
      '#attributes' => ['class' => ['relationship-nodes-settings-form']],
    ];

    $form['relationship_nodes']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This vocabulary is a relation type vocabulary'),
      '#default_value' => $relationEntityPreparer->getRelationEntityProperty($vocab, 'enabled'),
      '#description' => $this->t('If this is checked, this vocabulary will be validated as a relationship types list. It gets a mirror field that can contain the reverse relation type of the term.'),
      '#id' => 'relationship-nodes-enabled',
    ];

    $form['relationship_nodes']['referencing_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Relation type'),
      '#default_value' => $relationEntityPreparer->getRelationEntityProperty($vocab, 'referencing_type'),
      '#options' => [
        'self' => $this->t('Self-referencing (same content type)'),
        'cross' => $this->t('Cross-referencing (different content types)'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="relationship_nodes[enabled]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="relationship_nodes[enabled]"]' => ['checked' => TRUE],
        ],
      ],
      '#id' => 'relationship-nodes-referencing-type',
    ];

    $form['relationship_nodes']['confirm_mirror_change'] = [
      '#type' => 'hidden',
      '#default_value' => $form_state->getValue(['relationship_nodes', 'confirm_mirror_change']) ?? 0,
      '#attributes' => [
        'id' => 'relationship-nodes-confirm-mirror-change',
        'data-original' => $relationEntityPreparer->getRelationEntityProperty($vocab, 'referencing_type'),
      ],
    ];

    $form['#attached']['library'][] = 'relationship_nodes/mirror_change_confirm';

    $original_submit_callbacks = [];
    if (!empty($form['actions']['submit']['#submit'])) {
      $original_submit_callbacks = $form['actions']['submit']['#submit'];
      unset($form['actions']['submit']);
    }


    $form['actions']['confirm'] = [
      '#type' => 'button',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => [$this, 'openConfirmationModal'],
        'event' => 'click',
        'progress' => ['type' => 'none'],
        'disable-refocus' => TRUE,
      ],
    ];


    $form['actions']['hidden_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Hidden Save'),
      '#attributes' => [
        'style' => 'display:none;',
        'id' => 'relationship-nodes-hidden-submit',
      ],
      '#submit' => array_merge($original_submit_callbacks, [[$this, 'handleRelationEntitySubmission']]),
    ];

    $form['#validate'][] = [$this, 'validateConflicts'];
  }


  public function validateReferencingType(array &$form, FormStateInterface $form_state) {
  
}


  public function validateConflicts(array &$form, FormStateInterface $form_state) {
    \Drupal::service('relationship_nodes.relation_entity_type_preparer')->detectRelationEntityConfigConflicts($form, $form_state);
  }



  public function handleRelationEntitySubmission(array &$form, FormStateInterface $form_state) {
    \Drupal::service('relationship_nodes.relation_entity_type_preparer')->handleRelationEntitySubmission($form, $form_state);
  }


  public function openConfirmationModal(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $new_value = $form_state->getValue(['relationship_nodes', 'referencing_type']);
    $original_value = $form['relationship_nodes']['confirm_mirror_change']['#attributes']['data-original'];

    if (empty($form_state->getValue(['relationship_nodes', 'enabled'])) || !$original_value || $new_value === $original_value) {
      $response->addCommand(new InvokeCommand(
        '#relationship-nodes-hidden-submit',
        'click',
        []
      ));
      return $response;
    }

    $dialog_content = [
      '#type' => 'container',
      'message' => [
        '#markup' => $this->t('You changed the relation type. Are you sure you want to save these relationship settings?'),
      ],
      'actions' => [
        '#type' => 'container',
        'save' => [
          '#type' => 'button',
          '#value' => $this->t('Save'),
          '#attributes' => [
            'id' => 'relationship-nodes-modal-save',
            'class' => ['button', 'button--primary'],
          ],
        ],
        'cancel' => [
          '#type' => 'button',
          '#value' => $this->t('Cancel'),
          '#attributes' => [
            'id' => 'relationship-nodes-modal-cancel',
            'class' => ['button'],
          ],
          '#limit_validation_errors' => [],
          '#executes_submit_callback' => FALSE,
        ],
      ],
    ];

    $response->addCommand(new OpenModalDialogCommand(
      $this->t('Confirm save'),
      $dialog_content,
      ['width' => '400']
    ));

    return $response;
  }


}
