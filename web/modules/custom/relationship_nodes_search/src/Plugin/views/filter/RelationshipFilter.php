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
use Drupal\relationship_nodes_search\Service\NestedFilterConfigurationHelper;
use Drupal\relationship_nodes_search\Service\NestedFilterExposedWidgetHelper;
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
    protected NestedFilterConfigurationHelper $filterConfigurator;
    protected NestedFilterExposedWidgetHelper $filterWidgetHelper;
    protected ?array $valueOptions = NULL;


    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        NestedAggregationService $nestedAggregationService,
        RelationSearchService $relationSearchService,
        NestedFilterConfigurationHelper $filterConfigurator,
        NestedFilterExposedWidgetHelper $filterWidgetHelper
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->nestedAggregationService = $nestedAggregationService;
        $this->relationSearchService = $relationSearchService;
        $this->filterConfigurator = $filterConfigurator;
        $this->filterWidgetHelper = $filterWidgetHelper;
    }
    

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('relationship_nodes_search.nested_aggregation_service'),
            $container->get('relationship_nodes_search.relation_search_service'),
            $container->get('relationship_nodes_search.nested_filter_configuration_helper'),
            $container->get('relationship_nodes_search.nested_filter_exposed_widget_helper'),
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

        $filter_field_settings = $this->getFieldSettings();
        $this->filterConfigurator->buildNestedWidgetConfigForm($form, $available_fields, $filter_field_settings);
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

        $field_settings =  $this->getFieldSettings();
        $enabled = $this->filterWidgetHelper->getEnabledFields($field_settings);
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
    $field_settings = $this->getFieldSettings();
    $enabled_fields = $this->filterWidgetHelper->getEnabledAndSortedFields($field_settings);    
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
            '#options' => $this->filterConfigurator->getOperatorOptions(),
            '#default_value' => $this->value[$field_name]['operator'] ?? $field_config['field_operator'] ?? '=',
            '#attributes' => ['class' => ['relationship-filter-operator']],
        ];
    }

    /**
     * Add entity autocomplete widget.
     */
    protected function addEntityAutocompleteWidget(array &$form, string $field_name, string $label, bool $required, string $placeholder): void {
        $target_type =  $this->filterConfigurator->getTargetTypeForField($field_name);
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
    $display_mode = $field_config['select_display_mode'] ?? 'raw';
    $index = $this->getIndex();
    $real_field = $this->getRealField();

    $options = $this->filterWidgetHelper->getDropdownOptions($index, $real_field, $field_name, $display_mode);
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
    $filter_field_settings = $this->getFieldSettings();
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


    protected function getFieldSettings():array{
        return $this->options['filter_field_settings'] ?? [];
    }


    /**
     * Check if operator is valid.
     */
    protected function isValidOperator(string $operator): bool {
        return array_key_exists($operator, $this->filterConfigurator->getOperatorOptions());
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
            'value' => [],
            'filter_field_settings' => [],
            'operator' => 'and',
            'expose_operators' => FALSE,
        ];
    }


}