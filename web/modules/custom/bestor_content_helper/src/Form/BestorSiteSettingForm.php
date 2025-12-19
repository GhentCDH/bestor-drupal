<?php

namespace Drupal\bestor_content_helper\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

class BestorSiteSettingForm extends ContentEntityForm {

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    
    $entity = $this->entity;
    
    $form['info'] = [
      '#type' => 'container',
      '#weight' => -100,
    ];
    
    $form['info']['setting_label'] = [
      '#type' => 'item',
      '#markup' => '<strong>' . $entity->label() . '</strong> (id: ' . $entity->id() . ')',
    ];
    
    $description = $entity->get('description')->value;
    if ($description) {
      $form['info']['setting_description'] = [
        '#type' => 'item',
        '#markup' => $description,
      ];
    }
    
    $form['info']['separator'] = [
      '#markup' => '<hr>',
    ];

    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default value (English)'),
      '#default_value' => $entity->get('value')->value ?? '',
    ];

    $form['value_nl'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dutch translation'),
      '#default_value' => $entity->get('value_nl')->value ?? '',
      '#description' => $this->t('Leave empty to use the default value.')
    ];
   
    $form['value_fr'] = [
      '#type' => 'textfield',
      '#title' => $this->t('French translation'),
      '#default_value' => $entity->get('value_fr')->value ?? '',
      '#description' => $this->t('Leave empty to use the default value.')
    ];
    
    // Hide non-editable fields
    if (isset($form['label'])) {
      $form['label']['#access'] = FALSE;
    }
    if (isset($form['description'])) {
      $form['description']['#access'] = FALSE;
    }
    if (isset($form['id'])) {
      $form['id']['#access'] = FALSE;
    }
    if (isset($form['setting_group'])) {
      $form['setting_group']['#access'] = FALSE;
    }
    return $form;
  }

  
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $entity = $this->getEntity();
    $this->messenger()->addStatus($this->t('Saved setting %label.', ['%label' => $entity->label()]));
    $form_state->setRedirect('entity.bestor_site_setting.collection');
    return $result;
  }
}