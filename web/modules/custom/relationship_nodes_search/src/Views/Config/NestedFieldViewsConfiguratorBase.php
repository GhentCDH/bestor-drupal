<?php

namespace Drupal\relationship_nodes_search\Views\Config;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes\RelationEntity\UserInterface\NestedFieldConfiguratorBase;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes_search\FieldHelper\NestedFieldHelper;
use Drupal\relationship_nodes_search\FieldHelper\CalculatedFieldHelper;

/**
 * Base configurator for Views plugins handling nested fields.
 * 
 * Extends the generic configurator with Views/Search API-specific logic:
 * - Validates Search API index structure
 * - Parses Views plugin definitions
 * - Determines field capabilities from index metadata
 * - Prepares field configurations with Search API context
 * 
 * Used by both field and filter Views handlers.
 */
abstract class NestedFieldViewsConfiguratorBase extends NestedFieldConfiguratorBase {

  protected NestedFieldHelper $nestedFieldHelper;
  protected CalculatedFieldHelper $calculatedFieldHelper;

  /**
   * Constructs a NestedFieldViewsConfiguratorBase object.
   *
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver service.
   * @param NestedFieldHelper $nestedFieldHelper
   *   The nested field helper service.
   * @param CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper service.
   */
  public function __construct(
    FieldNameResolver $fieldNameResolver,
    NestedFieldHelper $nestedFieldHelper,
    CalculatedFieldHelper $calculatedFieldHelper
  ) {
    parent::__construct($fieldNameResolver);
    $this->nestedFieldHelper = $nestedFieldHelper;
    $this->calculatedFieldHelper = $calculatedFieldHelper;
  }

  /**
   * Validates and prepares configuration for Views plugin options form.
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
      // Add form error using parent method
      $this->addErrorMessage($form, $this->t('Cannot load index or field configuration, or no nested fields available.'));
      return NULL;
    }
    
    return $config;
  }

  /**
   * Prepares field configurations for Views context.
   * 
   * Wraps parent prepareFieldConfigurations() with Views/Search API-specific
   * context building (linkable fields, calculated fields).
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent Search API field name.
   * @param array $field_names
   *   Array of available field names.
   * @param array $current_settings
   *   Current field settings from saved configuration.
   *
   * @return array
   *   Field configurations with Views context applied.
   */
  public function prepareViewsFieldConfigurations(
    Index $index,
    string $sapi_fld_nm,
    array $field_names,
    array $current_settings
  ): array {
    // Build Views-specific context
    $context = $this->buildViewsContext($index, $sapi_fld_nm, $field_names);
    
    // Use parent's prepare method with context
    return $this->prepareFieldConfigurations($field_names, $current_settings, $context);
  }

  /**
   * Builds Views/Search API-specific context for field preparation.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent Search API field name.
   * @param array $field_names
   *   Available field names.
   *
   * @return array
   *   Context array with:
   *   - 'linkable_fields': Array of fields that support linking
   *   - 'calculated_fields': Array of calculated field names
   */
  protected function buildViewsContext(Index $index, string $sapi_fld_nm, array $field_names): array {
    return [
      'linkable_fields' => $this->getLinkableFields($index, $sapi_fld_nm, $field_names),
      'calculated_fields' => $this->getCalculatedFields($field_names),
    ];
  }

  /**
   * Gets fields that support entity reference linking.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent field name.
   * @param array $field_names
   *   Available field names.
   *
   * @return array
   *   Array of linkable field names.
   */
  protected function getLinkableFields(Index $index, string $sapi_fld_nm, array $field_names): array {
    // This should be implemented by concrete classes or injected as a service
    // For now, return empty to avoid undefined method errors
    return [];
  }

  /**
   * Gets calculated field names from available fields.
   *
   * @param array $field_names
   *   Available field names.
   *
   * @return array
   *   Array of calculated field names.
   */
  protected function getCalculatedFields(array $field_names): array {
    return array_filter($field_names, function($field_name) {
      return $this->calculatedFieldHelper->isCalculatedChildField($field_name);
    });
  }

  /**
   * Saves plugin options from form state.
   * 
   * Views-specific wrapper around parent's extractSettingsFromFormState().
   *
   * @param FormStateInterface $form_state
   *   The form state.
   * @param array $default_settings
   *   Default settings structure.
   * @param array &$options
   *   Reference to the options array to update.
   * @param string|null $wrapper_key
   *   Optional wrapper key for nested settings.
   */
  public function savePluginOptions(
    FormStateInterface $form_state,
    array $default_settings,
    array &$options,
    ?string $wrapper_key = NULL
  ): void {
    // Extract from form state
    $path_prefix = $wrapper_key ? [$wrapper_key] : [];
    
    foreach ($default_settings as $key => $default_value) {
      $path = array_merge(['options'], $path_prefix, [$key]);
      $value = $form_state->getValue($path);
      
      if (isset($value)) {
        $options[$key] = $value;
      }
    }
  }

  /**
   * Sorts field configurations by weight.
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

  /**
   * Gets form state path prefix for Views context.
   *
   * @return string
   *   The context prefix for Views forms ('options').
   */
  protected function getViewsContextPrefix(): string {
    return 'options';
  }
}