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
 * Field handler for nested relationship data with configurable sub-fields.
 *
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
        // Welke nested fields tonen
        $options['relation_fields'] = ['default' => []];
        $options['template'] = ['default' => 'relationship-field'];
        $options['sort_by_field'] = ['default' => ''];
        $options['group_by_field'] = ['default' => ''];

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

        $form['field_settings'] = [
            '#type' => 'details',
            '#title' => $this->t('Relation fields'),
            '#open' => TRUE,
        ];


        $form['field_settings']['template'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Template name'),
            '#default_value' => $this->options['template'],
            '#description' => $this->t('Template file name without .html.twig extension. Will look for templates/[name].html.twig'),
            
        ];


        $form['field_settings']['relation_fields'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Fields to pass to template'),
            '#options' => array_combine($available_fields, $available_fields),
            '#default_value' => $this->options['relation_fields'] ?? [],
            '#description' => $this->t('Select which fields to make available in the template.'),
        ];

        $form['field_settings']['sort_by_field'] = [
            '#type' => 'select',
            '#title' => $this->t('Sort by field'),
            '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
            '#default_value' => $this->options['sort_by_field'],
            '#description' => $this->t('Sort relationships by this field value.'),
        ];

        $form['field_settings']['group_by_field'] = [
            '#type' => 'select',
            '#title' => $this->t('Group by field'),
            '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
            '#default_value' => $this->options['group_by_field'],
            '#description' => $this->t('Group relationships by this field value.'),
        ];
    }


    public function submitOptionsForm(&$form, FormStateInterface $form_state) {
        parent::submitOptionsForm($form, $form_state);
        
        $field_settings = $form_state->getValue(['options', 'field_settings']);
        if ($field_settings) {
            foreach ($field_settings as $key => $value) {
                $this->options[$key] = $value;
            }
        }
        // Fixed: changed $options to $this->options
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
        $selected_fields = array_filter($this->options['relation_fields'] ?? []);
        
        // Filter data to only include selected fields
        $relationships = [];
        foreach ($nested_data as $item) {
            $filtered_item = [];
            foreach ($selected_fields as $field_name) {
                if (isset($item[$field_name])) {
                    $filtered_item[$field_name] = $item[$field_name];
                }
            }
            if (!empty($filtered_item)) {
                $relationships[] = $filtered_item;
            }
        }
        
        // Sort if needed
        if (!empty($this->options['sort_by_field']) && !empty($relationships)) {
            $sort_field = $this->options['sort_by_field'];
            usort($relationships, function($a, $b) use ($sort_field) {
                return ($a[$sort_field] ?? '') <=> ($b[$sort_field] ?? '');
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
        
        // Create summary data
        $summary = [
            'total' => count($relationships),
            'fields' => array_keys($selected_fields),
            'has_groups' => !empty($grouped),
            'group_count' => count($grouped),
        ];
        
        // Add field metadata
        $fields = [];
        foreach ($selected_fields as $field_name) {
            $fields[$field_name] = [
                'name' => $field_name,
                'label' => $this->formatFieldLabel($field_name),
                'type' => $this->getFieldType($field_name),
            ];
        }
        
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
}