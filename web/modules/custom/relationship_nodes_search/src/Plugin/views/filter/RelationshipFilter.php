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
        $sapi_fld_nm = $this->getSapiField();  
        if (!$index instanceof Index || empty($sapi_fld_nm)) {
            $form['error'] = [
                '#markup' => $this->t('Cannot load index or field configuration.'),
            ];
            return;
        }
        
        $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_fld_nm);
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

        $child_fld_settings = $this->getFieldSettings();
        $this->filterConfigurator->buildNestedWidgetConfigForm($form, $index, $sapi_fld_nm, $available_fields, $child_fld_settings);
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

        $child_fld_settings =  $this->getFieldSettings();
        $enabled = $this->filterWidgetHelper->getEnabledFields($child_fld_settings);
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
protected function valueForm(&$form, FormStateInterface $form_state):void {
    $index = $this->getIndex();
    $sapi_fld_nm = $this->getSapiField();
    if (!$index instanceof Index || empty($sapi_fld_nm)) {
        return;
    }

    $child_fld_settings = $this->options['exposed'] ? $this->getFieldSettings() : [];
    $child_fld_values = is_array($this->value) ? $this->value : [];
    $exp_op = $this->options['expose_operators'] ?? false;

    $this->filterWidgetHelper->buildExposedFieldWidget(
        $form, ['value'], $index, $sapi_fld_nm, $child_fld_settings, $child_fld_values, $exp_op
    );
}
    


    public function query():void {
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
     * Build filter conditions from form values.
     */
protected function buildFilterConditions(): array {
    $child_fld_settings = $this->getFieldSettings();
    $conditions = [];
    foreach ($child_fld_settings as $child_fld_nm => $field_config) {
        if (empty($field_config['enabled'])) {
            continue;
        }

        if ($this->options['exposed']) {
            $value = $this->value[$child_fld_nm]['value'] ?? $this->value[$child_fld_nm] ?? '';
        } 

        else {
            $value = $field_config['value'] ?? '';
        }
        
        if ($value === '' || $value === NULL) {
            continue;
        }
        $field_operator = $this->getFieldOperator($child_fld_nm, $field_config);

            $conditions[] = [
                'child_field_name' => $child_fld_nm,
                'value' => $value,
                'operator' => $field_operator,
            ];
        }
        return $conditions;
}


    /**
     * Get the operator for a specific field.
     */
    protected function getFieldOperator(string $child_fld_nm, array $field_config): string {
        $field_operator = '=';

        if (!empty($field_config['expose_field_operator']) && isset($this->value[$child_fld_nm]['operator'])) {
            $field_operator = $this->value[$child_fld_nm]['operator'];
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
        $sapi_fld_nm = $this->getSapiField();

        if (empty($sapi_fld_nm)) {
            return;
        }

        $nested_field_condition = new NestedParentFieldConditionGroup(strtoupper($operator));
        $nested_field_condition->setParentFieldName($sapi_fld_nm);
        
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
    protected function getTargetTypeForField(string $child_fld_nm): string {
        $index = $this->getIndex();
        $sapi_fld_nm = $this->getSapiField();
        
        if ($index instanceof Index && !empty($sapi_fld_nm)) {
            $target_type = $this->relationSearchService->getNestedFieldTargetType($index, $sapi_fld_nm, $child_fld_nm);
            if ($target_type) {
                return $target_type;
            }
        }
        
        // Fallback based on field name
        if (strpos($child_fld_nm, 'relation_type') !== false) {
            return 'taxonomy_term';
        }
        
        return 'node';
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
     * Get the real field name for this filter in the index.
     */
    protected function getSapiField(): ?string {
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