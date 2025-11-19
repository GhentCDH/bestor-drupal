<?php

namespace Drupal\relationship_nodes_search\Service\ConfigForm;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\Field\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\Service\Field\NestedFieldHelper;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base helper for building nested field configuration forms.
 * 
 * Provides shared form elements and validation helpers for Views plugins
 * (both filter and field handlers).
 */
abstract class NestedFieldViewsConfiguratorBase {

  use StringTranslationTrait;

  protected CalculatedFieldHelper $calculatedFieldHelper;
  protected NestedFieldHelper $nestedFieldHelper;


  public function __construct(
    CalculatedFieldHelper $calculatedFieldHelper,
    NestedFieldHelper $nestedFieldHelper
  ) {
    $this->calculatedFieldHelper = $calculatedFieldHelper;
    $this->nestedFieldHelper = $nestedFieldHelper;
  }


  /**
   * Validate and prepare configuration for Views plugin options form.
   * 
   * Common validation pattern used by both filter and field handlers.
   * Performs field structure validation and adds error message to form if validation fails.
   *
   * @param mixed $index
   *   The index from $this->getIndex().
   * @param array $definition
   *   The plugin definition.
   * @param array &$form
   *   The form array to add error message to if validation fails.
   *
   * @return array|null
   *   Configuration array with 'index', 'field_name', 'available_fields', or NULL if invalid.
   */
  public function validateAndPreparePluginForm($index, array $definition, array &$form): ?array {
    // Extract field name from plugin definition
    $field_name = $this->nestedFieldHelper->getPluginParentFieldName($definition);
    
    // Validate field structure (delegated to field helper)
    $config = $this->nestedFieldHelper->validatePluginFieldConfiguration($index, $field_name);
    
    if (!$config) {
      // Add form error (Views-specific concern)
      $form['error'] = [
        '#markup' => $this->t('Cannot load index or field configuration, or no nested fields available.'),
      ];
      return null;
    }
    
    return $config;
  }


  /**
   * Add field enable checkbox.
   */
  protected function addFieldEnableCheckbox(array &$form, string $child_fld_nm, array $child_fld_settings): void {
    $form['filter_field_settings'][$child_fld_nm]['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this field'),
      '#default_value' => !empty($child_fld_settings[$child_fld_nm]['enabled']),
    ];
  }


  /**
   * Add field label configuration.
   */
  protected function addFieldLabel(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
    $form['filter_field_settings'][$child_fld_nm]['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $child_fld_settings[$child_fld_nm]['label']
        ?? $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm),
      '#description' => $this->t('Label shown to users.'),
      '#size' => 30,
      '#states' => $disabled_state,
    ];
  }


  /**
   * Add weight configuration.
   */
  protected function addFieldWeight(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
    $form['filter_field_settings'][$child_fld_nm]['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $child_fld_settings[$child_fld_nm]['weight'] ?? 0,
      '#description' => $this->t('Fields with lower weights appear first.'),
      '#size' => 5,
      '#states' => $disabled_state,
    ];
  }


  /**
   * Get form state for disabling fields when checkbox unchecked.
   */
  protected function getFieldDisabledState(string $child_fld_nm, ?string $context_prefix = null): array {
    $base_path = $context_prefix
      ? $context_prefix . '[filter_field_settings][' . $child_fld_nm . '][enabled]'
      : 'options[filter_field_settings][' . $child_fld_nm . '][enabled]';

    return [
      'disabled' => [
        ':input[name="' . $base_path . '"]' => ['checked' => FALSE],
      ],
    ];
  }


  /**
   * Save plugin options from form state.
   * 
   * Generic helper for saving options with optional nesting support.
   *
   * @param FormStateInterface $form_state
   *   The form state.
   * @param array $default_options
   *   Array of option keys with default values.
   * @param array &$options
   *   Reference to the options array to update.
   * @param string|null $wrapper_key
   *   Optional wrapper key (e.g., 'relation_display_settings').
   */
  public function savePluginOptions($form_state, array $default_options, array &$options, ?string $wrapper_key = null): void {
    foreach ($default_options as $option => $default) {
      if ($wrapper_key) {
        $value = $form_state->getValue(['options', $wrapper_key, $option]);
      } else {
        $value = $form_state->getValue(['options', $option]);
      }
      
      if (isset($value)) {
        $options[$option] = $value;
      }
    }
  }


  /**
   * Sort field configurations by weight.
   * 
   * Helper for Views plugins to sort field settings for display.
   *
   * @param array $fields
   *   Array of field configurations with 'weight' keys.
   *
   * @return array
   *   Sorted array (maintains keys).
   */
  public function sortFieldsByWeight(array $fields): array {
    uasort($fields, function($a, $b) {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });
    
    return $fields;
  }
}