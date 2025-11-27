<?php

namespace Drupal\relationship_nodes_search\Views\Config;

use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationField\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\QueryHelper\FilterOperatorHelper;
use Drupal\relationship_nodes_search\FieldHelper\NestedIndexFieldHelper;

/**
 * Configuration form builder for Views filter fields.
 * 
 * Extends the Views configurator base with filter-specific functionality:
 * - Widget type selection (textfield, select)
 * - Operator configuration
 * - Filter exposure settings
 * - Required/placeholder configuration
 */
class NestedFieldViewsFilterConfigurator extends NestedFieldViewsConfiguratorBase {

  protected FilterOperatorHelper $operatorHelper;

  /**
   * Constructs a NestedFieldViewsFilterConfigurator object.
   *
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver service.
   * @param NestedIndexFieldHelper $nestedFieldHelper
   *   The nested field helper service.
   * @param CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper service.
   * @param FilterOperatorHelper $operatorHelper
   *   The operator helper service.
   */
  public function __construct(
    FieldNameResolver $fieldNameResolver,
    NestedIndexFieldHelper $nestedFieldHelper,
    CalculatedFieldHelper $calculatedFieldHelper,
    FilterOperatorHelper $operatorHelper
  ) {
    parent::__construct($fieldNameResolver, $nestedFieldHelper, $calculatedFieldHelper);
    $this->operatorHelper = $operatorHelper;
  }

  /**
   * Build filter configuration form.
   * 
   * High-level method that orchestrates filter form building.
   *
   * @param array &$form
   *   The form array.
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   Parent field name.
   * @param array $child_fld_nms
   *   Available child field names.
   * @param array $saved_settings
   *   Current saved settings.
   */
  public function buildFilterConfigForm(
    array &$form,
    Index $index,
    string $sapi_fld_nm,
    array $child_fld_nms,
    array $saved_settings
  ): void {
    // PREPARE: Build field configurations with filter-specific context
    $field_configs = $this->prepareFilterFieldConfigurations(
      $index,
      $sapi_fld_nm,
      $child_fld_nms,
      $saved_settings
    );

    // RENDER: Build form using custom callback for filter-specific elements
    $this->buildConfigurationForm(
      $form,
      $field_configs,
      [], // No global settings for filters
      [
        'wrapper_key' => 'filter_settings',
        'field_settings_key' => 'filter_field_settings',
        'context_prefix' => $this->getViewsContextPrefix(),
        'show_template' => FALSE,
        'show_grouping' => FALSE,
        'show_sorting' => FALSE,
        'field_callback' => [$this, 'buildFilterFieldForm'],
      ]
    );
  }

  /**
   * Prepares field configurations for filter context.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent Search API field name.
   * @param array $child_fld_nms
   *   Available child field names.
   * @param array $saved_settings
   *   Current saved settings.
   *
   * @return array
   *   Field configurations with filter-specific context.
   */
  protected function prepareFilterFieldConfigurations(
    Index $index,
    string $sapi_fld_nm,
    array $child_fld_nms,
    array $saved_settings
  ): array {
    // Build filter-specific context
    $context = $this->buildFilterContext($index, $sapi_fld_nm, $child_fld_nms);
    
    // Use parent's prepare method with context
    return $this->prepareFieldConfigurations($child_fld_nms, $saved_settings, $context);
  }

  /**
   * Builds filter-specific context for field preparation.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent Search API field name.
   * @param array $child_fld_nms
   *   Available child field names.
   *
   * @return array
   *   Context array with filter-specific metadata.
   */
  protected function buildFilterContext(Index $index, string $sapi_fld_nm, array $child_fld_nms): array {
    $linkable = $this->getLinkableFields($index, $sapi_fld_nm, $child_fld_nms);
    $calculated = $this->getCalculatedFields($child_fld_nms);
    
    // Build filter-specific extras
    $field_extras = [];
    foreach ($child_fld_nms as $field_name) {
      $field_extras[$field_name] = [
        'supports_dropdown' => in_array($field_name, $linkable), // Can show entity labels
      ];
    }
    
    return [
      'linkable_fields' => $linkable,
      'calculated_fields' => $calculated,
      'field_extras' => $field_extras,
    ];
  }

  /**
   * Builds form elements for a single filter field.
   * 
   * Custom callback used by buildConfigurationForm().
   *
   * @param array &$form
   *   The form array.
   * @param array $config
   *   Field configuration array.
   * @param array $options
   *   Form structure options.
   */
  public function buildFilterFieldForm(array &$form, array $config, array $options): void {
    $field_name = $config['field_name'];
    $wrapper_key = $options['wrapper_key'];
    $field_settings_key = $options['field_settings_key'];
    $context_prefix = $options['context_prefix'];
    $is_enabled = $config['enabled'];

    // Build disabled state
    $disabled_state = $this->buildFieldDisabledState(
      $wrapper_key,
      $field_settings_key,
      $field_name,
      $context_prefix
    );

    // Field container
    $form[$wrapper_key][$field_settings_key][$field_name] = [
      '#type' => 'details',
      '#title' => $config['label'],
      '#open' => $is_enabled,
    ];

    // Enable checkbox
    $form[$wrapper_key][$field_settings_key][$field_name]['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this filter'),
      '#default_value' => $config['enabled'],
    ];

