<?php

namespace Drupal\relationship_nodes\RelationEntityType\AdminUserInterface;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\relationship_nodes\RelationEntityType\AdminUserInterface\FieldConfigUiUpdater;


/**
 * Service for handling relationship bundle form submissions.
 */
class RelationBundleFormHandler {

  use StringTranslationTrait;

  protected RelationBundleSettingsManager $settingsManager;
  protected RelationFieldConfigurator $fieldConfigurator;
  protected FieldConfigUiUpdater $fieldUiUpdater;


  /**
   * Constructs a RelationBundleFormHandler object.
   *
   * @param RelationBundleSettingsManager $settingsManager
   *   The settings manager.
   * @param RelationFieldConfigurator $fieldConfigurator
   *   The field configurator.
   * @param FieldConfigUiUpdater $fieldUiUpdater
   *   The field UI updater.
   */
  public function __construct(
    RelationBundleSettingsManager $settingsManager,
    RelationFieldConfigurator $fieldConfigurator,
    FieldConfigUiUpdater $fieldUiUpdater,
  ) {
    $this->settingsManager = $settingsManager;
    $this->fieldConfigurator = $fieldConfigurator;
    $this->fieldUiUpdater = $fieldUiUpdater;
  }


  /**
   * Handles form submission for relationship bundle forms.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function handleSubmission(array &$form, FormStateInterface $form_state): void {
    $entity = $this->getFormEntity($form_state);
    if (!$entity) {
      return;
    }
    $values = $form_state->getValue('relationship_nodes') ?? [];
    $this->settingsManager->setProperties($entity, $values); 
    if (!$this->settingsManager->isRelationEntity($entity)) {
      return;
    }

    $updates = $this->fieldConfigurator->implementFieldUpdates($entity);

    if (isset($updates['created'])) {
      $this->showFieldCreationMessage($entity, $updates['created']);
    }
  }



  /**
   * Gets the form entity from form state.
   *
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return NodeType|Vocabulary|null
   *   The form entity or NULL.
   */
  public function getFormEntity(FormStateInterface $form_state): NodeType|Vocabulary|null {
    $entity = $form_state->getFormObject()->getEntity();
    return ($entity instanceof NodeType || $entity instanceof Vocabulary) ? $entity : null;
  }


  protected function showFieldCreationMessage(ConfigEntityBundleBase $entity, array $missing_fields): void {
    if (empty($missing_fields)) {
      return;
    }

    $url_info = $this->fieldUiUpdater->getDefaultRoutingInfo($this->settingsManager->getEntityTypeObjectClass($entity));
    $url = Url::fromRoute($url_info['field_ui_fields_route'], [
      $url_info['bundle_param_key'] => $entity->id(),
    ]);

    $link = Link::fromTextAndUrl($this->t('Manage fields'), $url)->toString();

    if ($entity instanceof NodeType) {
      $message = 'The following relationship fields were created but need to be configured: @fields. @link';
    } else {
      $message = 'The following relationship fields were created: @fields. You can review them here: @link';
    }
    
    \Drupal::messenger()->addStatus($this->t(
      $message, ['@fields' => implode(', ', array_keys($missing_fields)), '@link' => $link]
    ));
  }
}