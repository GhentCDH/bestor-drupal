<?php

namespace Drupal\relationship_nodes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Core\StringTranslation\StringTranslationTrait;



class RelationFieldConfigForm extends FormBase  {

  use StringTranslationTrait;

  protected $nodeType;
  protected $fieldName;

    public function getFormId() {
      return 'relation_field_config_form';
    }

    public function getTitle($node_type, $field_name) {
      return $this->t('Configure @field for @type', ['@field' => $field_name, '@type' => $node_type]);
    }

  public function buildForm(array $form, FormStateInterface $form_state, FieldConfig $field_config = NULL) {
    $this->fieldConfig = $field_config;
    $this->nodeType = $field_config->getTargetBundle();
    $this->fieldName = $field_config->getName();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $field_config->label(),
      '#required' => TRUE,
    ];

    $form['target_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Target node type'),
      '#options' => $this->getAllNodeTypes(),
      '#default_value' => $field_config->getTargetBundle(),
      '#required' => TRUE,
      '#multiple' => FALSE, 
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
}

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field = FieldConfig::load("node.{$this->nodeType}.{$this->fieldName}");
    $field->setLabel($form_state->getValue('label'));
    $field->setSetting('handler_settings', [
      'target_bundles' => [$form_state->getValue('target_bundle')]
    ]);
    $field->save();

    $this->messenger()->addStatus($this->t('Field @field updated.', ['@field' => $this->fieldName]));
  }

 
  protected function getAllNodeTypes() {
    $options = [];
    foreach (NodeType::loadMultiple() as $type) {
      $options[$type->id()] = $type->label();
    }
    return $options;
  }

  protected function getCurrentTargetBundle($node_type, $field_name) {
      $field = \Drupal::entityTypeManager()->getStorage('field_config')->load("node.$node_type.$field_name");
      if ($field) {
          $handler_settings = $field->getSetting('handler_settings');
          if (!empty($handler_settings['target_bundles']) && is_array($handler_settings['target_bundles'])) {
              return reset($handler_settings['target_bundles']);
          }
      }
      return NULL;
  }
}