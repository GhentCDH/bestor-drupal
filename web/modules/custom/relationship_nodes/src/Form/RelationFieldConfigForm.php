<?php

namespace Drupal\relationship_nodes\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\relationship_nodes\RelationEntityType\AdminUserInterface\FieldConfigUiUpdater;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;



class RelationFieldConfigForm extends FormBase {

  use StringTranslationTrait;

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FieldNameResolver $fieldResolver;
  protected RelationBundleSettingsManager $settingsManager;
  protected FieldConfigUiUpdater $uiUpdater;
  protected ?FieldConfig $fieldConfig = null;
  protected ?string $fieldName = null;
  protected ?string $entityType = null;
  protected ?string $bundle = null;


  public function __construct(
    EntityTypeManagerInterface $entityTypeManager, 
    FieldNameResolver $fieldResolver, 
    RelationBundleSettingsManager $settingsManager,
    FieldConfigUiUpdater $uiUpdater
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldResolver = $fieldResolver;
    $this->settingsManager = $settingsManager;
    $this->uiUpdater = $uiUpdater;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('relationship_nodes.field_name_resolver'),
      $container->get('relationship_nodes.relation_bundle_settings_manager'),
      $container->get('relationship_nodes.field_config_ui_updater')
    );
  }

  public function getFormId() {
    return 'relation_field_config_form';
  }

  public function getTitle($bundle, $field_name) {
    $bundle_entity = $this->entityTypeManager
      ->getStorage($this->entityType === 'node' ? 'node_type' : 'taxonomy_vocabulary')
      ->load($bundle);

    $bundle_label = $bundle_entity ? $bundle_entity->label() : $bundle;

    return $this->t('Configure @field for @type', [
      '@field' => $field_name,
      '@type' => $bundle_label,
    ]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, FieldConfig $field_config = NULL) {
    if(empty($field_config)){
      return $form;
    }
    
    $this->fieldConfig = $field_config;
    $this->entityType = $field_config->getTargetEntityTypeId();
    $this->fieldName = $field_config->getName();
    $this->bundle = $field_config->getTargetBundle();

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
      if($this->fieldName == $this->fieldResolver->getRelationTypeField()){
        $form['target_bundle']['#title'] = $this->t('Target relation type vocabulary');
        $form['target_bundle']['#options'] = $this->getAllRelationVocabs();
      } elseif(in_array($this->fieldName,$this->fieldResolver->getRelatedEntityFields())){
          $form['target_bundle']['#title'] = $this->t('Target node type');
          $form['target_bundle']['#options'] = $this->getAllNodeTypes();
      }
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    if (!$this->settingsManager->isRelationEntity($this->bundle)) {
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
      $target = $form_state->getValue('target_bundle');
      $field->setSetting('handler_settings', [
        'target_bundles' => [$target => $target],
      ]);
    }

    $field->save();

    $this->messenger()->addStatus($this->t('Field @field updated.', [
      '@field' => $this->fieldName,
    ]));

    $form_state->setRedirectUrl($this->uiUpdater->getRedirectUrl($field));
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
    foreach (Vocabulary::loadMultiple() as $type) {
      if($this->settingsManager->isRelationVocab($type)){
        $options[$type->id()] = $type->label();
      } 
    }
    return $options;
  }


  protected function getCurrentTargetBundle($bundle, $field_name):?string {
    $field = $this->entityTypeManager
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