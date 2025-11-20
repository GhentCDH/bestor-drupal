<?php

namespace Drupal\relationship_nodes_search\Service\Widget;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\Query\FilterOperatorHelper;
use Drupal\relationship_nodes_search\Service\Field\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\Service\Widget\NestedFilterDropdownOptionsProvider;


/**
 * Service for building exposed filter form widgets.
 * 
 * Handles the creation of form elements for exposed filters,
 * including operator selectors, select dropdowns, and text fields.
 * 
 * @todo Implement entity autocomplete widget support (see commented methods).
 */
class NestedExposedFormBuilder {

    use StringTranslationTrait;

    protected FilterOperatorHelper $operatorHelper;
    protected CalculatedFieldHelper $calculatedFieldHelper;
    protected NestedFilterDropdownOptionsProvider $dropdownProvider;

    
    /**
     * Constructs a NestedExposedFormBuilder object.
     *
     * @param FilterOperatorHelper $operatorHelper
     *   The filter operator helper service.
     * @param CalculatedFieldHelper $calculatedFieldHelper
     *   The calculated field helper service.
     * @param NestedFilterDropdownOptionsProvider $dropdownProvider
     *   The dropdown options provider service.
     */
    public function __construct(
        FilterOperatorHelper $operatorHelper,
        CalculatedFieldHelper $calculatedFieldHelper,
        NestedFilterDropdownOptionsProvider $dropdownProvider,
    ) {
        $this->operatorHelper = $operatorHelper;
        $this->calculatedFieldHelper = $calculatedFieldHelper;
        $this->dropdownProvider = $dropdownProvider;
    }


    /**
     * Build exposed field widget structure.
     *
     * Creates a container with all enabled child field widgets, sorted by weight.
     * Automatically enriches settings with dropdown options when needed.
     *
     * @param array $form
     *   The form array (passed by reference).
     * @param array $path
     *   Form element path (array of keys).
     * @param \Drupal\search_api\Entity\Index $index
     *   The search index.
     * @param string $sapi_fld_nm
     *   Parent field name.
     * @param array $child_fld_settings
     *   Child field configuration array.
     * @param array $child_fld_values
     *   Current field values from form state.
     * @param bool $expose_operators
     *   Whether to expose operator selectors.
     */
    public function buildExposedFieldWidget(
        array &$form,
        array $path,
        Index $index,
        string $sapi_fld_nm,
        array $child_fld_settings,
        array $child_fld_values = [],
        bool $expose_operators = false
    ): void {
        $child_flds_container = [
        '#type' => 'container',
        '#tree' => TRUE,
        '#attributes' => ['class' => ['relationship-child-field-wrapper']],
        ];

        $this->setFormNestedValue($form, $path, $child_flds_container);

        if (empty($child_fld_settings)) {
        return;
        }

        $enabled_fields = $this->getEnabledAndSortedFields($child_fld_settings);

        foreach ($enabled_fields as $child_fld_nm => $child_fld_config) {
        // Enrich with dropdown options if select widget
        if (($child_fld_config['widget'] ?? 'textfield') === 'select') {
            $display_mode = $child_fld_config['select_display_mode'] ?? 'raw';
            $child_fld_config['options'] = $this->dropdownProvider->getDropdownOptions(
            $index,
            $sapi_fld_nm,
            $child_fld_nm,
            $display_mode
            );
        }

        $child_fld_value = $child_fld_values[$child_fld_nm] ?? null;
        $child_path = array_merge($path, [$child_fld_nm]);

        $this->buildChildFieldElement(
            $form,
            $child_path,
            $child_fld_nm,
            $child_fld_config,
            $child_fld_value,
            $expose_operators
        );
        }
    }


    /**
     * Get enabled fields from configuration.
     *
     * @param array $child_fld_settings
     *   Child field settings array.
     *
     * @return array
     *   Enabled fields only.
     */
    public function getEnabledFields(array $child_fld_settings): array {
        return array_filter($child_fld_settings, function($config) {
        return !empty($config['enabled']);
        });
    }


    /**
     * Get enabled fields sorted by weight.
     *
     * @param array $child_fld_settings
     *   Child field settings array.
     *
     * @return array
     *   Enabled and sorted fields.
     */
    public function getEnabledAndSortedFields(array $child_fld_settings): array {
        $enabled = $this->getEnabledFields($child_fld_settings);

        uasort($enabled, function($a, $b) {
        return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
        });

        return $enabled;
    }


    /**
     * Build a single child field form element.
     *
     * @param array $form
     *   The form array (passed by reference).
     * @param array $path
     *   Form element path.
     * @param string $child_fld_nm
     *   Child field name.
     * @param array $field_config
     *   Field configuration (must include 'options' for select widgets).
     * @param array|null $field_value
     *   Current field value.
     * @param bool $expose_operators
     *   Whether to expose operator selector.
     */
    protected function buildChildFieldElement(
        array &$form,
        array $path,
        string $child_fld_nm,
        array $field_config,
        ?array $field_value = null,
        bool $expose_operators = false
    ): void {
        $widget_type = $field_config['widget'] ?? 'textfield';
        $label = $field_config['label'] ?? $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm);
        $required = !empty($field_config['required']);
        $placeholder = $field_config['placeholder'] ?? '';
        $expose_field_operator = !empty($field_config['expose_field_operator']);

