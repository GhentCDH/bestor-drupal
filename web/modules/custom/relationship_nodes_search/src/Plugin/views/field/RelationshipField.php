<?php

namespace Drupal\relationship_nodes_search\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\field\SearchApiStandard;
use Drupal\views\ResultRow;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\relationship_nodes_search\Service\RelationViewService;
use Drupal\search_api\Entity\Index;


/**
 * @ViewsField("search_api_relationship_field")
 */
class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
    
    protected RelationViewService $relationViewService;


    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        RelationViewService $relationViewService,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->relationViewService = $relationViewService;
    }
    

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('relationship_nodes_search.relation_view_service'),
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
        
        $real_field = $this->definition['search_api field'] ?? '';
        $index = $this->getIndex();
        
        if (!$index instanceof Index || empty($real_field)) {
            $form['error'] = [
                '#markup' => $this->t('Cannot load index or field configuration.'),
            ];
            return;
        }

        $available_fields = $this->relationViewService->getCalculatedFields($index, $real_field);
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
        foreach (get_object_vars($values) as $property => $value) {
            if (str_starts_with($property, 'relationship_info__') && is_array($value)) {
                return $value;
            }
        }
        return parent::getValue($values, $field);
    }
    /**
     * Complex array data cannot be sorted directly.
     */
    public function clickSortable() {
        return FALSE;
    }

    /**
     * Override to prevent rendering of individual items.
     * all rendering by the render() method.
     */
    public function renderItems($items) {
        // Return empty - we handle rendering in render() method
        return [];
    }

    /**
     * Override advancedRender to prevent default field rendering.
     * Our render() method returns a render array that should be used directly.
     */
    public function advancedRender(ResultRow $values) {
        return $this->render($values);
    }

    /**
     * Override render_item to prevent it from being called.
     * This prevents the "array to string" error.
     */
    public function render_item($count, $item) {
        // This should never be called because we return render array from render()
        return '';
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

    protected function prepareTemplateData(array $nested_data) {

        $selected_fields = array_filter($this->options['relation_fields'] ?? []); //weg


        $field_settings = $this->options['field_settings'] ?? [];

        $relationships = [];
        foreach ($nested_data as $item) {
            $item_with_values = [];
            foreach ($field_settings as $field_name => $settings) {
                if (!empty($settings['enabled']) && isset($item[$field_name])) {
                    $item_with_values[$field_name] = $item[$field_name];
                }
            }
            if (!empty($item_with_values)) {
                $relationships[] = $item_with_values;
            }
        }


        // Sort if needed
        if (!empty($this->options['sort_by_field']) && !empty($relationships)) {
            $sort_field = $this->options['sort_by_field'];
            usort($relationships, function($a, $b) use ($sort_field) {
                $val_a = $a[$sort_field] ?? '';
                $val_b = $b[$sort_field] ?? '';
                
                // Case-insensitive vergelijking
                return strcasecmp($val_a, $val_b);
            });
        }
        
        // Group if needed
        $grouped = [];
        if (!empty($this->options['group_by_field']) && !empty($relationships)) {
            $group_field = $this->options['group_by_field'];
            foreach ($relationships as $item) {
                $group_key = $item[$group_field] ?? 'ungrouped';
                if (!isset($grouped[$group_key])) {
                    $grouped[$group_key] = [];
                }
                $grouped[$group_key][] = $item;
            }
        }

        $fields = [];
        foreach ($field_settings as $field_name => $settings) {
            // Only include if enabled
            if (empty($settings['enabled'])) {
                continue;
            }
            
            $fields[$field_name] = [
                'name' => $field_name,
                'label' => !empty($settings['label']) 
                    ? $settings['label'] 
                    : $this->formatFieldLabel($field_name),
                'type' => $this->getFieldType($field_name),
                'weight' => $settings['weight'] ?? 0,
                'hide_label' => !empty($settings['hide_label']),
            ];
        }
        
        // Sort fields by weight
        uasort($fields, function($a, $b) {
            return $a['weight'] <=> $b['weight'];
        });
        
        // Create summary data
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

    protected function getFieldType($field_name) {
        if (str_ends_with($field_name, '_id')) {
            return 'id';
        }
        if (str_ends_with($field_name, '_title') || str_ends_with($field_name, '_label')) {
            return 'title';
        }
        if (str_ends_with($field_name, '_type')) {
            return 'type';
        }
        return 'text';
    }

        protected function getCalculatedFields():array {

        $index = $this->getIndex();
        $real_field = $this->getRealField();
             
        if (!$index instanceof Index || empty($real_field)) {
            return [];
        }

        return $this->relationViewService->getCalculatedFields($index, $real_field) ?? [];
    }

    protected function getOriginalNestedFields(): array {
        $index = $this->getIndex();
        $real_field = $this->getRealField();
             
        if (!$index instanceof Index || empty($real_field)) {
            return [];
        }

        return $this->relationViewService->getOriginalNestedFields($index, $real_field) ?? [];
    }

    protected function getRealField(): ?string {
        return $this->definition['search_api field'] ?? null;
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