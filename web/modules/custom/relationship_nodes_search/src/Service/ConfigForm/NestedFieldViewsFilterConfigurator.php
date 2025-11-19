<?php

namespace Drupal\relationship_nodes_search\Service\ConfigForm;

use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\Field\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\Service\Field\ChildFieldEntityReferenceHelper;
use Drupal\relationship_nodes_search\Service\Query\FilterOperatorHelper;
use Drupal\relationship_nodes_search\Service\Field\NestedFieldHelper;
use Drupal\relationship_nodes_search\Service\ConfigForm\NestedFieldViewsConfiguratorBase;

/**
 * Configuration form builder for Views filter fields.
 */
class NestedFieldViewsFilterConfigurator extends NestedFieldViewsConfiguratorBase {

  protected ChildFieldEntityReferenceHelper $childReferenceHelper;
  protected FilterOperatorHelper $operatorHelper;


public function __construct(
  CalculatedFieldHelper $calculatedFieldHelper,
  NestedFieldHelper $nestedFieldHelper,
  ChildFieldEntityReferenceHelper $childReferenceHelper,
  FilterOperatorHelper $operatorHelper
) {
  parent::__construct($calculatedFieldHelper, $nestedFieldHelper);
  $this->childReferenceHelper = $childReferenceHelper;
  $this->operatorHelper = $operatorHelper;
}


  /**
   * Build nested widget configuration form.
   */
  public function buildConfigForm(
    array &$form,
    Index $index,
    string $sapi_fld_nm,
    array $child_fld_nms,
    array $child_fld_settings
  ): void {
    $form['filter_field_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter fields'),
      '#description' => $this->t('Select which fields should be available for filtering.'),
      '#tree' => TRUE,
    ];

    foreach ($child_fld_nms as $child_fld_nm) {
      $is_enabled = !empty($child_fld_settings[$child_fld_nm]['enabled']);
      $disabled_state = $this->getFieldDisabledState($child_fld_nm);

      $form['filter_field_settings'][$child_fld_nm] = [
        '#type' => 'details',
        '#title' => $child_fld_nm,
        '#open' => $is_enabled,
      ];

      $this->addFieldEnableCheckbox($form, $child_fld_nm, $child_fld_settings);
      $this->addFieldLabel($form, $child_fld_nm, $child_fld_settings, $disabled_state);
      $this->addFieldWidget($form, $index, $sapi_fld_nm, $child_fld_nm, $child_fld_settings, $disabled_state);
      $this->addFieldWeight($form, $child_fld_nm, $child_fld_settings, $disabled_state);
      $this->addFieldRequired($form, $child_fld_nm, $child_fld_settings, $disabled_state);
      $this->addFieldPlaceholder($form, $child_fld_nm, $child_fld_settings, $disabled_state);
      $this->addFieldOperator($form, $child_fld_nm, $child_fld_settings, $disabled_state);
      $this->addExposeFieldOperator($form, $child_fld_nm, $child_fld_settings, $disabled_state);
      $this->addFieldValueField($form, $child_fld_nm, $child_fld_settings, $disabled_state);
    }
  }


  /**
   * Add widget type selector.
   */
  protected function addFieldWidget(
    array &$form,
    Index $index,
    string $sapi_fld_nm,
    string $child_fld_nm,
    array $child_fld_settings,
    array $disabled_state
  ): void {
    $form['filter_field_settings'][$child_fld_nm]['widget'] = [
      '#type' => 'select',
      '#title' => $this->t('Widget type'),
      '#options' => [
        'textfield' => $this->t('Text field'),
        'select' => $this->t('Dropdown (from indexed values)'),
      ],
      '#default_value' => $child_fld_settings[$child_fld_nm]['widget'] ?? 'textfield',
      '#states' => $disabled_state,
      '#description' => $this->t('Dropdown automatically loads all unique values from the search index.'),
    ];

    // Display mode for dropdown options
    if ($this->childReferenceHelper->nestedFieldCanLink($index, $sapi_fld_nm, $child_fld_nm)) {
      $input_el_name = 'options[filter_field_settings][' . $child_fld_nm . '][widget]';

      $form['filter_field_settings'][$child_fld_nm]['select_display_mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Display mode for dropdown options'),
        '#options' => [
          'raw' => $this->t('Raw value (ID)'),
          'label' => $this->t('Label (entity name)'),
        ],
        '#default_value' => $child_fld_settings[$child_fld_nm]['select_display_mode'] ?? 'raw',
        '#description' => $this->t('How to display options in the dropdown. Only applies to entity reference fields.'),
        '#states' => array_merge(
          $disabled_state,
          [
            'visible' => [
              ':input[name="' . $input_el_name . '"]' => ['value' => 'select'],
            ],
          ]
        ),
      ];
    }
  }


  /**
   * Add required checkbox.
   */
  protected function addFieldRequired(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
    $form['filter_field_settings'][$child_fld_nm]['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required'),
      '#default_value' => $child_fld_settings[$child_fld_nm]['required'] ?? FALSE,
      '#description' => $this->t('Make this field required when exposed.'),
      '#states' => $disabled_state,
    ];
  }


  /**
   * Add placeholder configuration.
   */
  protected function addFieldPlaceholder(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
    $form['filter_field_settings'][$child_fld_nm]['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $child_fld_settings[$child_fld_nm]['placeholder'] ?? '',
      '#description' => $this->t('Placeholder text for the filter field.'),
      '#states' => $disabled_state,
    ];
  }


  /**
   * Add operator selector.
   */
  protected function addFieldOperator(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
    $form['filter_field_settings'][$child_fld_nm]['field_operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Operator'),
      '#options' => $this->operatorHelper->getOperatorOptions(),
      '#default_value' => $child_fld_settings[$child_fld_nm]['field_operator'] ?? $this->operatorHelper->getDefaultOperator(),
      '#description' => $this->t('Comparison operator for this field.'),
      '#states' => $disabled_state,
    ];
  }


  /**
   * Add expose operator checkbox.
   */
  protected function addExposeFieldOperator(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
    $form['filter_field_settings'][$child_fld_nm]['expose_field_operator'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Let user choose operator'),
      '#default_value' => $child_fld_settings[$child_fld_nm]['expose_field_operator'] ?? FALSE,
      '#description' => $this->t('Override global setting for this specific field.'),
      '#states' => array_merge(
        $disabled_state,
        [
          'visible' => [
            ':input[name="options[expose_operators]"]' => ['checked' => TRUE],
          ],
        ]
      ),
    ];
  }


  /**
   * Add default value field.
   */
  protected function addFieldValueField(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
    $form['filter_field_settings'][$child_fld_nm]['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => $child_fld_settings[$child_fld_nm]['value'] ?? '',
      '#description' => $this->t('Filter value (only used when filter is not exposed).'),
      '#states' => array_merge(
        $disabled_state,
        [
          'visible' => [
            ':input[name="options[expose_button][checkbox][checkbox]"]' => ['checked' => FALSE],
          ],
        ]
      ),
    ];
  }
}