        $child_fld_container = [
        '#type' => 'container',
        '#attributes' => ['class' => ['relationship-filter-field-wrapper']],
        ];
        $this->setFormNestedValue($form, $path, $child_fld_container);

        if ($expose_operators && $expose_field_operator) {
        $this->addOperatorWidget($form, $path, $field_config, $field_value);
        }

        switch ($widget_type) {
        case 'select':
            $this->addSelectWidget($form, $path, $field_config, $label, $required, $field_value);
            break;

        case 'textfield':
        default:
            $this->addTextfieldWidget($form, $path, $label, $required, $placeholder, $field_value);
            break;
        }
    }


    /**
     * Add operator selector widget.
     *
     * Creates a select dropdown for choosing the comparison operator
     * (equals, greater than, etc.).
     *
     * @param array $form
     *   The form array (passed by reference).
     * @param array $path
     *   Form element path.
     * @param array $field_config
     *   Field configuration.
     * @param array|null $field_value
     *   Current field value.
     */
    protected function addOperatorWidget(array &$form, array $path, array $field_config, ?array $field_value = null): void {
        $path[] = 'operator';
        $operator = [
            '#type' => 'select',
            '#title' => $this->t('Operator'),
            '#options' => $this->operatorHelper->getOperatorOptions(),
            '#default_value' => $field_value['operator'] ?? $field_config['field_operator'] ?? $this->operatorHelper->getDefaultOperator(),
            '#attributes' => ['class' => ['relationship-filter-operator']],
        ];
        $this->setFormNestedValue($form, $path, $operator);
    }


    /**
     * Add dropdown select widget.
     *
     * Creates a select dropdown with options provided in field configuration.
     *
     * @param array $form
     *   The form array (passed by reference).
     * @param array $path
     *   Form element path.
     * @param array $field_config
     *   Field configuration (must include 'options' key).
     * @param string $label
     *   Field label.
     * @param bool $required
     *   Whether the field is required.
     * @param array|null $field_value
     *   Current field value.
     */
    protected function addSelectWidget(
        array &$form,
        array $path,
        array $field_config,
        string $label,
        bool $required,
        ?array $field_value = null
    ): void {
        $options = $field_config['options'] ?? [];

        $path[] = 'value';
        $value = [
            '#type' => 'select',
            '#title' => $label,
            '#options' => $options,
            '#default_value' => $field_value['value'] ?? '',
            '#required' => $required,
            '#empty_option' => $required ? NULL : $this->t('- Any -'),
        ];
        $this->setFormNestedValue($form, $path, $value);
    }


    /**
     * Add textfield widget.
     *
     * Creates a simple text input field.
     *
     * @param array $form
     *   The form array (passed by reference).
     * @param array $path
     *   Form element path.
     * @param string $label
     *   Field label.
     * @param bool $required
     *   Whether the field is required.
     * @param string $placeholder
     *   Placeholder text.
     * @param array|null $field_value
     *   Current field value.
     */
    protected function addTextfieldWidget(
        array &$form,
        array $path,
        string $label,
        bool $required,
        string $placeholder,
        ?array $field_value = null
    ): void {
        $path[] = 'value';
        $value = [
            '#type' => 'textfield',
            '#title' => $label,
            '#default_value' => $field_value['value'] ?? '',
            '#required' => $required,
            '#placeholder' => $placeholder,
        ];
        $this->setFormNestedValue($form, $path, $value);
    }


     /* // ENTITY AUTOCOMPLETE NOT YET IMPLEMENTED (CF CONFIG HELPER)
    protected function addEntityAutocompleteWidget(array &$form, array $path, string $child_fld_nm, string $label, bool $required, string $placeholder, ?array $field_value = null): void {
        $target_type =  // implement childfieldentrefhelper getnestedfieldtargettype;
        $default_entity = $this->getDefaultEntityValue($child_fld_nm, $target_type, $field_value);
        $path[] = 'value';
        $value = [
            '#type' => 'entity_autocomplete',
            '#title' => $label,
            '#target_type' => $target_type,
            '#default_value' => $default_entity,
            '#required' => $required,
            '#placeholder' => $placeholder,
        ];
        $this->setFormNestedValue($form, $path, $value);
    }
  
    protected function getDefaultEntityValue(string $child_fld_nm, string $target_type, ?array $field_value = null) {   
        if (empty($field_value) || !is_numeric($field_value)) {
            return null;
        }

        try {
            return $this->entityTypeManager->getStorage($target_type)->load($field_value);
        } catch (\Exception $e) {
            return null;
        }
    }*/



    /**
     * Set a nested value in form array.
     *
     * Navigates through the form array using the path keys and sets the value
     * at the final location, creating intermediate arrays as needed.
     *
     * @param array $form
     *   The form array (passed by reference).
     * @param array $path
     *   Array of keys representing the path to the value.
     * @param mixed $value
     *   The value to set.
     */
    protected function setFormNestedValue(array &$form, array $path, $value): void {
        $ref = &$form;
        foreach ($path as $key) {
        if (!isset($ref[$key]) || !is_array($ref[$key])) {
            $ref[$key] = [];
        }
        $ref = &$ref[$key];
        }
        $ref = $value;
    }



}