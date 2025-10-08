<?php

namespace Drupal\bestor_advanced_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\block\Entity\Block;

/**
 * Advanced Search form with dynamic filters per content type.
 */
class AdvancedSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bestor_advanced_search_form';
  }

  /**
   * Helper: Geef content types opties (kan je vervangen door Search API index keys).
   */
  protected function getContentTypeOptions() {
    $database_types = ['concept', 'document', 'institution', 'instrument', 'person', 'place', 'story'];
    $types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($types as $type) {
        if(in_array($type->id(), $database_types)) {
            $options[$type->id()] = $type->label();
        }    
    }
    return $options;
  }

  /**
   * AJAX callback: vervang filters wrapper.
   */
  public function ajaxFilterCallback(array &$form, FormStateInterface $form_state) {
    return $form['filters_wrapper'];
  }

  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => $this->getContentTypeOptions(),
      '#ajax' => [
        'callback' => '::ajaxFilterCallback',
        'wrapper' => 'filters-wrapper',
        'event' => 'change',
      ],
      '#default_value' => $form_state->getValue('content_type') ?: NULL,
    ];

    $form['filters_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'filters-wrapper'],
    ];

    $selected_content_type = $form_state->getValue('content_type');
    if ($selected_content_type) {
      // TODO: Hier render je dynamisch facet blocks of filters per content type.

      // Voorbeeld: facet block machine name = 'facet_' . $selected_content_type .
      $block_id = 'facet_' . $selected_content_type;

      // Probeer facet block te laden.
      $block = Block::load($block_id);
      if ($block) {
        $block_view = \Drupal::entityTypeManager()
          ->getViewBuilder('block')
          ->view($block);
        $form['filters_wrapper']['facet_block'] = $block_view;
      }
      else {
        $form['filters_wrapper']['no_facets'] = [
          '#markup' => $this->t('Geen filters/facets gevonden voor %type', ['%type' => $selected_content_type]),
        ];
      }
    }
    else {
      $form['filters_wrapper']['empty'] = [
        '#markup' => $this->t('Selecteer eerst een content type om filters te tonen.'),
      ];
    }

    // TODO: hier zou je ook een Search API resultaten view kunnen embedden, of
    // een eigen Search API query laten lopen om resultaten te tonen.

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Hier kan je indien gewenst zoekactie uitvoeren of redirect.
  }
}
