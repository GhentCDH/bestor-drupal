<?php

namespace Drupal\bestor_custom_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Database search form.
 */
class DatabaseFullTextSearchForm extends FormBase {

  protected $languageManager;

  public function getFormId() {
    return 'database_full_text_search_form';
  }

  
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'bef-exposed-form';
    $form['#attributes']['class'][] = 'views-exposed-form';
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('view.database.page_1', [], ['language' => \Drupal::languageManager()->getCurrentLanguage()])->toString();
    
    $form['fullsearch'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fulltext search'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Search...'),
      '#size' => 30,
      '#maxlength' => 128,
      '#required' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#id' => 'edit-submit-search',
      '#name' => '',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
