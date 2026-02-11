<?php

namespace Drupal\relationship_nodes_search\Views\Widget;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Render\Element;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\relationship_nodes_search\QueryHelper\FilterOperatorHelper;
use Drupal\relationship_nodes\RelationField\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\Views\Widget\NestedFilterDropdownOptionsProvider;
use Drupal\relationship_nodes_search\FieldHelper\NestedIndexFieldHelper;
use Drupal\relationship_nodes_search\QueryHelper\NestedFacetResultParser;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for building exposed filter form widgets.
 */
class NestedExposedFormBuilder {

  use StringTranslationTrait;

  protected FilterOperatorHelper $operatorHelper;
  protected CalculatedFieldHelper $calculatedFieldHelper;
  protected NestedFilterDropdownOptionsProvider $dropdownProvider;
  protected NestedIndexFieldHelper $nestedFieldHelper;
  protected NestedFacetResultParser $facetResultParser;
  protected LoggerChannelFactoryInterface $loggerFactory;

  
  /**
   * Constructs a NestedExposedFormBuilder object.
   */
  public function __construct(
    FilterOperatorHelper $operatorHelper,
    CalculatedFieldHelper $calculatedFieldHelper,
    NestedFilterDropdownOptionsProvider $dropdownProvider,
    NestedIndexFieldHelper $nestedFieldHelper,
    NestedFacetResultParser $facetResultParser,
    LoggerChannelFactoryInterface $loggerFactory
  ) {
    $this->operatorHelper = $operatorHelper;
    $this->calculatedFieldHelper = $calculatedFieldHelper;
    $this->dropdownProvider = $dropdownProvider;
    $this->nestedFieldHelper = $nestedFieldHelper;
    $this->facetResultParser = $facetResultParser;
    $this->loggerFactory = $loggerFactory;
  }


  /**
   * Build exposed field widget structure.
   */
  public function buildExposedFieldWidget(
    array &$form,
    array $path,
    Index $index,
    string $sapi_fld_nm,
    array $child_fld_settings,
    array $child_fld_values = [],
    bool $expose_operators = FALSE,
    $view_query = NULL
  ): void {
    if (empty($child_fld_settings)) {
      return;
    }

    $enabled_fields = $this->getEnabledAndSortedFields($child_fld_settings);

    foreach ($enabled_fields as $child_fld_nm => $child_fld_config) {
      $child_fld_value = $child_fld_values[$child_fld_nm] ?? NULL;
      $child_path = array_merge($path, [$child_fld_nm]);
      if (($child_fld_config['widget'] ?? 'textfield') === 'select') {
        $options = $this->fetchFacetOptionsWithoutExposedFilters(
          $index,
          $sapi_fld_nm,
          $child_fld_nm,
          $view_query,
          $child_fld_config['select_display_mode'] ?? 'raw'
        );
        $child_fld_config['options'] = $options;
      }

      $this->buildChildFieldElement(
        $form,
        $child_path,
        $child_fld_nm,
        $child_fld_config,
        $child_fld_value,
        $expose_operators
      );
    }
  }


  /**
   * Get enabled fields from configuration.
   *
   * @param array $child_fld_settings
   *   Child field settings array.
   *
   * @return array
   *   Enabled fields only.
   */
  public function getEnabledFields(array $child_fld_settings): array {
    return array_filter($child_fld_settings, function($config) {
      return !empty($config['enabled']);
    });
  }


  /**
   * Get enabled fields sorted by weight.
   *
   * @param array $child_fld_settings
   *   Child field settings array.
   *
   * @return array
   *   Enabled and sorted fields.
   */
  public function getEnabledAndSortedFields(array $child_fld_settings): array {
    $enabled = $this->getEnabledFields($child_fld_settings);

    uasort($enabled, function($a, $b) {
      return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
    });

    return $enabled;
  }


