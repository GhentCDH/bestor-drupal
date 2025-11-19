<?php

namespace Drupal\relationship_nodes_search\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedParentFieldConditionGroup;
use Drupal\relationship_nodes_search\Service\Query\NestedQueryStructureBuilder;
use Drupal\relationship_nodes_search\Service\Field\NestedFieldHelper;
use Drupal\relationship_nodes_search\Service\Widget\NestedExposedFormBuilder;
use Drupal\relationship_nodes_search\Service\ConfigForm\NestedFieldViewsFilterConfigurator;
use Drupal\relationship_nodes_search\Service\Query\FilterOperatorHelper;

/**
 * Filter for nested relationship data in Search API.
 *
 * @ViewsFilter("search_api_relationship_filter")
 */
class RelationshipFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

    use SearchApiFilterTrait;

    protected NestedFieldHelper $nestedFieldHelper;
    protected NestedExposedFormBuilder $exposedFormBuilder;
    protected NestedFieldViewsFilterConfigurator $filterConfigurator;
    protected NestedQueryStructureBuilder $queryBuilder;
    protected FilterOperatorHelper $operatorHelper;


    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        NestedFieldHelper $nestedFieldHelper,
        NestedExposedFormBuilder $exposedFormBuilder,
        NestedFieldViewsFilterConfigurator $filterConfigurator,
        NestedQueryStructureBuilder $queryBuilder,
        FilterOperatorHelper $operatorHelper
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->nestedFieldHelper = $nestedFieldHelper;
        $this->exposedFormBuilder = $exposedFormBuilder;
        $this->filterConfigurator = $filterConfigurator;
        $this->queryBuilder = $queryBuilder;
        $this->operatorHelper = $operatorHelper;
    }


    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('relationship_nodes_search.nested_field_helper'),
            $container->get('relationship_nodes_search.nested_exposed_form_builder'),
            $container->get('relationship_nodes_search.nested_field_views_filter_configurator'),
            $container->get('relationship_nodes_search.nested_query_structure_builder'),
            $container->get('relationship_nodes_search.filter_operator_helper')
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

        $child_fld_settings = $this->getFieldSettings();
        $this->filterConfigurator->buildConfigForm(
            $form, 
            $config['index'], 
            $config['field_name'], 
            $config['available_fields'], 
            $child_fld_settings
        );
    }

    
    public function submitOptionsForm(&$form, FormStateInterface $form_state) {
        parent::submitOptionsForm($form, $form_state);    
        $this->filterConfigurator->savePluginOptions(
            $form_state,
            $this->getDefaultFilterOptions(),
            $this->options
        );
    }


    /*
    * Create title/description of the filter, visible in the views admin config form
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

    /*
    * Exposed Form
    */
protected function valueForm(&$form, FormStateInterface $form_state):void {
    $index = $this->getIndex();
    $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);
    if (!$index instanceof Index || empty($sapi_fld_nm)) {
        return;
    }

    $child_fld_settings = $this->options['exposed'] ? $this->getFieldSettings() : [];
    $child_fld_values = is_array($this->value) ? $this->value : [];

    $exp_op = $this->options['expose_operators'] ?? false;

    $this->exposedFormBuilder->buildExposedFieldWidget(
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

            // Determine operator - inline!
            $operator = null;
            if (!empty($field_config['expose_field_operator']) && isset($this->value[$child_fld_nm]['operator'])) {
                $operator = $this->value[$child_fld_nm]['operator'];
            } elseif (!empty($field_config['field_operator'])) {
                $operator = $field_config['field_operator'];
            }
            $operator = $this->operatorHelper->sanitizeOperator($operator);

            $conditions[] = [
                'child_field_name' => $child_fld_nm,
                'value' => $value,
                'operator' => $operator,
            ];
        }
        
        return $conditions;
    }


    /**
     * Apply nested conditions to the query.
     */
    protected function applyNestedConditions(array $conditions): void {
        $operator = $this->options['operator'] ?? 'and';
        $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);
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


    protected function getFieldSettings():array{
        return $this->options['filter_field_settings'] ?? [];
    }


    /**
     * Sanitizes a single field value.
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