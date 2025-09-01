<?php

namespace Drupal\relationship_nodes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Url;


class RelationFieldConfigForm extends FormBase {

  use StringTranslationTrait;

  protected $entityType;
  protected $bundle;
  protected $fieldName;
  protected $fieldConfig;

  public function getFormId() {
    return 'relation_field_config_form';
  }

  public function getTitle($bundle, $field_name) {
    $bundle_entity = \Drupal::entityTypeManager()
      ->getStorage($this->entityType === 'node' ? 'node_type' : 'taxonomy_vocabulary')
      ->load($bundle);

    $bundle_label = $bundle_entity ? $bundle_entity->label() : $bundle;

    return $this->t('Configure @field for @type', [
      '@field' => $field_name,
      '@type' => $bundle_label,
    ]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, FieldConfig $field_config = NULL) {

    $this->fieldConfig = $field_config;
    $this->entityType = $field_config->getTargetEntityTypeId();
    $this->fieldName = $field_config->getName();
    $this->bundle = $field_config->getTargetBundle();

    $settingsManager = \Drupal::service('relationship_nodes.relation_bundle_settings_manager');
    dpm($form_state->getFormObject());


    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $field_config->label(),
      '#required' => TRUE,
    ];


    if ($this->entityType === 'node') {
      $form['target_bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Target node type'),
        '#options' => $this->getAllNodeTypes(),
        '#default_value' => $this->getCurrentTargetBundle($this->bundle, $this->fieldName),
        '#required' => TRUE,
        '#multiple' => FALSE,
      ];
      if($this->fieldName == 'relation_type'){
        $form['target_bundle']['#title'] = $this->t('Target relation type vocabulary');
        $form['target_bundle']['#options'] = $this->getAllRelationVocabs();
      } elseif($this->fieldName == 'related_entity_1' || $this->fieldName == 'related_entity_2'){
          $form['target_bundle']['#title'] = $this->t('Target node type');
          $form['target_bundle']['#options'] = $this->getAllNodeTypes();
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    if (!$settingsManager->isRelationEntity($this->bundle)) {
      $form['delete'] = [
          '#type' => 'link',
          '#title' => $this->t('Delete RN Field'),
          '#url' => Url::fromRoute('relationship_nodes.rn_field_delete', [
              'field_config' => $this->fieldConfig->id(),
          ]),
          '#attributes' => [
              'class' => ['button', 'button--danger'],
          ],
      ];
  }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field = FieldConfig::load("{$this->entityType}.{$this->bundle}.{$this->fieldName}");
    if (!$field) {
      $this->messenger()->addError($this->t('Field not found.'));
      return;
    }

    $field->setLabel($form_state->getValue('label'));


    if ($this->entityType === 'node') {
      $field->setSetting('handler_settings', [
        'target_bundles' => [$form_state->getValue('target_bundle')],
      ]);
    }

    $field->save();

    $this->messenger()->addStatus($this->t('Field @field updated.', [
      '@field' => $this->fieldName,
    ]));
  }

  protected function getAllNodeTypes() {
    $options = [];
    foreach (NodeType::loadMultiple() as $type) {
      $options[$type->id()] = $type->label();
    }
    return $options;
  }

    protected function getAllRelationVocabs() {
    $options = [];
    $settingsManager = \Drupal::service('relationship_nodes.relation_bundle_settings_manager');
    foreach (Vocabulary::loadMultiple() as $type) {
      if($settingsManager->isRelationVocab($type)){
        $options[$type->id()] = $type->label();
      } 
    }
    return $options;
  }

  protected function getCurrentTargetBundle($bundle, $field_name) {
    $field = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->load("{$this->entityType}.$bundle.$field_name");

    if ($field) {
      $handler_settings = $field->getSetting('handler_settings');
      if (!empty($handler_settings['target_bundles']) && is_array($handler_settings['target_bundles'])) {
        return reset($handler_settings['target_bundles']);
      }
    }
    return NULL;
  }

}
