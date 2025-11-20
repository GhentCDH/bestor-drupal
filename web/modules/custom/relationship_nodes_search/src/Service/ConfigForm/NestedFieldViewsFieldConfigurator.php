<?php

namespace Drupal\relationship_nodes_search\Service\ConfigForm;

use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\Field\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\Service\Field\NestedFieldHelper;
use Drupal\relationship_nodes_search\Service\Field\ChildFieldEntityReferenceHelper;

/**
 * Configuration form builder for Views field display.
 */
class NestedFieldViewsFieldConfigurator extends NestedFieldViewsConfiguratorBase {

  protected ChildFieldEntityReferenceHelper $childReferenceHelper;


  /**
   * Constructs a NestedFieldViewsFieldConfigurator object.
   *
   * @param CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper service.
   * @param NestedFieldHelper $nestedFieldHelper
   *   The nested field helper service.
   * @param ChildFieldEntityReferenceHelper $childReferenceHelper
   *   The child reference helper service.
   */
  public function __construct(
    CalculatedFieldHelper $calculatedFieldHelper,
    NestedFieldHelper $nestedFieldHelper,
    ChildFieldEntityReferenceHelper $childReferenceHelper
  ) {
    parent::__construct($calculatedFieldHelper, $nestedFieldHelper);
    $this->childReferenceHelper = $childReferenceHelper;
  }


  /**
   * Build configuration form for a single field.
   * 
   * Creates the admin UI for configuring how a nested field should be displayed.
   *
   * @param array &$form
   *   The form array.
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   Parent field name.
   * @param string $child_fld_nm
   *   Child field name.
   * @param array $field_settings
   *   Current field settings.
   */
  public function buildFieldConfigForm(
    array &$form, 
    Index $index, 
    string $sapi_fld_nm, 
    string $child_fld_nm, 
    array $field_settings
  ): void {
    $is_enabled = !empty($field_settings[$child_fld_nm]['enabled']);
    $disabled_state = $this->buildFieldDisabledState($child_fld_nm);
    $can_link = $this->childReferenceHelper->nestedFieldCanLink($index, $sapi_fld_nm, $child_fld_nm);
    
    $form['relation_display_settings']['field_settings'][$child_fld_nm] = [
      '#type' => 'details',
      '#title' => $child_fld_nm,
      '#open' => $is_enabled,
    ];

    $this->addFieldEnableCheckbox($form, $child_fld_nm, $field_settings);

    if ($can_link) {
      $this->addDisplayModeSelector($form, $child_fld_nm, $field_settings, $disabled_state);
    }

    $this->addFieldLabel($form, $child_fld_nm, $field_settings, $disabled_state);
    $this->addFieldWeight($form, $child_fld_nm, $field_settings, $disabled_state);
    $this->addHideLabelCheckbox($form, $child_fld_nm, $field_settings, $disabled_state);

    if (!$this->calculatedFieldHelper->isCalculatedChildField($child_fld_nm)) {
      $this->addMultipleSeparator($form, $child_fld_nm, $field_settings, $disabled_state);
    }
  }


  /**
   * Add enable checkbox for field display settings.
   */
  protected function addFieldEnableCheckbox(array &$form, string $child_fld_nm, array $field_settings): void {
    $form['relation_display_settings']['field_settings'][$child_fld_nm]['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display this field'),
      '#default_value' => !empty($field_settings[$child_fld_nm]['enabled']),
    ];
  }


  /**
   * Add field label for field display (overrides base class for different container).
   */
  protected function addFieldLabel(array &$form, string $child_fld_nm, array $field_settings, array $disabled_state): void {
    $form['relation_display_settings']['field_settings'][$child_fld_nm]['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom label'),
      '#default_value' => $field_settings[$child_fld_nm]['label'] 
        ?? $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm),
      '#description' => $this->t('Custom label for this field.'),
      '#size' => 30,
      '#states' => $disabled_state,
    ];
  }


  /**
   * Add weight configuration for field display (overrides base class for different container).
   */
  protected function addFieldWeight(array &$form, string $child_fld_nm, array $field_settings, array $disabled_state): void {
    $form['relation_display_settings']['field_settings'][$child_fld_nm]['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $field_settings[$child_fld_nm]['weight'] ?? 0,
      '#description' => $this->t('Fields with lower weights appear first.'),
      '#size' => 5,
      '#states' => $disabled_state,
    ];
  }


  /**
   * Add display mode selector for entity reference fields.
   */
  protected function addDisplayModeSelector(array &$form, string $child_fld_nm, array $field_settings, array $disabled_state): void {
    $form['relation_display_settings']['field_settings'][$child_fld_nm]['display_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display mode'),
      '#options' => [
        'raw' => $this->t('Raw value (ID)'),
        'label' => $this->t('Label'),
        'link' => $this->t('Label as link'),
      ],
      '#default_value' => $field_settings[$child_fld_nm]['display_mode'] ?? 'raw',
      '#description' => $this->t('How to display this field value.'),
      '#states' => $disabled_state,
    ];
  }


  /**
   * Add hide label checkbox.
   */
  protected function addHideLabelCheckbox(array &$form, string $child_fld_nm, array $field_settings, array $disabled_state): void {
    $form['relation_display_settings']['field_settings'][$child_fld_nm]['hide_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide label in output'),
      '#default_value' => $field_settings[$child_fld_nm]['hide_label'] ?? FALSE,
      '#states' => $disabled_state,
    ];
  }


  /**
   * Add multiple value separator configuration.
   */
  protected function addMultipleSeparator(array &$form, string $child_fld_nm, array $field_settings, array $disabled_state): void {
    $form['relation_display_settings']['field_settings'][$child_fld_nm]['multiple_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Multiple Values Separator'),
      '#default_value' => $field_settings[$child_fld_nm]['multiple_separator'] ?? ', ',
      '#description' => $this->t('Configure how to separate multiple values.'),
      '#size' => 10,
      '#states' => $disabled_state,
    ];
  }


  /**
   * Build disabled state for field display settings.
   */
  protected function buildFieldDisabledState(string $child_fld_nm): array {
    return [
      'disabled' => [
        ':input[name="options[relation_display_settings][field_settings][' . $child_fld_nm . '][enabled]"]' => ['checked' => FALSE],
      ],
    ];
  }
}