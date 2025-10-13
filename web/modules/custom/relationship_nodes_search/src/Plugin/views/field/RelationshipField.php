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


    public function defineOptions() {
        $options = parent::defineOptions();
        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
            $options[$option] = ['default' => $default];
        }
        return $options;
    }


    public function buildOptionsForm(&$form, FormStateInterface $form_state) {
        parent::buildOptionsForm($form, $form_state);
        
        $sapi_field = $this->definition['search_api field'] ?? '';
        $index = $this->getIndex();
        
        if (!$index instanceof Index || empty($sapi_field)) {
            $form['error'] = [
                '#markup' => $this->t('Cannot load index or field configuration.'),
            ];
            return;
        }

        $available_fields = $this->relationSearchService->getCalculatedFields($index, $sapi_field);
        
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

        foreach ($available_fields as $field_name) {
            $is_enabled = !empty($field_settings[$field_name]['enabled']);

            $link_option = $this->titleFieldHasMatchingIdField($field_name);
            $is_link = $is_enabled && $link_option && !empty($field_settings[$field_name]['link']);
            
            $form['relation_display_settings']['field_settings'][$field_name] = [
                '#type' => 'details',
                '#title' => $field_name,
                '#open' => $is_enabled,
            ];

            $form['relation_display_settings']['field_settings'][$field_name]['enabled'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Display this field'),
                '#default_value' => $is_enabled,
            ];

            if($link_option){
                 $form['relation_display_settings']['field_settings'][$field_name]['link'] = [
                    '#type' => 'checkbox',
                    '#title' => $this->t('Display this field as a link'),
                    '#default_value' => $is_link,
                ];

            }

            $form['relation_display_settings']['field_settings'][$field_name]['label'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Custom label'),
                '#default_value' => $field_settings[$field_name]['label'] ?? $this->formatFieldLabel($field_name),
                '#description' => $this->t('Custom label for this field.'),
                '#size' => 30,
                '#states' => [
                    'disabled' => [
                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                    ],
                ],
            ];

            $form['relation_display_settings']['field_settings'][$field_name]['weight'] = [
                '#type' => 'number',
                '#title' => $this->t('Weight'),
                '#default_value' => $field_settings[$field_name]['weight'] ?? 0,
                '#description' => $this->t('Fields with lower weights appear first.'),
                '#size' => 5,
                '#states' => [
                    'disabled' => [
                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                    ],
                ],
            ];

            $form['relation_display_settings']['field_settings'][$field_name]['hide_label'] = [
                '#type' => 'checkbox',
                '#title' => $this->t('Hide label in output'),
                '#default_value' => $field_settings[$field_name]['hide_label'] ?? FALSE,
                '#states' => [
                    'disabled' => [
                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                    ],
                ],
            ];
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

    
    public function submitOptionsForm(&$form, FormStateInterface $form_state) {
        parent::submitOptionsForm($form, $form_state);
        $relation_options = $form_state->getValue(['options', 'relation_display_settings']);
        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
            if (isset($relation_options[$option])) {
                $this->options[$option] = $relation_options[$option];
            }
        }
    }  

   
    public function getValue(ResultRow $values, $field = NULL) {
        $indexed_relation_fields = $this->getOriginalNestedFields();
        $values_arr = get_object_vars($values);
        if(empty($values_arr) || !is_array($values_arr)){
            return parent::getValue($values, $field);
        }
        $sapi_field = $this->getSearchApiField();
        if(empty($values_arr[$sapi_field])){
            return parent::getValue($values, $field);
        }
        $value = $values_arr[$sapi_field];
        if(!is_array($value)){
            return parent::getValue($values, $field);
        }
        return $value;
    }


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


    /**
     * Override render and sort methods to prevent the default views rendering: force to use custom rendering.
     */
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


    protected function prepareTemplateData(array $nested_data) {

        $field_settings = $this->options['field_settings'] ?? [];

        $relationships = [];
        foreach ($nested_data as $item) {
            $item_with_values = [];
            foreach ($field_settings as $field_name => $settings) {
                if (!empty($settings['enabled']) && isset($item[$field_name])) {
                    $value = $item[$field_name];
                    $is_link = !empty($settings['link']);
                    

                    $item_with_values[$field_name] = [
                        'field_value' => $value,
                        'link_url' => null,
                    ];
                    
                    if ($is_link) {
                        $url = $this->relationSearchService->getUrlForField($field_name, $item);
                        if ($url) {
                            $item_with_values[$field_name]['link_url'] = $url;
                        }
                    }
                }
            }
            if (!empty($item_with_values)) {
                $relationships[] = $item_with_values;
            }
        }

        if (!empty($this->options['sort_by_field']) && !empty($relationships)) {
            $sort_field = $this->options['sort_by_field'];
            usort($relationships, function($a, $b) use ($sort_field) {
                $val_a = $a[$sort_field]['field_value'] ?? '';
                $val_b = $b[$sort_field]['field_value'] ?? '';
                
                return strcasecmp($val_a, $val_b);
            });
        }
        
        $grouped = [];
        if (!empty($this->options['group_by_field']) && !empty($relationships)) {
            $group_field = $this->options['group_by_field'];
            foreach ($relationships as $item) {
                $group_key = $item[$group_field]['field_value'] ?? 'ungrouped';
        
                if (!isset($grouped[$group_key])) {
                    $grouped[$group_key] = [];
                }
                $grouped[$group_key][] = $item;
            }
        }

        $fields = [];
        foreach ($field_settings as $field_name => $settings) {

            if (empty($settings['enabled'])) {
                continue;
            }
            
            $fields[$field_name] = [
                'name' => $field_name,
                'label' => !empty($settings['label']) 
                    ? $settings['label'] 
                    : $this->formatFieldLabel($field_name),
                'weight' => $settings['weight'] ?? 0,
                'hide_label' => !empty($settings['hide_label']),
                'is_link' => !empty($settings['link']),
            ];
        }

        uasort($fields, function($a, $b) {
            return $a['weight'] <=> $b['weight'];
        });
        
        $summary = [
            'total' => count($relationships),
            'fields' => array_keys($fields),
            'has_groups' => !empty($grouped),
            'group_count' => count($grouped),
        ];
             
        return [
            'relationships' => $relationships,
            'grouped' => $grouped,
            'summary' => $summary,
            'fields' => $fields,
        ];
    }


    protected function formatFieldLabel($field_name) {
        $label = str_replace(['calculated_', '_'], ['', ' '], $field_name);
        return ucfirst(trim($label));
    }


    protected function getCalculatedFields():array {
        $index = $this->getIndex();
        $sapi_field = $this->getSearchApiField();    
        if (!$index instanceof Index || empty($sapi_field)) {
            return [];
        }

        return $this->relationSearchService->getCalculatedFields($index, $sapi_field) ?? [];
    }


    protected function getOriginalNestedFields(): array {
        $index = $this->getIndex();
        $sapi_field = $this->getSearchApiField();      
        if (!$index instanceof Index || empty($sapi_field)) {
            return [];
        }

        return $this->relationSearchService->getOriginalNestedFields($index, $sapi_field) ?? [];
    }


    protected function titleFieldHasMatchingIdField(string $title_field_name):bool{
        $index = $this->getIndex();
        $sapi_field = $this->getSearchApiField();   
        if (!$index instanceof Index || empty($sapi_field)) {
            return false;
        }

        return $this->relationSearchService->titleFieldHasMatchingIdField($index, $sapi_field, $title_field_name);
    }


    protected function getSearchApiField(): ?string {
        return $this->definition['search_api field'] ?? null; // With space - as such implemented in search api.
    }


    protected function getDefaultRelationFieldOptions(){
        return [
            'field_settings' => [],
            'sort_by_field' => '',
            'group_by_field' => '',
            'template' => 'relationship-field',
        ];
    }
}