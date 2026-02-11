<?php

namespace Drupal\relationship_nodes_search\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedParentFieldConditionGroup;
use Drupal\relationship_nodes_search\QueryHelper\NestedQueryStructureBuilder;
use Drupal\relationship_nodes_search\Views\Widget\NestedExposedFormBuilder;
use Drupal\relationship_nodes_search\Views\Config\NestedFieldViewsFilterConfigurator;
use Drupal\relationship_nodes_search\QueryHelper\FilterOperatorHelper;

/**
 * Filter for nested relationship data in Search API.
 *
 * @ViewsFilter("search_api_relationship_filter")
 */
class RelationshipFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

  use SearchApiFilterTrait;

  protected NestedExposedFormBuilder $exposedFormBuilder;
  protected NestedFieldViewsFilterConfigurator $filterConfigurator;
  protected NestedQueryStructureBuilder $queryBuilder;
  protected FilterOperatorHelper $operatorHelper;


  /**
   * Constructs a RelationshipFilter object.
   *
   * @param array $configuration
   *    The plugin configuration.
   * @param string $plugin_id
   *    The plugin ID.
   * @param mixed $plugin_definition
   *    The plugin definition.
   * @param NestedExposedFormBuilder $exposedFormBuilder
   *    The exposed form builder service.
   * @param NestedFieldViewsFilterConfigurator $filterConfigurator
   *    The filter configurator service.
   * @param NestedQueryStructureBuilder $queryBuilder
   *    The query builder service.
   * @param FilterOperatorHelper $operatorHelper
   *    The operator helper service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    NestedExposedFormBuilder $exposedFormBuilder,
    NestedFieldViewsFilterConfigurator $filterConfigurator,
    NestedQueryStructureBuilder $queryBuilder,
    FilterOperatorHelper $operatorHelper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->exposedFormBuilder = $exposedFormBuilder;
    $this->filterConfigurator = $filterConfigurator;
    $this->queryBuilder = $queryBuilder;
    $this->operatorHelper = $operatorHelper;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('relationship_nodes_search.nested_exposed_form_builder'),
      $container->get('relationship_nodes_search.nested_field_views_filter_configurator'),
      $container->get('relationship_nodes_search.nested_query_structure_builder'),
      $container->get('relationship_nodes_search.filter_operator_helper')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();   
    foreach ($this->getDefaultFilterOptions() as $option => $default) {
      $options[$option] = ['default' => $default];
    } 
    return $options;
  }


  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    // Hide default form fields, we add custom ones in our subfield section
    if (isset($form['value'])) {
      $form['value']['#access'] = FALSE;
    }
    
    if (isset($form['expose']['multiple'])) {
      $form['expose']['multiple']['#access'] = FALSE;
    }

    $config = $this->filterConfigurator->validateAndPreparePluginForm(
      $this->getIndex(),
      $this->definition,
      $form
    );
    if (!$config) {
      return;
    } 

    $form['operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operator'),
      '#options' => [
          'and' => $this->t('AND - All conditions must match'),
          'or' => $this->t('OR - Any condition can match'),
      ],
      '#default_value' => $this->options['operator'] ?? 'and',
      '#description' => $this->t('How to combine multiple filter fields.'),
    ];

    $form['expose_operators'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to select operators'),
      '#default_value' => $this->options['expose_operators'] ?? FALSE,
      '#description' => $this->t('When exposed, allow users to choose the comparison operator for each field.'),
    ];

    $this->filterConfigurator->buildFilterConfigForm(
      $form, 
      $config['index'], 
      $config['field_name'], 
      $config['available_fields'], 
      $this->options
    );
  }

  
  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);    
    $this->filterConfigurator->savePluginOptions(
      $form_state,
      $this->getDefaultFilterOptions(),
      $this->options
    );
  }


    /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if (!$this->isExposed()) {
      return parent::adminSummary();
    }

    $child_fld_settings =  $this->getFieldSettings();
    $enabled = $this->exposedFormBuilder->getEnabledFields($child_fld_settings);
    if (empty($enabled)) {
      return $this->t('Not configured');
    }

    $operator = $this->options['operator'] ?? 'and';
    
    return $this->t('@count fields (@operator)', [
      '@count' => count($enabled),
      '@operator' => strtoupper($operator),
    ]);
  }


  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state):void {
    $index = $this->getIndex();
    $sapi_fld_nm = $this->filterConfigurator->getPluginParentFieldName($this->definition);
    if (!$index instanceof Index || empty($sapi_fld_nm)) {
      return;
    }

    $child_fld_settings = $this->options['exposed'] ? $this->getFieldSettings() : [];
    $child_fld_values = is_array($this->value) ? $this->value : [];

    $exp_op = $this->options['expose_operators'] ?? FALSE;

    $form['value'] = [
      '#type' => 'details',
      '#title' => $this->options['expose']['label'] ?? $this->t('Filter Options'),
      '#open' => $this->hasActiveFilterValues($child_fld_values, $child_fld_settings),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['relationship-child-field-wrapper']],
    ];

    $this->exposedFormBuilder->buildExposedFieldWidget(
      $form, ['value'], $index, $sapi_fld_nm, $child_fld_settings, $child_fld_values, $exp_op, $this->query
    );
  }


  /**
   * {@inheritdoc}
   */
  public function query():void {
    if (!$this->getQuery()) {
      return;
    }

    $conditions = $this->buildFilterConditions();
    if (!empty($conditions)) {
      $this->applyNestedConditions($conditions);;
    } 
  }


  /**
   * Builds filter conditions from form values.
   *
   * Extracts enabled field values and their operators from the form state,
   * sanitizes input, and returns structured condition arrays.
   *
   * @return array
   *   Array of condition arrays, each containing:
   *   - child_field_name: the nested field name
   *   - value: the filter value
   *   - operator: the comparison operator
   */
  protected function buildFilterConditions(): array {
    $child_fld_settings = $this->getFieldSettings();
    $conditions = [];
    
    foreach ($child_fld_settings as $child_fld_nm => $field_config) {
      if (empty($field_config['enabled'])) {
        continue;
      }

      // Get value
      if ($this->options['exposed']) {
        $value = $this->value[$child_fld_nm]['value'] ?? $this->value[$child_fld_nm] ?? '';
      } else {
        $value = $field_config['value'] ?? '';
      }
      
      if (is_string($value)) {
        $value = $this->sanitizeFieldValue($value);
      }
      
      if ($value === '' || $value === NULL) {
        continue;
      }

      $operator = $this->operatorHelper->determineFieldOperator($field_config, $child_fld_nm, $this->value);

      $conditions[] = [
        'child_field_name' => $child_fld_nm,
        'value' => $value,
        'operator' => $operator,
      ];
    }
    
    return $conditions;
  }


  /**
   * Applies nested conditions to the search query.
   *
   * Creates a NestedParentFieldConditionGroup and adds all child field
   * conditions to it, then adds the group to the query.
   *
   * @param array $conditions
   *   Array of condition arrays from buildFilterConditions().
   */
  protected function applyNestedConditions(array $conditions): void {
    $operator = $this->options['operator'] ?? 'and';
    $sapi_fld_nm = $this->filterConfigurator->getPluginParentFieldName($this->definition);
    $index = $this->getIndex();

    if (empty($sapi_fld_nm) || !$index instanceof Index) {
      return;
    }

    $nested_fld_condition = new NestedParentFieldConditionGroup(strtoupper($operator));
    $nested_fld_condition
      ->setParentFieldName($sapi_fld_nm)
      ->setIndex($index)
      ->setQueryBuilder($this->queryBuilder);
    
    foreach ($conditions as $condition) {
      $nested_fld_condition->addChildFieldCondition(
        $condition['child_field_name'],
        $condition['value'],
        $condition['operator']
      );
    }
    $this->query->addConditionGroup($nested_fld_condition);
  }


  /**
   * Gets the field settings from options.
   *
   * @return array
   *   The filter field settings array.
   */
  protected function getFieldSettings(): array {
    return $this->options['field_settings'] ?? [];
  }


  /**
   * Sanitizes a single field value.
   *
   * Removes HTML tags, trims whitespace, and limits length to 255 characters.
   *
   * @param string $value
   *   The value to sanitize.
   *
   * @return string
   *   The sanitized value.
   */
  protected function sanitizeFieldValue(string $value): string {
    // Remove HTML tags
    $value = strip_tags($value);
    
    // Trim whitespace
    $value = trim($value);
    
    // Limit length
    if (mb_strlen($value) > 255) {
      $value = mb_substr($value, 0, 255);
    }
    
    return $value;
  }


  /**
   * Gets default filter options.
   *
   * @return array
   *   Array of default option values.
   */
  protected function getDefaultFilterOptions(): array {
    return [
      'value' => [],
      'field_settings' => [],
      'operator' => 'and',
      'expose_operators' => FALSE,
    ];
  }


  /**
   * Check if any filter values are set.
   *
   * @param array $values
   *   The filter values.
   * @param array $field_settings
   *   The field settings.
   *
   * @return bool
   *   TRUE if any enabled field has a non-empty value.
   */
  protected function hasActiveFilterValues(array $values, array $field_settings): bool {
    if (empty($values)) {
      return FALSE;
    }

    foreach ($field_settings as $field_name => $config) {
      // Skip disabled fields
      if (empty($config['enabled'])) {
        continue;
      }

      // Check if field has a value
      $value = $values[$field_name]['value'] ?? $values[$field_name] ?? NULL;
      
      if ($value !== NULL && $value !== '') {
        return TRUE;
      }
    }

    return FALSE;
  }
}