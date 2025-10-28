<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;


class NestedFilterConfigurationHelper {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;
    protected LoggerChannelFactoryInterface $loggerFactory;
    protected CacheBackendInterface $cache;
    protected RelationSearchService $relationSearchService;
    protected NestedAggregationService $nestedAggregationService;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        LoggerChannelFactoryInterface $loggerFactory,
        CacheBackendInterface $cache,
        RelationSearchService $relationSearchService,
        NestedAggregationService $nestedAggregationService
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->loggerFactory = $loggerFactory;
        $this->cache = $cache;
        $this->relationSearchService = $relationSearchService;
        $this->nestedAggregationService = $nestedAggregationService;
    }


    public function buildNestedWidgetConfigForm(array &$form, array $all_child_fields, array $child_field_settings, bool $is_facet = false): void {
        $form['filter_field_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Filter fields'),
            '#description' => $this->t('Select which fields should be available for filtering.'),
            '#tree' => TRUE,
        ];

        foreach ($all_child_fields as $field_name) {
            $is_enabled = !empty($child_field_settings[$field_name]['enabled']);
            $disabled_state = $this->getFieldDisabledState($field_name);
            
            $form['filter_field_settings'][$field_name] = [
                '#type' => 'details',
                '#title' => $field_name,
                '#open' => $is_enabled,
            ];

            $this->addFieldEnableCheckbox($form, $field_name, $child_field_settings);
            $this->addFieldLabel($form, $field_name, $child_field_settings, $disabled_state);
            $this->addFieldWidget($form, $field_name, $child_field_settings, $disabled_state);
            $this->addFieldWeight($form, $field_name, $child_field_settings, $disabled_state);
            if(!$is_facet) $this->addFieldRequired($form, $field_name, $child_field_settings, $disabled_state);
            $this->addFieldPlaceholder($form, $field_name, $child_field_settings, $disabled_state);
            if(!$is_facet) $this->addFieldOperator($form, $field_name, $child_field_settings, $disabled_state);
            if(!$is_facet) $this->addExposeFieldOperator($form, $field_name, $child_field_settings, $disabled_state);
            if(!$is_facet) $this->addFieldValueField($form, $field_name, $child_field_settings, $disabled_state);
        }
    }


    protected function getFieldDisabledState(string $field_name): array {
        return [
            'disabled' => [
                ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
            ],
        ];
    }


    protected function addFieldEnableCheckbox(array &$form, string $field_name, array $filter_field_settings): void {
        $form['filter_field_settings'][$field_name]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable this filter field'),
            '#default_value' => !empty($filter_field_settings[$field_name]['enabled']),
        ];
    }


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
protected function addFieldRequired(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['required'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Required'),
            '#default_value' => $filter_field_settings[$field_name]['required'] ?? FALSE,
            '#description' => $this->t('Make this field required when exposed.'),
            '#states' => $disabled_state,
        ];
    }

     protected function addFieldPlaceholder(array &$form, string $field_name, array $filter_field_settings, array $disabled_state): void {
        $form['filter_field_settings'][$field_name]['placeholder'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Placeholder'),
            '#default_value' => $filter_field_settings[$field_name]['placeholder'] ?? '',
            '#description' => $this->t('Placeholder text for the filter field.'),
            '#states' => $disabled_state,
        ];
    }

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
     * Get available operator options.
     */
    public function getOperatorOptions(): array {
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
}