  /**
   * Build a single child field form element.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param array $path
   *   Form element path.
   * @param string $child_fld_nm
   *   Child field name.
   * @param array $field_config
   *   Field configuration (must include 'options' for select widgets).
   * @param array|null $field_value
   *   Current field value.
   * @param bool $expose_operators
   *   Whether to expose operator selector.
   */
  protected function buildChildFieldElement(
    array &$form,
    array $path,
    string $child_fld_nm,
    array $field_config,
    ?array $field_value = NULL,
    bool $expose_operators = FALSE
  ): void {
    $widget_type = $field_config['widget'] ?? 'textfield';
    $label = $field_config['label'] ?? $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm);
    $required = !empty($field_config['required']);
    $placeholder = $field_config['placeholder'] ?? '';
    $expose_field_operator = !empty($field_config['expose_field_operator']);

    $child_fld_container = [
    '#type' => 'container',
    '#attributes' => ['class' => ['relationship-filter-field-wrapper']],
    ];
    $this->setFormNestedValue($form, $path, $child_fld_container);

    if ($expose_operators && $expose_field_operator) {
    $this->addOperatorWidget($form, $path, $field_config, $field_value);
    }

    switch ($widget_type) {
      case 'select':
        $this->addSelectWidget($form, $path, $field_config, $label, $required, $field_value);
        break;
      case 'textfield':
      default:
        $this->addTextfieldWidget($form, $path, $label, $required, $placeholder, $field_value);
        break;
    }
  }


  /**
   * Add operator selector widget.
   *
   * Creates a select dropdown for choosing the comparison operator
   * (equals, greater than, etc.).
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param array $path
   *   Form element path.
   * @param array $field_config
   *   Field configuration.
   * @param array|null $field_value
   *   Current field value.
   */
  protected function addOperatorWidget(array &$form, array $path, array $field_config, ?array $field_value = NULL): void {
    $path[] = 'operator';
    $operator = [
      '#type' => 'select',
      '#title' => $this->t('Operator'),
      '#options' => $this->operatorHelper->getOperatorOptions(),
      '#default_value' => $field_value['operator'] ?? $field_config['field_operator'] ?? $this->operatorHelper->getDefaultOperator(),
      '#attributes' => ['class' => ['relationship-filter-operator']],
    ];
    $this->setFormNestedValue($form, $path, $operator);
  }


  /**
   * Add dropdown select widget.
   *
   * Creates a select dropdown with options provided in field configuration.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param array $path
   *   Form element path.
   * @param array $field_config
   *   Field configuration (must include 'options' key).
   * @param string $label
   *   Field label.
   * @param bool $required
   *   Whether the field is required.
   * @param array|null $field_value
   *   Current field value.
   */
  protected function addSelectWidget(
    array &$form,
    array $path,
    array $field_config,
    string $label,
    bool $required,
    ?array $field_value = NULL
  ): void {
    $options = $field_config['options'] ?? [];

    $path[] = 'value';
    $value = [
      '#type' => 'select',
      '#title' => $label,
      '#options' => $options,
      '#default_value' => $field_value['value'] ?? '',
      '#required' => $required,
      '#empty_option' => $required ? NULL : $this->t('- Any -'),
    ];
    $this->setFormNestedValue($form, $path, $value);
}


  /**
   * Add textfield widget.
   *
   * Creates a simple text input field.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param array $path
   *   Form element path.
   * @param string $label
   *   Field label.
   * @param bool $required
   *   Whether the field is required.
   * @param string $placeholder
   *   Placeholder text.
   * @param array|null $field_value
   *   Current field value.
   */
  protected function addTextfieldWidget(
    array &$form,
    array $path,
    string $label,
    bool $required,
    string $placeholder,
    ?array $field_value = NULL
  ): void {
    $path[] = 'value';
    $value = [
      '#type' => 'textfield',
      '#title' => $label,
      '#default_value' => $field_value['value'] ?? '',
      '#required' => $required,
      '#placeholder' => $placeholder,
    ];
    $this->setFormNestedValue($form, $path, $value);
  }


  /* // ENTITY AUTOCOMPLETE NOT YET IMPLEMENTED (CF CONFIG HELPER)
  protected function addEntityAutocompleteWidget(array &$form, array $path, string $child_fld_nm, string $label, bool $required, string $placeholder, ?array $field_value = NULL): void {
    $target_type =  // implement childfieldentrefhelper getnestedfieldtargettype;
    $default_entity = $this->getDefaultEntityValue($child_fld_nm, $target_type, $field_value);
    $path[] = 'value';
    $value = [
      '#type' => 'entity_autocomplete',
      '#title' => $label,
      '#target_type' => $target_type,
      '#default_value' => $default_entity,
      '#required' => $required,
      '#placeholder' => $placeholder,
    ];
    $this->setFormNestedValue($form, $path, $value);
  }

  protected function getDefaultEntityValue(string $child_fld_nm, string $target_type, ?array $field_value = NULL) {   
    if (empty($field_value) || !is_numeric($field_value)) {
      return NULL;
    }

    try {
      return $this->entityTypeManager->getStorage($target_type)->load($field_value);
    } catch (\Exception $e) {
      return NULL;
    }
  }*/



  /**
   * Set a nested value in form array.
   *
   * Navigates through the form array using the path keys and sets the value
   * at the final location, creating intermediate arrays as needed.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param array $path
   *   Array of keys representing the path to the value.
   * @param mixed $value
   *   The value to set.
   */
  protected function setFormNestedValue(array &$form, array $path, $value): void {
    $ref = &$form;
    $path_count = count($path);
    
    foreach ($path as $i => $key) {
      $is_last = ($i === $path_count - 1);
      
      if ($is_last) {
        if (!isset($ref[$key])) {
          $ref[$key] = $value;
        } elseif (is_array($ref[$key]) && is_array($value)) {
          $ref[$key] = array_merge($ref[$key], $value);
        } else {
          $ref[$key] = $value;
        }
      } else {
        if (!isset($ref[$key])) {
          $ref[$key] = [];
        } elseif (!is_array($ref[$key])) {
          $ref[$key] = [];
        }
        $ref = &$ref[$key];
      }
    }
  }


  /**
   * Fetch facet options excluding exposed filter conditions.
   *
   * @param Index $index
   *   The search index.
   * @param string $parent_field
   *   Parent field name.
   * @param string $child_field
   *   Child field name.
   * @param SearchApiQuery|null $view_query
   *   The view query object.
   * @param string $display_mode
   *   Display mode: 'raw' or 'label'.
   *
   * @return array
   *   Array of options for the dropdown.
   */
