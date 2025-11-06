<?php

namespace Drupal\relationship_nodes_search\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\field\SearchApiStandard;
use Drupal\views\ResultRow;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\relationship_nodes_search\Service\RelationSearchService;
use Drupal\search_api\Entity\Index;


/**
 * @ViewsField("search_api_relationship_field")
 */
class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
    
    protected RelationSearchService $relationSearchService;


    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        RelationSearchService $relationSearchService,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->relationSearchService = $relationSearchService;
    }
    
    
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('relationship_nodes_search.relation_search_service'),
        );
    }


    /**
     * Inherit docs.
     */
    public function defineOptions() {
        $options = parent::defineOptions();
        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
            $options[$option] = ['default' => $default];
        }
        return $options;
    }


    /**
     * Inherit docs.
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state) {
        parent::buildOptionsForm($form, $form_state);
        
        $sapi_fld_nm = $this->getSearchApiField();
        $index = $this->getIndex();
        
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

        $form['relation_display_settings'] = [
            '#type' => 'details',
            '#title' => $this->t('Relation display settings'),
            '#open' => TRUE,
        ];

        $form['relation_display_settings']['field_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Field configuration'),
            '#description' => $this->t('Select fields to display and configure their appearance.'),
            '#tree' => TRUE
        ];

        $field_settings = $this->options['field_settings'] ?? [];

        foreach ($available_fields as $child_fld_nm) {
            $is_enabled = !empty($field_settings[$child_fld_nm]['enabled']);
            $disabled_state = ['disabled' => [':input[name="options[relation_display_settings][field_settings][' . $child_fld_nm . '][enabled]"]' => ['checked' => FALSE]]];

            $link_option =  $this->relationSearchService->nestedFieldCanLink($index, $sapi_fld_nm, $child_fld_nm);
            
            $form['relation_display_settings']['field_settings'][$child_fld_nm] = [
                '#type' => 'details',
                '#title' => $child_fld_nm,
                '#open' => $is_enabled,
            ];

            $form['relation_display_settings']['field_settings'][$child_fld_nm]['enabled'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Display this field'),
                '#default_value' => $is_enabled,
            ];

            if($link_option){
                $display_mode = $field_settings[$child_fld_nm]['display_mode'] ?? 'raw';
                $form['relation_display_settings']['field_settings'][$child_fld_nm]['display_mode'] = [
                    '#type' => 'radios',
                    '#title' => $this->t('Display mode'),
                    '#options' => [
                        'raw' => $this->t('The raw value (id)'),
                        'label' => $this->t('Label'),
                        'link' => $this->t('Label as link'),
                    ],
                    '#default_value' => $display_mode,
                    '#description' => $this->t('How to display this field value.'),
                    '#states' => $disabled_state,
                ];
            }

            $form['relation_display_settings']['field_settings'][$child_fld_nm]['label'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Custom label'),
                '#default_value' => $field_settings[$child_fld_nm]['label'] ?? $this->relationSearchService->formatCalculatedFieldLabel($child_fld_nm),
                '#description' => $this->t('Custom label for this field.'),
                '#size' => 30,
                '#states' => $disabled_state
            ];

            $form['relation_display_settings']['field_settings'][$child_fld_nm]['weight'] = [
                '#type' => 'number',
                '#title' => $this->t('Weight'),
                '#default_value' => $field_settings[$child_fld_nm]['weight'] ?? 0,
                '#description' => $this->t('Fields with lower weights appear first.'),
                '#size' => 5,
                '#states' => $disabled_state
            ];

            $form['relation_display_settings']['field_settings'][$child_fld_nm]['hide_label'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Hide label in output'),
                '#default_value' => $field_settings[$child_fld_nm]['hide_label'] ?? FALSE,
                '#states' => $disabled_state
            ];

            if(!$is_predefined){
                $form['relation_display_settings']['field_settings'][$child_fld_nm]['multiple_separator'] = [
                    '#type' => 'textfield',
                    '#title' => $this->t('Multiple Values Separator'),
                    '#default_value' => $field_settings[$child_fld_nm]['multiple_separator'] ?? ', ',
                    '#description' => $this->t('Configure how to separate multiple values (only applies if this field has multiple values).'),
                    '#size' => 10,
                    '#states' => $disabled_state
                ];
            }
        }

        $form['relation_display_settings']['sort_by_field'] = [
            '#type' => 'select',
            '#title' => $this->t('Sort by field'),
            '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
            '#default_value' => $this->options['sort_by_field'],
            '#description' => $this->t('Sort relationships by this field value.'),
        ];

        $form['relation_display_settings']['group_by_field'] = [
            '#type' => 'select',
            '#title' => $this->t('Group by field'),
            '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
            '#default_value' => $this->options['group_by_field'],
            '#description' => $this->t('Group relationships by this field value.'),
        ];

        $form['relation_display_settings']['template'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Template name'),
            '#default_value' => $this->options['template'],
            '#description' => $this->t('Template file name without .html.twig extension. Will look for templates/[name].html.twig'),
        ];
    }


    /**
     * Inherit docs.
     */
    public function submitOptionsForm(&$form, FormStateInterface $form_state) {
        parent::submitOptionsForm($form, $form_state);
        $relation_options = $form_state->getValue(['options', 'relation_display_settings']);
        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
            if (isset($relation_options[$option])) {
                $this->options[$option] = $relation_options[$option];
            }
        }
    }  


    /**
     * Inherit docs.
     */
    public function getValue(ResultRow $values, $field = NULL) {
        $index = $this->getIndex();
        $sapi_fld_nm = $this->getSearchApiField();      
        if (!$index instanceof Index || empty($sapi_fld_nm)) {
            return parent::getValue($values, $field);
        }

        $values_arr = get_object_vars($values);
        if(empty($values_arr) || !is_array($values_arr)){
            return parent::getValue($values, $field);
        }

        if(empty($values_arr[$sapi_fld_nm])){
            return parent::getValue($values, $field);
        }
        $value = $values_arr[$sapi_fld_nm];
        if(!is_array($value)){
            return parent::getValue($values, $field);
        }
        return $value;
    }


    /**
     * Override render and sort methods to prevent the default views rendering: force to use custom rendering.
     */
    public function render(ResultRow $values) {
        $nested_data = $this->getValue($values);
        if (empty($nested_data) || !is_array($nested_data)) {
            return '';
        }

        $template_data = $this->prepareTemplateData($nested_data);
        $theme_hook = str_replace('-', '_', $this->options['template']);
        
        return [
            '#theme' => $theme_hook,
            '#relationships' => $template_data['relationships'],
            '#grouped' => $template_data['grouped'],
            '#summary' => $template_data['summary'],
            '#fields' => $template_data['fields'],
            '#row' => $values,
            '#cache' => [
                'contexts' => ['languages:language_content'],
            ],
        ];
    }

    public function clickSortable() {
        return FALSE;
    }

    public function renderItems($items) {
        return [];
    }

    public function advancedRender(ResultRow $values) {
        return $this->render($values);
    }

    
    public function render_item($count, $item) {
        return '';
    }

    /**
     * Prepare the data to be send to the twig template
     */
    protected function prepareTemplateData(array $nested_data): array {
        $field_settings = $this->options['field_settings'] ?? [];
        $index = $this->getIndex();
        $sapi_fld_nm = $this->getSearchApiField();
        
        if (!$index instanceof Index || empty($sapi_fld_nm)) {
            return $this->getEmptyTemplateData();
        }

        $relationships = $this->buildRelationshipsArray($nested_data, $field_settings, $index, $sapi_fld_nm);
        $relationships = $this->sortRelationships($relationships);
        $grouped = $this->groupRelationships($relationships);
        $fields = $this->buildFieldsMetadata($field_settings);
        
        return [
            'relationships' => $relationships,
            'grouped' => $grouped,
            'summary' => $this->buildSummary($relationships, $fields, $grouped),
            'fields' => $fields,
        ];
    }


    /**
     * Build relationships array from nested data.
     */
    protected function buildRelationshipsArray(array $nested_data, array $field_settings, Index $index, string $sapi_fld_nm): array {
        $relationships = [];
        
        foreach ($nested_data as $item) {
            $item_with_values = [];
            
            foreach ($field_settings as $child_fld_nm => $settings) {
                if (empty($settings['enabled']) || !isset($item[$child_fld_nm])) {
                    continue;
                }
                
                $field_value = $this->processFieldValue($item[$child_fld_nm], $settings);
                
                if ($field_value !== null) {
                    $item_with_values[$child_fld_nm] = $field_value;
                }
            }
            
            if (!empty($item_with_values)) {
                $relationships[] = $item_with_values;
            }
        }
        
        return $relationships;
    }


    /**
     * Process a single field value based on its type and settings.
     */
    protected function processFieldValue($raw_value, array $settings): ?array {
        $value_arr = is_array($raw_value) ? $raw_value : [$raw_value];
        $display_mode = $settings['display_mode'] ?? 'raw';
        $processed_values = [];

        foreach($value_arr as $raw_val){
            $processed_values[] = $this->relationSearchService->processSingleFieldValue($raw_val, $display_mode);        
        }
        
        return [
            'field_values' => $processed_values,
            'separator' => $settings['multiple_separator'] ?? ', ',
            'is_multiple' => count($value_arr) > 1,
        ];
               
    }


    /**
     * Sort relationships based on configured sort field.
     */
    protected function sortRelationships(array $relationships): array {
        if (empty($this->options['sort_by_field']) || empty($relationships)) {
            return $relationships;
        }
        
        $sort_fld_nm = $this->options['sort_by_field'];
        usort($relationships, function($a, $b) use ($sort_fld_nm) {
            if (!isset($a[$sort_fld_nm]) || !isset($b[$sort_fld_nm])) {
                return 0;
            }
            
            $val_a = $a[$sort_fld_nm]['field_values'][0]['value'] ?? '';
            $val_b = $b[$sort_fld_nm]['field_values'][0]['value'] ?? '';
            
            return strcasecmp($val_a, $val_b);
        });
        
        return $relationships;
    }


    /**
     * Group relationships based on configured group field.
     */
    protected function groupRelationships(array $relationships): array {
        if (empty($this->options['group_by_field']) || empty($relationships)) {
            return [];
        }
        
        $grouped = [];
        $sort_fld_nm = $this->options['group_by_field'];
        
        foreach ($relationships as $item) {
            if (!isset($item[$sort_fld_nm])) {
                continue;
            }
            
            $group_key = $item[$sort_fld_nm]['field_values'][0]['value'] ?? 'ungrouped';
            
            if (!isset($grouped[$group_key])) {
                $grouped[$group_key] = [];
            }
            $grouped[$group_key][] = $item;
        }
        
        return $grouped;
    }


    /**
     * Build fields metadata for template.
     */
    protected function buildFieldsMetadata(array $field_settings): array {
        $fields = [];
        
        foreach ($field_settings as $child_fld_nm => $settings) {
            if (empty($settings['enabled'])) {
                continue;
            }
            
            $fields[$child_fld_nm] = [
                'name' => $child_fld_nm,
                'label' => !empty($settings['label']) 
                    ? $settings['label'] 
                    :  $this->relationSearchService->formatCalculatedFieldLabel($child_fld_nm),
                'weight' => $settings['weight'] ?? 0,
                'hide_label' => !empty($settings['hide_label']),
                'display_mode' => $settings['display_mode'] ?? 'id',
                'multiple_separator' => $settings['multiple_separator'] ?? ', '
            ];
        }

        uasort($fields, function($a, $b) {
            return $a['weight'] <=> $b['weight'];
        });
        
        return $fields;
    }


    /**
     * Build summary data for template.
     */
    protected function buildSummary(array $relationships, array $fields, array $grouped): array {
        return [
            'total' => count($relationships),
            'fields' => array_keys($fields),
            'has_groups' => !empty($grouped),
            'group_count' => count($grouped),
        ];
    }


    /**
     * Get empty template data structure.
     */
    protected function getEmptyTemplateData(): array {
        return [
            'relationships' => [],
            'grouped' => [],
            'summary' => [
                'total' => 0,
                'fields' => [],
                'has_groups' => false,
                'group_count' => 0
            ],
            'fields' => [],
        ];
    }

    
    /**
     * Get the name of the search api field.
     */
    protected function getSearchApiField(): ?string {
        return $this->definition['search_api field'] ?? null; // With space - as such implemented in search api.
    }


    /**
     * Get an array of options for the field config form in views.
     */
    protected function getDefaultRelationFieldOptions(): array{
        return [
            'field_settings' => [],
            'sort_by_field' => '',
            'group_by_field' => '',
            'template' => 'relationship-field',
        ];
    }
}