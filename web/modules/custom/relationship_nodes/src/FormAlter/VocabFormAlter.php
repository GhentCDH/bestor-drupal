<?php

namespace Drupal\relationship_nodes\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class VocabFormAlter implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return ['hook_event_dispatcher.form_taxonomy_vocabulary_form.alter' => 'alterForm'];
  }

  public function alterForm(array &$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocab */
    $vocab = $form_state->getFormObject()->getEntity();

    $form['relationship_nodes'] = [
      '#type' => 'details',
      '#title' => t('Relation type settings'),
      '#open' => TRUE,
    ];

    $form['relationship_nodes']['is_relation_type_vocab'] = [
      '#type' => 'checkbox',
      '#title' => t('This vocabulary is a relation type vocabulary'),
      '#default_value' => $vocab->getThirdPartySetting('relationship_nodes', 'is_relation_type_vocab', FALSE),
    ];

    $form['relationship_nodes']['selfreferencing'] = [
      '#type' => 'checkbox',
      '#title' => t('Self-referencing'),
      '#default_value' => $vocab->getThirdPartySetting('relationship_nodes', 'selfreferencing', FALSE),
      '#states' => [
        'visible' => [
          ':input[name="is_relation_type_vocab"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['#entity_builders'][] = [get_class($this), 'entityBuilder'];
  }

  public static function entityBuilder($entity_type, Vocabulary $vocab, array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $vocab->setThirdPartySetting('relationship_nodes', 'is_relation_type_vocab', !empty($values['is_relation_type_vocab']));
    $vocab->setThirdPartySetting('relationship_nodes', 'selfreferencing', !empty($values['selfreferencing']));
  }
}