protected function fetchFacetOptionsWithoutExposedFilters(
  Index $index,
  string $parent_field,
  string $child_field,
  $view_query = NULL,
  string $display_mode = 'raw'
): array {
  try {
    // Create fresh query
    $query = $index->query();
    // Apply only non-exposed filters from the view
    $non_exposed_filters = [];
    if ($view_query && method_exists($view_query, 'getSearchApiQuery')) {
      $sapi_query = $view_query->getSearchApiQuery();
      $filters = $sapi_query->getOptions()['search_api_view']->filter;
      if (!empty($filters)) {
        foreach($filters as $filter_id => $filter) {
          if ($filter->options['exposed'] === false) {
            $non_exposed_filters[] = $filter->realField;

          }
        }
      }
      dpm($sapi_query->getConditionGroup());
      /*
      $view = $view_query->getView();
      dpm($view);
      dpm($view_query);
      $this->applyNonExposedViewConfiguration($query, $view);
      */
    }

    // Query only needs facets, no results
    $query->range(0, 0);

    // Add facet configuration
    $field_key = $parent_field . ':' . $child_field;
    $full_field_path = $this->nestedFieldHelper->colonsToDots($field_key);
    
    $facets = [
      $field_key => [
        'field' => $full_field_path,
        'limit' => 0,
        'operator' => 'or',
        'min_count' => 1,
        'missing' => FALSE,
      ],
    ];

    
    $query->setOption('search_api_facets', $facets);

    // DEBUG: Inspect query before execution
    $conditions = $query->getConditionGroup();

    // Execute query
    $results = $query->execute();

    // DEBUG: Inspect results

    
    $facet_data = $results->getExtraData('search_api_facets');

// Dan pas de parser call
$raw_values = $this->facetResultParser->extractTrimmedFacetValues($results, $field_key);

    
    // Format according to display mode
    $formatted = $this->formatFacetOptions($raw_values, $display_mode, $index, $full_field_path);

    return $formatted;

  } catch (\Exception $e) {
    $this->loggerFactory->get('relationship_nodes_search')->error(
      'Failed to fetch facet options: @message',
      ['@message' => $e->getMessage()]
    );
    return [];
  }
}

  /**
   * Apply non-exposed view configuration to query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to configure.
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   */
  protected function removeExposedViewConfig($query, $view): void {
    // Apply non-exposed filters manually by inspecting filter configuration
    foreach ($view->filter as $filter_id => $filter) {
      if ($filter->isExposed()) {
        continue;
      }

      // Get the filter's field and value
      $field = $filter->realField ?? $filter_id;
      $value = $filter->value ?? NULL;
      $operator = $filter->operator ?? '=';

      // Skip if no value set
      if ($value === NULL || $value === '') {
        continue;
      }

      // Add condition to query
      try {
        $query->addCondition($field, $value, $operator);
      } catch (\Exception $e) {
        // Log but continue
        $this->loggerFactory->get('relationship_nodes_search')->warning(
          'Failed to apply non-exposed filter @filter: @message',
          ['@filter' => $filter_id, '@message' => $e->getMessage()]
        );
      }
    }

    // Apply contextual filters (arguments)
    if (!empty($view->argument)) {
      foreach ($view->argument as $argument_id => $argument) {
        $field = $argument->realField ?? $argument_id;
        $value = $argument->getValue() ?? NULL;
        
        if ($value !== NULL && $value !== '') {
          try {
            $query->addCondition($field, $value);
          } catch (\Exception $e) {
            $this->loggerFactory->get('relationship_nodes_search')->warning(
              'Failed to apply contextual filter @argument: @message',
              ['@argument' => $argument_id, '@message' => $e->getMessage()]
            );
          }
        }
      }
    }
  }


  /**
   * Format facet values to options array.
   *
   * @param array $raw_values
   *   Raw facet values from the search results.
   * @param string $display_mode
   *   Display mode: 'raw' or 'label'.
   * @param Index $index
   *   The search index.
   * @param string $field_path
   *   Full field path in the index.
   *
   * @return array
   *   Formatted options array for select widget.
   */
  protected function formatFacetOptions(array $raw_values, string $display_mode, Index $index, string $field_path): array {
    if (empty($raw_values)) {
      return [];
    }

    $options = [];
    
    foreach ($raw_values as $value) {
      if ($display_mode === 'label' && is_numeric($value)) {
        // Load entity label using dropdown provider
        $label = $this->loadEntityLabel($value, $index, $field_path);
        $options[$value] = $label ?: $value;
      } else {
        $options[$value] = $value;
      }
    }

    return $options;
  }

  /**
   * Load entity label for a value.
   *
   * @param mixed $entity_id
   *   The entity ID.
   * @param Index $index
   *   The search index.
   * @param string $field_path
   *   Full field path in the index.
   *
   * @return string|null
   *   The entity label, or NULL if not found.
   */
  protected function loadEntityLabel($entity_id, Index $index, string $field_path): ?string {
    // Use existing dropdown provider to load entity labels
    return $this->dropdownProvider->getEntityLabel($entity_id, $field_path, $index);
  }
}