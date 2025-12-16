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
      '#title' => $this->t('Setting'),
      '#markup' => '<strong>' . $entity->label() . '</strong>',
    ];
    
    $description = $entity->get('description')->value;
    if ($description) {
      $form['info']['setting_description'] = [
        '#type' => 'item',
        '#title' => $this->t('Description'),
        '#markup' => $description,
      ];
    }
    
    $form['info']['setting_id'] = [
      '#type' => 'item',
      '#title' => $this->t('ID'),
      '#markup' => '<code>' . $entity->id() . '</code>',
    ];
    
    $form['info']['separator'] = [
      '#markup' => '<hr>',
    ];
    
    if (isset($form['label'])) {
      $form['label']['#access'] = FALSE;
    }
    if (isset($form['description'])) {
      $form['description']['#access'] = FALSE;
    }
    if (isset($form['id'])) {
      $form['id']['#access'] = FALSE;
    }
    
    if (isset($form['value'])) {
      $form['value']['#weight'] = -50;
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