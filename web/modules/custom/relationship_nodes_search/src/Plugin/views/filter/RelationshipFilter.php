<?php

namespace Drupal\relationship_nodes_search\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes_search\Service\RelationSearchService;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedParentFieldConditionGroup;
use Drupal\relationship_nodes_search\Service\NestedAggregationService;
use Drupal\Core\Cache\Cache;

/**
 * Filter for nested relationship data in Search API.
 *
 * @ViewsFilter("search_api_relationship_filter")
 */
class RelationshipFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

    use SearchApiFilterTrait;

    protected NestedAggregationService $nestedAggregationService;
    protected RelationSearchService $relationSearchService;
    protected ?array $valueOptions = NULL;


    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        NestedAggregationService $nestedAggregationService,
        RelationSearchService $relationSearchService,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
         $this->nestedAggregationService = $nestedAggregationService;
        $this->relationSearchService = $relationSearchService;
    }
    

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('relationship_nodes_search.nested_aggregation_service'),
            $container->get('relationship_nodes_search.relation_search_service'),
        );
    }


    public function defineOptions() {
        $options = parent::defineOptions();   
        foreach ($this->getDefaultFilterOptions() as $option => $default) {
            $options[$option] = ['default' => $default];
        } 
        return $options;
    }


    /*
    * Configuration views admin form
    */
    public function buildOptionsForm(&$form, FormStateInterface $form_state) {
        parent::buildOptionsForm($form, $form_state);
        if (isset($form['expose']['multiple'])) {
            $form['expose']['multiple']['#access'] = FALSE;
        }

        $index = $this->getIndex();
        $real_field = $this->getRealField();  
        if (!$index instanceof Index || empty($real_field)) {
            $form['error'] = [
                '#markup' => $this->t('Cannot load index or field configuration.'),
            ];
            return;
        }
        
        $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $real_field);
        if (empty($available_fields)) {
            $form['info'] = [
                '#markup' => $this->t('No nested fields available. Please configure nested fields in the Search API index.'),
            ];
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

        $form['filter_field_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Filter fields'),
            '#description' => $this->t('Select which fields should be available for filtering.'),
            '#tree' => TRUE,
        ];

        $this->buildFieldSettingsForms($form, $available_fields);
    }

    public function submitOptionsForm(&$form, FormStateInterface $form_state) {
        parent::submitOptionsForm($form, $form_state);
        
        foreach ($this->getDefaultFilterOptions() as $option => $default) {
            $value = $form_state->getValue(['options', $option]);
            if (isset($value)) {
                $this->options[$option] = $value;
            }
        }
    }


    /*
    * Create title/description of the filter, visible in the views admin config form
    */
    public function adminSummary() {
        if (!$this->isExposed()) {
            return parent::adminSummary();
        }

        $enabled = $this->getEnabledFields();

        if (empty($enabled)) {
            return $this->t('Not configured');
        }

        $operator = $this->options['operator'] ?? 'and';
        
        return $this->t('@count fields (@operator)', [
            '@count' => count($enabled),
            '@operator' => strtoupper($operator),
        ]);
    }

    /*
    * Exposed Form
    */
protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
        '#type' => 'container',
        '#tree' => TRUE,
    ];
    
    // If not exposed, show text fields for value inputs
    if (!$this->options['exposed']) {
        return;
    }

    // If exposed, build exposed field widgets
    $enabled_fields = $this->getEnabledAndSortedFields();    
    if (empty($enabled_fields)) {
        return;
    }
    foreach ($enabled_fields as $child_field_name => $field_config) {
        $this->buildExposedFieldWidget($form, $child_field_name, $field_config);
    }
}
    


    public function query() {
        if (!$this->getQuery()) {
            return;
        }

        $conditions = $this->buildFilterConditions();
        if (empty($conditions)) {
            return;
        }

        $this->applyNestedConditions($conditions);
    }


    /**
     * Build field settings forms for all available fields.
     */
    protected function buildFieldSettingsForms(array &$form, array $available_fields): void {
        $filter_field_settings = $this->options['filter_field_settings'] ?? [];

        foreach ($available_fields as $field_name) {
            $is_enabled = !empty($filter_field_settings[$field_name]['enabled']);
            $disabled_state = $this->getFieldDisabledState($field_name);
            
            $form['filter_field_settings'][$field_name] = [
                '#type' => 'details',
                '#title' => $field_name,
                '#open' => $is_enabled,
            ];

            $this->addFieldEnableCheckbox($form, $field_name, $filter_field_settings);
            $this->addFieldLabel($form, $field_name, $filter_field_settings, $disabled_state);
            $this->addFieldWidget($form, $field_name, $filter_field_settings, $disabled_state);
            $this->addFieldWeight($form, $field_name, $filter_field_settings, $disabled_state);
            $this->addFieldRequired($form, $field_name, $filter_field_settings, $disabled_state);
            $this->addFieldPlaceholder($form, $field_name, $filter_field_settings, $disabled_state);
            $this->addFieldOperator($form, $field_name, $filter_field_settings, $disabled_state);
            $this->addExposeFieldOperator($form, $field_name, $filter_field_settings, $disabled_state);
            $this->addFieldValueField($form, $field_name, $filter_field_settings, $disabled_state);
        }
    }

    /**
     * Add enable checkbox for a field.
     */
    protected function addFieldEnableCheckbox(array &$form, string $field_name, array $filter_field_settings): void {
        $form['filter_field_settings'][$field_name]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable this filter field'),
            '#default_value' => !empty($filter_field_settings[$field_name]['enabled']),
        ];
    }

    /**
     * Add label field.
     */
    protected function addFieldLabel(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#default_value' => $filter_field_settings[$field_name]['label'] 
                ?? $this->relationSearchService->formatCalculatedFieldLabel($field_name),
            '#description' => $this->t('Label shown to users when exposed.'),
            '#size' => 30,
            '#states' => $disabled_state,
        ];
    }

    /**
     * Add widget type selector.
     */
    protected function addFieldWidget(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['widget'] = [
            '#type' => 'select',
            '#title' => $this->t('Widget type'),
            '#options' => [
                'textfield' => $this->t('Text field'),
                'select' => $this->t('Dropdown (from indexed values)'),
                // VVVV NOG TE IMPLEMENTEREN EVENTUEEL VVVV dpm
                //'entity_autocomplete' => $this->t('Entity autocomplete'), 
            ],
            '#default_value' => $filter_field_settings[$field_name]['widget'] ?? 'textfield',
            '#states' => $disabled_state,
            '#description' => $this->t('Dropdown automatically loads all unique values from the search index.'),
        ];
        
        // Display mode for dropdown options
        $form['filter_field_settings'][$field_name]['select_display_mode'] = [
            '#type' => 'radios',
            '#title' => $this->t('Display mode for dropdown options'),
            '#options' => [
                'raw' => $this->t('Raw value (ID)'),
                'label' => $this->t('Label (entity name)'),
            ],
            '#default_value' => $filter_field_settings[$field_name]['select_display_mode'] ?? 'raw',
            '#description' => $this->t('How to display options in the dropdown. Only applies to entity reference fields.'),
            '#states' => array_merge(
                $disabled_state,
                [
                    'visible' => [
                        ':input[name="options[filter_field_settings][' . $field_name . '][widget]"]' => ['value' => 'select'],
                    ],
                ]
            ),
        ];
    }


    /**
     * Add weight field.
     */
    protected function addFieldWeight(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['weight'] = [
            '#type' => 'number',
            '#title' => $this->t('Weight'),
            '#default_value' => $filter_field_settings[$field_name]['weight'] ?? 0,
            '#description' => $this->t('Fields with lower weights appear first.'),
            '#size' => 5,
            '#states' => $disabled_state,
        ];
    }

    /**
     * Add required checkbox.
     */
    protected function addFieldRequired(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['required'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Required'),
            '#default_value' => $filter_field_settings[$field_name]['required'] ?? FALSE,
            '#description' => $this->t('Make this field required when exposed.'),
            '#states' => $disabled_state,
        ];
    }

    /**
     * Add placeholder field.
     */
    protected function addFieldPlaceholder(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['placeholder'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Placeholder'),
            '#default_value' => $filter_field_settings[$field_name]['placeholder'] ?? '',
            '#description' => $this->t('Placeholder text for the filter field.'),
            '#states' => $disabled_state,
        ];
    }

    /**
     * Add operator selector.
     */
    protected function addFieldOperator(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['field_operator'] = [
            '#type' => 'select',
            '#title' => $this->t('Operator'),
            '#options' => $this->getOperatorOptions(),
            '#default_value' => $filter_field_settings[$field_name]['field_operator'] ?? '=',
            '#description' => $this->t('Comparison operator for this field.'),
            '#states' => $disabled_state,
        ];
    }

    /**
     * Add expose operator checkbox.
     */
    protected function addExposeFieldOperator(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['expose_field_operator'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Let user choose operator'),
            '#default_value' => $filter_field_settings[$field_name]['expose_field_operator'] ?? FALSE,
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
     * Build exposed field widget in exposed filter.
     */
    protected function buildExposedFieldWidget(array &$form, string $child_field_name, array $field_config): void {
        $widget_type = $field_config['widget'] ?? 'textfield';
        $label = $field_config['label'] ?? $this->relationSearchService->formatCalculatedFieldLabel($child_field_name);
        $required = !empty($field_config['required']);
        $placeholder = $field_config['placeholder'] ?? '';
        $expose_operators = $this->options['expose_operators'] ?? FALSE;
        $expose_field_operator = !empty($field_config['expose_field_operator']);

        $form['value'][$child_field_name] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['relationship-filter-field-wrapper']],
        ];

        if ($expose_operators && $expose_field_operator) {
            $this->addOperatorWidget($form, $child_field_name, $field_config);
        }

        switch ($widget_type) {
            case 'entity_autocomplete':
                $this->addEntityAutocompleteWidget($form, $child_field_name, $label, $required, $placeholder);
                break;

            case 'select':
                $this->addSelectWidget($form, $child_field_name, $field_config, $label, $required);
                break;

            case 'textfield':
            default:
                $this->addTextfieldWidget($form, $child_field_name, $label, $required, $placeholder);
                break;
        }
    }

    /**
     * Add operator selector widget.
     */
    protected function addOperatorWidget(array &$form, string $field_name, array $field_config): void {
        $form['value'][$field_name]['operator'] = [
            '#type' => 'select',
            '#title' => $this->t('Operator'),
            '#options' => $this->getOperatorOptions(),
            '#default_value' => $this->value[$field_name]['operator'] ?? $field_config['field_operator'] ?? '=',
            '#attributes' => ['class' => ['relationship-filter-operator']],
        ];
    }

    /**
     * Add entity autocomplete widget.
     */
    protected function addEntityAutocompleteWidget(array &$form, string $field_name, string $label, bool $required, string $placeholder): void {
        $target_type = $this->getTargetTypeForField($field_name);
        $default_entity = $this->getDefaultEntityValue($field_name, $target_type);
        
        $form['value'][$field_name]['value'] = [
            '#type' => 'entity_autocomplete',
            '#title' => $label,
            '#target_type' => $target_type,
            '#default_value' => $default_entity,
            '#required' => $required,
            '#placeholder' => $placeholder,
        ];
    }


    /**
     * Add dropdown select widget.
     */