    // Widget type
    $this->addWidgetSelector($form, $config, $wrapper_key, $field_settings_key, $disabled_state, $context_prefix);

    // Label
    $form[$wrapper_key][$field_settings_key][$field_name]['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $config['label'],
      '#description' => $this->t('Label shown to users.'),
      '#size' => 30,
      '#states' => $disabled_state,
    ];

    // Weight
    $form[$wrapper_key][$field_settings_key][$field_name]['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $config['weight'],
      '#description' => $this->t('Fields with lower weights appear first.'),
      '#size' => 5,
      '#states' => $disabled_state,
    ];

    // Required
    $form[$wrapper_key][$field_settings_key][$field_name]['required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required'),
      '#default_value' => $config['required'] ?? FALSE,
      '#description' => $this->t('Make this field required when exposed.'),
      '#states' => $disabled_state,
    ];

    // Placeholder
    $form[$wrapper_key][$field_settings_key][$field_name]['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $config['placeholder'] ?? '',
      '#description' => $this->t('Placeholder text for the filter field.'),
      '#states' => $disabled_state,
    ];

    // Operator
    $form[$wrapper_key][$field_settings_key][$field_name]['field_operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Operator'),
      '#options' => $this->operatorHelper->getOperatorOptions(),
      '#default_value' => $config['field_operator'] ?? $this->operatorHelper->getDefaultOperator(),
      '#description' => $this->t('Comparison operator for this field.'),
      '#states' => $disabled_state,
    ];

    // Expose operator
    $form[$wrapper_key][$field_settings_key][$field_name]['expose_field_operator'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Let user choose operator'),
      '#default_value' => $config['expose_field_operator'] ?? FALSE,
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

    // Default value
    $form[$wrapper_key][$field_settings_key][$field_name]['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => $config['value'] ?? '',
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

  /**
   * Adds widget type selector with display mode for dropdowns.
   *
   * @param array &$form
   *   The form array.
   * @param array $config
   *   Field configuration.
   * @param string $wrapper_key
   *   Wrapper element key.
   * @param string $field_settings_key
   *   Field settings container key.
   * @param array $disabled_state
   *   Disabled state configuration.
   * @param string|null $context_prefix
   *   Form state path prefix.
   */
  protected function addWidgetSelector(
    array &$form,
    array $config,
    string $wrapper_key,
    string $field_settings_key,
    array $disabled_state,
    ?string $context_prefix
  ): void {
    $field_name = $config['field_name'];
    
    $form[$wrapper_key][$field_settings_key][$field_name]['widget'] = [
      '#type' => 'select',
      '#title' => $this->t('Widget type'),
      '#options' => [
        'textfield' => $this->t('Text field'),
        'select' => $this->t('Dropdown (from indexed values)'),
      ],
      '#default_value' => $config['widget'] ?? 'textfield',
      '#states' => $disabled_state,
      '#description' => $this->t('Dropdown automatically loads all unique values from the search index.'),
    ];

    // Display mode for dropdown options (only for linkable fields)
    if ($config['supports_dropdown'] ?? FALSE) {
      $path_parts = array_filter([
        $context_prefix,
        $wrapper_key,
        $field_settings_key,
        $field_name,
        'widget'
      ]);
      $input_name = implode('][', $path_parts);

      $form[$wrapper_key][$field_settings_key][$field_name]['select_display_mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('Display mode for dropdown options'),
        '#options' => [
          'raw' => $this->t('Raw value (ID)'),
          'label' => $this->t('Label (entity name)'),
        ],
        '#default_value' => $config['select_display_mode'] ?? 'raw',
        '#description' => $this->t('How to display options in the dropdown. Only applies to entity reference fields.'),
        '#states' => array_merge(
          $disabled_state,
          [
            'visible' => [
              ':input[name="' . $input_name . '"]' => ['value' => 'select'],
            ],
          ]
        ),
      ];
    }
  }

  /**
   * Gets linkable fields for filter context.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent field name.
   * @param array $child_fld_nms
   *   Available field names.
   *
   * @return array
   *   Array of linkable field names.
   */
  protected function getLinkableFields(Index $index, string $sapi_fld_nm, array $child_fld_nms): array {
    $linkable = [];
    
    foreach ($child_fld_nms as $field_name) {
      if ($this->nestedFieldHelper->childFieldCanLink($index, $sapi_fld_nm, $field_name)) {
        $linkable[] = $field_name;
      }
    }
    
    return $linkable;
  }
}