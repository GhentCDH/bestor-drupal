<?php

namespace Drupal\relationship_nodes_search\Views\Config;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes\Display\Configurator\FieldConfiguratorBase;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationField\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\FieldHelper\NestedIndexFieldHelper;

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
abstract class NestedFieldViewsConfiguratorBase extends FieldConfiguratorBase {

  protected CalculatedFieldHelper $calculatedFieldHelper;

  /**
   * Constructs a NestedFieldViewsConfiguratorBase object.
   *
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver service.
   * @param NestedIndexFieldHelper $nestedFieldHelper
   *   The nested field helper service.
   * @param CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper service.
   */
  public function __construct(
    FieldNameResolver $fieldNameResolver,
    NestedIndexFieldHelper $nestedFieldHelper,
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
    $field_name = $this->getPluginParentFieldName($definition);
    
    // Validate field structure (delegated to field helper)
    $config = $this->validatePluginFieldConfiguration($index, $field_name);
    
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
   * Extracts parent field name from Views plugin definition.
   * 
   * Helper method for Views plugins to get their configured field name.
   *
   * @param array $definition
   *   The plugin definition array.
   *
   * @return string|null
   *   The field name, or NULL if not found.
   */
  public function getPluginParentFieldName(array $definition): ?string {
    // Field handlers use 'search_api field' (with space)
    if (isset($definition['search_api field'])) {
      return $definition['search_api field'];
    }
    
    // Filter handlers use 'real field'.
    if (isset($definition['real field'])) {
      return $definition['real field'];
    }
    
    return NULL;
  }


  /**
   * Validate index and field configuration for a Views plugin.
   * 
   * Common validation helper for plugin configuration forms.
   *
   * @param Index|null $index
   *   The Search API index.
   * @param string|null $field_name
   *   The parent field name.
   *
   * @return array{index: Index, field_name: string, available_fields: array}|null
   *   Validation result with index, field name, and available child fields.
   *   Returns NULL if validation fails.
   */
  protected function validatePluginFieldConfiguration (?Index $index, ?string $field_name): ?array {
    if (!$index instanceof Index || empty($field_name)) {
      return NULL;
    }
    
    $available_fields = $this->getAvailableFieldNames($index, $field_name);
    
    if (empty($available_fields)) {
      return NULL;
    }
    
    return [
      'index' => $index,
      'field_name' => $field_name,
      'available_fields' => $available_fields,
    ];
  }


  /**
   * Gets processed nested child field names with unnecessary fields removed.
   *
   * Filters out internal relationship fields that shouldn't be exposed to users,
   * returning only the relevant child fields for a parent field.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent field name.
   *
   * @return array
   *   Array of processed child field names.
   */
  protected function getAvailableFieldNames(Index $index, string $sapi_fld_nm): array {
    $child_fld_nms = $this->nestedFieldHelper->getAllNestedChildFieldNames($index, $sapi_fld_nm);
    if (empty($child_fld_nms)) {
      return [];
    }

    // Validate relationship structure - all required entity fields must exist
    $related_entity_flds = $this->fieldNameResolver->getRelatedEntityFields();
    foreach ($related_entity_flds as $related_entity_fld) {
      if (!in_array($related_entity_fld, $child_fld_nms)) {
        // Misconfigured relationship object
        return [];
      }
    }

    // Build removal list
    $remove = $related_entity_flds;

    // Handle relation type field
    $relation_type_fld = $this->fieldNameResolver->getRelationTypeField();
    if (in_array($relation_type_fld, $child_fld_nms)) {
      $remove[] = $relation_type_fld;
    } else {
      // Add calculated relation type fields to removal list
      $remove = array_merge($remove, $this->calculatedFieldHelper->getCalculatedFieldNames('relation_type', NULL, TRUE));
    }

    // Filter and return (array_values to reindex)
    return array_values(array_diff($child_fld_nms, $remove));
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