protected function addSelectWidget(array &$form, string $field_name, array $field_config, string $label, bool $required): void {
    $display_mode = $field_config['select_display_mode'] ?? 'label';
    
    $options = $this->getDropdownOptions($field_name, $display_mode);
    if (!$required && !empty($options)) {
        $options = ['' => $this->t('- Any -')] + $options;
    }
    
    $form['value'][$field_name]['value'] = [
        '#type' => 'select',
        '#title' => $label,
        '#options' => $options,
        '#default_value' => $this->value[$field_name]['value'] ?? $this->value[$field_name] ?? '',
        '#required' => $required,
        '#empty_option' => $required ? NULL : $this->t('- Any -'),
    ];
}


    /**
     * Add textfield widget.
     */
    protected function addTextfieldWidget(array &$form, string $field_name, string $label, bool $required, string $placeholder): void {
        $form['value'][$field_name]['value'] = [
            '#type' => 'textfield',
            '#title' => $label,
            '#default_value' => $this->value[$field_name]['value'] ?? $this->value[$field_name] ?? '',
            '#required' => $required,
            '#placeholder' => $placeholder,
        ];
    }

    /**
     * Build filter conditions from form values.
     */
protected function buildFilterConditions(): array {
    $filter_field_settings = $this->options['filter_field_settings'] ?? [];
    $conditions = [];
    foreach ($filter_field_settings as $child_field_name => $field_config) {
        if (empty($field_config['enabled'])) {
            continue;
        }

        if ($this->options['exposed']) {
            $value = $this->value[$child_field_name]['value'] ?? $this->value[$child_field_name] ?? '';
        } 

        else {
            $value = $field_config['value'] ?? '';
        }
        
        if ($value === '' || $value === NULL) {
            continue;
        }
        $field_operator = $this->getFieldOperator($child_field_name, $field_config);

            $conditions[] = [
                'child_field_name' => $child_field_name,
                'value' => $value,
                'operator' => $field_operator,
            ];
        }
        
        return $conditions;
}


    /**
     * Get the operator for a specific field.
     */
    protected function getFieldOperator(string $field_name, array $field_config): string {
        $field_operator = '=';

        if (!empty($field_config['expose_field_operator']) && isset($this->value[$field_name]['operator'])) {
            $field_operator = $this->value[$field_name]['operator'];
        } elseif (!empty($field_config['field_operator'])) {
            $field_operator = $field_config['field_operator'];
        }

        return $this->isValidOperator($field_operator) ? $field_operator : '=';
    }

    /**
     * Apply nested conditions to the query.
     */
    protected function applyNestedConditions(array $conditions): void {
        $operator = $this->options['operator'] ?? 'and';
        $parent_field = $this->getRealField();

        if (empty($parent_field)) {
            return;
        }

        $nested_field_condition = new NestedParentFieldConditionGroup(strtoupper($operator));
        $nested_field_condition->setParentFieldName($parent_field);
        
        foreach ($conditions as $condition) {
            $nested_field_condition->addChildFieldCondition(
                $condition['child_field_name'],
                $condition['value'],
                $condition['operator']
            );
        }
        $this->query->addConditionGroup($nested_field_condition);
    }


    /**
     * Get enabled fields from configuration.
     */
    protected function getEnabledFields(): array {
        $filter_field_settings = $this->options['filter_field_settings'] ?? [];
        
        return array_filter($filter_field_settings, function($config) {
            return !empty($config['enabled']);
        });
    }

    /**
     * Get enabled fields sorted by weight.
     */
    protected function getEnabledAndSortedFields(): array {
        $enabled = $this->getEnabledFields();

        uasort($enabled, function($a, $b) {
            return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
        });

        return $enabled;
    }

    /**
     * Check if configuration has any enabled fields.
     */
    protected function hasEnabledFields(array $filter_field_settings): bool {
        foreach ($filter_field_settings as $field_config) {
            if (!empty($field_config['enabled'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get Form API states for disabling field when not enabled.
     */
    protected function getFieldDisabledState(string $field_name): array {
        return [
            'disabled' => [
                ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
            ],
        ];
    }

    /**
     * Get target entity type for autocomplete field.
     */
    protected function getTargetTypeForField(string $field_name): string {
        $index = $this->getIndex();
        $real_field = $this->getRealField();
        
        if ($index instanceof Index && !empty($real_field)) {
            $target_type = $this->relationSearchService->getNestedFieldTargetType($index, $real_field, $field_name);
            if ($target_type) {
                return $target_type;
            }
        }
        
        // Fallback based on field name
        if (strpos($field_name, 'relation_type') !== false) {
            return 'taxonomy_term';
        }
        
        return 'node';
    }

    /**
     * Get default entity value for autocomplete field.
     */
    protected function getDefaultEntityValue(string $field_name, string $target_type) {
        $value = $this->value[$field_name]['value'] ?? $this->value[$field_name] ?? NULL;
        
        if (empty($value) || !is_numeric($value)) {
            return NULL;
        }

        try {
            return $this->entityTypeManager->getStorage($target_type)->load($value);
        } catch (\Exception $e) {
            return NULL;
        }
    }

    /**
     * Get available operator options.
     */
    protected function getOperatorOptions(): array {
        return [
            '=' => $this->t('Is equal to'),
            '!=' => $this->t('Is not equal to'),
            '<' => $this->t('Is less than'),
            '<=' => $this->t('Is less than or equal to'),
            '>' => $this->t('Is greater than'),
            '>=' => $this->t('Is greater than or equal to'),
            'IN' => $this->t('Is one of'),
            'NOT IN' => $this->t('Is not one of'),
            'BETWEEN' => $this->t('Is between'),
            'NOT BETWEEN' => $this->t('Is not between'),
            '<>' => $this->t('Contains'),
        ];
    }

    /**
     * Check if operator is valid.
     */
    protected function isValidOperator(string $operator): bool {
        return array_key_exists($operator, $this->getOperatorOptions());
    }

    /**
     * Get the real field name for this filter.
     */
    protected function getRealField(): ?string {
        return $this->definition['real field'] ?? null;
    }

    /**
     * Get default filter options.
     */
    protected function getDefaultFilterOptions(): array {
        return [
            'filter_field_settings' => [],
            'operator' => 'and',
            'expose_operators' => FALSE,
        ];
    }


/**
 * Get dropdown options with caching.
 */
protected function getDropdownOptions(string $field_name, string $display_mode = 'label'): array {
    if (isset($this->valueOptions[$field_name])) {
        return $this->valueOptions[$field_name];
    }
    // Check persistent cache
    $cache_key = $this->getCacheKey($field_name);
    $cached = \Drupal::cache()->get($cache_key);
    
    if ($cached) {
        $this->valueOptions[$field_name] = $cached->data;
        return $cached->data;
    }
    // NOT cached - fetch now (can happen on first exposed form load)
    try {
        $options = $this->fetchOptionsFromIndex($field_name, $display_mode);
        // Cache it
        $this->valueOptions[$field_name] = $options;
        \Drupal::cache()->set(
            $cache_key, 
            $options, 
            Cache::PERMANENT,
            ['relationship_filter_options']
        );
        
        return $options;
    } catch (\Exception $e) {
        \Drupal::logger('relationship_nodes_search')->error(
            'Failed to fetch options for @field: @message',
            ['@field' => $field_name, '@message' => $e->getMessage()]
        );
        return [];
    }
}
protected function addFieldValueField(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
    $form['filter_field_settings'][$field_name]['value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#default_value' => $filter_field_settings[$field_name]['value'] ?? '',
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
 * Fetch options from index using aggregation query.
 */

protected function fetchOptionsFromIndex(string $field_name, string $display_mode): array {
    $index = $this->getIndex();
    $real_field = $this->getRealField();
    
    if (!$index instanceof Index || empty($real_field)) {
        return [];
    }
    
    $field_id = $real_field . ':' . $field_name;
    $full_field_path = $this->relationSearchService->colonsToDots($field_id);
    
    $query = $index->query();
    $query->range(0, 0);
    
    // Set facet configuration
    $query->setOption('search_api_facets', [
        $field_id => [
            'field' =>  $full_field_path,
            'limit' => 0,
            'operator' => 'or',
            'min_count' => 1,
            'missing' => FALSE,
        ],
    ]);
    
    
    try {
        $results = $query->execute();
        $facets = $results->getExtraData('search_api_facets', []);
        if (empty($facets[$field_id])) {
            return [];
        }
        
        $results = array_column($facets[$field_id], 'filter');
        $display_mode = 'raw'; // voorkom exception MOET VERWIJDERD WORDEN OP TERMIJN
        return $this->convertFacetResultsToOptions($results, $field_name, $display_mode);
        
    } catch (\Exception $e) {
        \Drupal::logger('relationship_nodes_search')->error(
            'Failed to fetch dropdown options: @message',
            ['@message' => $e->getMessage()]
        );
        return [];
    }
}

/**
 * Convert entity IDs to display options.
 */
protected function convertFacetResultsToOptions(array $results, string $field_name, string $display_mode = 'raw'): array {
    if (empty($results)) {
        return [];
    }
    $results = $this->nestedAggregationService->cleanFacetResults($results);
    if ($display_mode === 'raw') {
        return array_combine($results, $results);
    }
    
    $target_type = $this->getTargetTypeForField($field_name);
    
    try {
        $storage = $this->entityTypeManager->getStorage($target_type);
        $entities = $storage->loadMultiple($results);
        
        $options = [];
        foreach ($entities as $id => $entity) {
            $options[$id] = $entity->label();
        }
        
        return $options;
    } catch (\Exception $e) {
        \Drupal::logger('relationship_nodes_search')->error('Failed to load entities: @message', ['@message' => $e->getMessage()]);
        return array_combine($results, $results);
    }
}

    protected function getCacheKey(string $field_name): string {
        $index = $this->getIndex();
        $real_field = $this->getRealField();
        return "relationship_filter:{$index->id()}:{$real_field}:{$field_name}";
    }
}