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


    public function buildNestedWidgetConfigForm(array &$form, array $child_fld_nms, array $child_fld_settings, string $facet_id = null): void {
        $form['filter_field_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Filter fields'),
            '#description' => $this->t('Select which fields should be available for filtering.'),
            '#tree' => TRUE,
        ];

        foreach ($child_fld_nms as $child_fld_nm) {
            $is_enabled = !empty($child_fld_settings[$child_fld_nm]['enabled']);
            $disabled_state = $this->getFieldDisabledState($child_fld_nm, $facet_id);
            $form['filter_field_settings'][$child_fld_nm] = [
                '#type' => 'details',
                '#title' => $child_fld_nm,
                '#open' => $is_enabled,
            ];

            $is_facet = !empty($facet_id);
            $this->addFieldEnableCheckbox($form, $child_fld_nm, $child_fld_settings);
            $this->addFieldLabel($form, $child_fld_nm, $child_fld_settings, $disabled_state);
            $this->addFieldWidget($form, $child_fld_nm, $child_fld_settings, $disabled_state, $facet_id);
            $this->addFieldWeight($form, $child_fld_nm, $child_fld_settings, $disabled_state);
            if(!$is_facet) $this->addFieldRequired($form, $child_fld_nm, $child_fld_settings, $disabled_state);
            $this->addFieldPlaceholder($form, $child_fld_nm, $child_fld_settings, $disabled_state);
            if(!$is_facet) $this->addFieldOperator($form, $child_fld_nm, $child_fld_settings, $disabled_state);
            if(!$is_facet) $this->addExposeFieldOperator($form, $child_fld_nm, $child_fld_settings, $disabled_state);
            if(!$is_facet) $this->addFieldValueField($form, $child_fld_nm, $child_fld_settings, $disabled_state);
        }
    }


    protected function getFieldDisabledState(string $child_fld_nm, string $facet_id = null): array {
        $input_el_name = empty($facet_id)
            ? 'options[filter_field_settings][' . $child_fld_nm . '][enabled]'
            : 'exposed_form_options[bef][filter]['. $facet_id. '][configuration][advanced][filter_field_settings]['.  $child_fld_nm .'][enabled]';
        
        return [
            'disabled' => [
                ':input[name="' . $input_el_name . '"]' => ['checked' => FALSE],
            ],
        ];
    }


    protected function addFieldEnableCheckbox(array &$form, string $child_fld_nm, array $child_fld_settings): void {
        $form['filter_field_settings'][$child_fld_nm]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable this filter field'),
            '#default_value' => !empty($child_fld_settings[$child_fld_nm]['enabled']),
        ];
    }


    protected function addFieldLabel(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
        $form['filter_field_settings'][$child_fld_nm]['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#default_value' => $child_fld_settings[$child_fld_nm]['label'] 
                ?? $this->relationSearchService->formatCalculatedFieldLabel($child_fld_nm),
            '#description' => $this->t('Label shown to users when exposed.'),
            '#size' => 30,
            '#states' => $disabled_state,
        ];
    }


    protected function addFieldWidget(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state, string $facet_id = null): void {
        $form['filter_field_settings'][$child_fld_nm]['widget'] = [
            '#type' => 'select',
            '#title' => $this->t('Widget type'),
            '#options' => [
                'textfield' => $this->t('Text field'),
                'select' => $this->t('Dropdown (from indexed values)'),
                /* // ENTITY AUTOCOMPLETE NOT YET IMPLEMENTED (CF WIDGET HELPER) 
                'entity_autocomplete' => $this->t('Entity autocomplete'), */
            ],
            '#default_value' => $child_fld_settings[$child_fld_nm]['widget'] ?? 'textfield',
            '#states' => $disabled_state,
            '#description' => $this->t('Dropdown automatically loads all unique values from the search index.'),
        ];
        
        // Display mode for dropdown options
        $input_el_name = empty($facet_id)
            ? 'options[filter_field_settings][' . $child_fld_nm . '][widget]'
            : 'exposed_form_options[bef][filter]['. $facet_id. '][configuration][advanced][filter_field_settings]['.  $child_fld_nm .'][widget]';
        
        $form['filter_field_settings'][$child_fld_nm]['select_display_mode'] = [
            '#type' => 'radios',
            '#title' => $this->t('Display mode for dropdown options'),
            '#options' => [
                'raw' => $this->t('Raw value (ID)'),
                'label' => $this->t('Label (entity name)'),
            ],
            '#default_value' => $child_fld_settings[$child_fld_nm]['select_display_mode'] ?? 'raw',
            '#description' => $this->t('How to display options in the dropdown. Only applies to entity reference fields.'),
            '#states' => array_merge(
                $disabled_state,
                [
                    'visible' => [
                        ':input[name="' . $input_el_name . '"]' => ['value' => 'select'],
                    ],
                ]
            ),
        ];
    }
 protected function addFieldWeight(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
        $form['filter_field_settings'][$child_fld_nm]['weight'] = [
            '#type' => 'number',
            '#title' => $this->t('Weight'),
            '#default_value' => $child_fld_settings[$child_fld_nm]['weight'] ?? 0,
            '#description' => $this->t('Fields with lower weights appear first.'),
            '#size' => 5,
            '#states' => $disabled_state,
        ];
    }
protected function addFieldRequired(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
        $form['filter_field_settings'][$child_fld_nm]['required'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Required'),
            '#default_value' => $child_fld_settings[$child_fld_nm]['required'] ?? FALSE,
            '#description' => $this->t('Make this field required when exposed.'),
            '#states' => $disabled_state,
        ];
    }

     protected function addFieldPlaceholder(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
        $form['filter_field_settings'][$child_fld_nm]['placeholder'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Placeholder'),
            '#default_value' => $child_fld_settings[$child_fld_nm]['placeholder'] ?? '',
            '#description' => $this->t('Placeholder text for the filter field.'),
            '#states' => $disabled_state,
        ];
    }

        protected function addFieldOperator(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
        $form['filter_field_settings'][$child_fld_nm]['field_operator'] = [
            '#type' => 'select',
            '#title' => $this->t('Operator'),
            '#options' => $this->getOperatorOptions(),
            '#default_value' => $child_fld_settings[$child_fld_nm]['field_operator'] ?? '=',
            '#description' => $this->t('Comparison operator for this field.'),
            '#states' => $disabled_state,
        ];
    }

        protected function addExposeFieldOperator(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
        $form['filter_field_settings'][$child_fld_nm]['expose_field_operator'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Let user choose operator'),
            '#default_value' => $child_fld_settings[$child_fld_nm]['expose_field_operator'] ?? FALSE,
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

    protected function addFieldValueField(array &$form, string $child_fld_nm, array $child_fld_settings, array $disabled_state): void {
    $form['filter_field_settings'][$child_fld_nm]['value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#default_value' => $child_fld_settings[$child_fld_nm]['value'] ?? '',
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