<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relationship_nodes_search\Service\NestedFilterConfigurationHelper;
use Drupal\relationship_nodes_search\Service\ChildFieldEntityReferenceHelper;
use Drupal\relationship_nodes_search\Service\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\Service\NestedFieldHelper;


class NestedFilterExposedWidgetHelper {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;
    protected LoggerChannelFactoryInterface $loggerFactory;
    protected CacheBackendInterface $cache;
    protected NestedFieldHelper $nestedFieldHelper; 
    protected ChildFieldEntityReferenceHelper $childReferenceHelper;
    protected CalculatedFieldHelper $calculatedFieldHelper;
    protected NestedAggregationService $nestedAggregationService;
    protected NestedFilterConfigurationHelper $filterConfigurator;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        LoggerChannelFactoryInterface $loggerFactory,
        CacheBackendInterface $cache,
        NestedFieldHelper $nestedFieldHelper,
        ChildFieldEntityReferenceHelper $childReferenceHelper,
        CalculatedFieldHelper $calculatedFieldHelper,
        NestedAggregationService $nestedAggregationService,
        NestedFilterConfigurationHelper $filterConfigurator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->loggerFactory = $loggerFactory;
        $this->cache = $cache;
        $this->nestedFieldHelper = $nestedFieldHelper;
        $this->childReferenceHelper = $childReferenceHelper;
        $this->calculatedFieldHelper = $calculatedFieldHelper;
        $this->nestedAggregationService = $nestedAggregationService;
        $this->filterConfigurator = $filterConfigurator;
    }

    /**
     * Get enabled fields from configuration.
     */
    public function getEnabledFields(array $child_fld_settings): array {
        return array_filter($child_fld_settings, function($config) {
            return !empty($config['enabled']);
        });
    }

    /**
     * Get enabled fields sorted by weight.
     */
    public function getEnabledAndSortedFields(array $child_fld_settings): array {
        $enabled = $this->getEnabledFields($child_fld_settings);

        uasort($enabled, function($a, $b) {
            return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
        });

        return $enabled;
    }

    public function getDropdownOptions(Index $index, string $sapi_fld_nm, string $child_fld_nm, string $display_mode = 'label'): array {
        $cache_key = $this->getCacheKey($index, $sapi_fld_nm, $child_fld_nm, $display_mode);
        if (1 == 2 && $cached = $this->cache->get($cache_key)) {
            $this->loggerFactory->get('relationship_nodes_search')->debug(
                'Cache hit for dropdown options [@field] with @count items',
                ['@field' => $child_fld_nm, '@count' => count($cached->data ?? [])]
            );
            return $cached->data;
        }
        try {
            $options = $this->fetchOptionsFromIndex($index, $sapi_fld_nm, $child_fld_nm, $display_mode);
            $this->cache->set($cache_key, $options, Cache::PERMANENT, ['relationship_filter_options']);
           
            return $options;
        }
        catch (\Exception $e) {
            $this->loggerFactory->get('relationship_nodes_search')->error(
                'Failed to fetch options for @index:@field: @message',
                ['@index' => $index->id(), '@field' => $child_fld_nm, '@message' => $e->getMessage()]
            );
            return [];
        }
    }

    
    protected function getCacheKey(Index $index, string $sapi_fld_nm, string $child_fld_nm, string $display_mode = 'label', array $extra_context = []): string {
        $parts = [
            'relationship_filter',
            $index->id(),
            str_replace([':', '.', '/'], '_', $sapi_fld_nm),
            str_replace([':', '.', '/'], '_', $child_fld_nm),
            $display_mode,
        ];

        if (!empty($extra_context)) {
            ksort($extra_context);
            $parts[] = md5(json_encode($extra_context));
        }

        return implode(':', $parts);
    }

    protected function fetchOptionsFromIndex(Index $index, string $sapi_fld_nm, string $child_fld_nm, string $display_mode): array {
        $field_id = $sapi_fld_nm . ':' . $child_fld_nm;
        $full_field_path = $this->nestedFieldHelper->colonsToDots($field_id);

        $query = $index->query();
        $query->range(0, 0);
        $query->setOption('search_api_facets', [
            $field_id => [
                'field' => $full_field_path,
                'limit' => 0,
                'operator' => 'or',
                'min_count' => 1,
                'missing' => FALSE,
            ],
        ]);

        $results = $query->execute();
        dpm($results, 'fetch option results');
        $facets = $results->getExtraData('search_api_facets', []);
        if (empty($facets[$field_id])) {
            return [];
        }

        $facet_values = array_column($facets[$field_id], 'filter');
        return $this->convertFacetResultsToOptions($facet_values, $index, $sapi_fld_nm, $child_fld_nm, $display_mode);
    }

    public function convertFacetResultsToOptions(array $results, Index $index, string $sapi_fld_nm, string $child_fld_nm, string $display_mode = 'raw'): array {
        if (empty($results)) {
            return [];
        }
        $results = $this->nestedAggregationService->parseNestedFacetResults($results);
        
        if ($display_mode === 'raw') {
            return array_combine($results, $results);
        }

        $target_type = $this->calculatedFieldHelper->isCalculatedChildField($child_fld_nm) 
            ? $this->calculatedFieldHelper->getCalculatedFieldTargetType($child_fld_nm)
            : $this->childReferenceHelper->getNestedFieldTargetType($index, $sapi_fld_nm, $child_fld_nm);
        if(!in_array($target_type, ['node', 'taxonomy_term'])){
            return [];
        }
        try {
            $int_ids = $this->childReferenceHelper->extractIntIdsFromStringIds($results, $target_type);
            $storage = $this->entityTypeManager->getStorage($target_type);
            $entities = $storage->loadMultiple($int_ids);
            $options = [];
            foreach ($entities as $id => $entity) {
                $options[$target_type .'/' . $id] = $entity->label();
            }
            return $options;
        } catch (\Exception $e) {
             $this->loggerFactory->get('relationship_nodes_search')->error('Failed to load entities: @message', ['@message' => $e->getMessage()]);
            return array_combine($results, $results);
        }
    }

    public function buildExposedFieldWidget(
        array &$form, 
        array $path,
        Index $index,
        string $sapi_fld_nm,
        array $child_fld_settings,
        array $child_fld_values = [],
        bool $expose_operators = false,  
    ): void {
        $child_flds_container = [
            '#type' => 'container',
            '#tree' => true,
            '#attributes' => ['class' => ['relationship-child-field-wrapper']],
        ];

        $this->setFormNestedValue($form, $path, $child_flds_container);

        if(empty($child_fld_settings)){
            return;
        }

        $enabled_fields = $this->getEnabledAndSortedFields($child_fld_settings);
        foreach ($enabled_fields as $child_fld_nm => $child_fld_config) {
            $child_fld_value = $child_fld_values[$child_fld_nm] ?? null;
            $child_path = array_merge($path, [$child_fld_nm]);
            $this->buildChildFieldElement(
                $form, $child_path, $index, $sapi_fld_nm, $child_fld_nm, $child_fld_config, $child_fld_value, $expose_operators
            );
        }
    }

     /**
     * Build exposed field widget in exposed filter.
     */
    protected function buildChildFieldElement(
        array &$form, 
        array $path,
        Index $index,
        string $sapi_fld_nm,
        string $child_fld_nm, 
        array $field_config, 
        ?array $field_value = null,
        bool $expose_operators = false,  
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
            case 'entity_autocomplete':
                $this->addEntityAutocompleteWidget($form, $path, $child_fld_nm, $label, $required, $placeholder, $field_value);
                break;

            case 'select':
                $this->addSelectWidget($form, $path, $index, $sapi_fld_nm, $child_fld_nm, $field_config, $label, $required, $field_value);
                break;

            case 'textfield':
            default:
                $this->addTextfieldWidget($form, $path, $child_fld_nm, $label, $required, $placeholder, $field_value);
                break;
        }
    }

    /**
     * Add operator selector widget.
     */
    protected function addOperatorWidget(array &$form, array $path, array $field_config, ?array $field_value = null): void {
        $path[] = 'operator';
        $operator = [
            '#type' => 'select',
            '#title' => $this->t('Operator'),
            '#options' => $this->filterConfigurator->getOperatorOptions(),
            '#default_value' => $field_value['operator'] ?? $field_config['field_operator'] ?? '=',
            '#attributes' => ['class' => ['relationship-filter-operator']],
        ];
        $this->setFormNestedValue($form, $path, $operator);
    }




    /**
     * Add dropdown select widget.
     */
    protected function addSelectWidget(array &$form, array $path, Index $index, string $sapi_fld_nm, string $child_fld_nm, array $field_config, string $label, bool $required, ?array $field_value = null): void {
        $display_mode = $field_config['select_display_mode'] ?? 'raw';
    
        $options = $this->getDropdownOptions($index, $sapi_fld_nm, $child_fld_nm, $display_mode);
        if (!$required && !empty($options)) {
            $options = ['' => $this->t('- Any -')] + $options;
        }
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
     */
    protected function addTextfieldWidget(array &$form, array $path, string $child_fld_nm, string $label, bool $required, string $placeholder, ?array $field_value = null): void {
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



    protected function setFormNestedValue(array &$form, array $path, $value): void {
        $ref =&$form;
        foreach ($path as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
            $ref[$key] = [];
            }
            $ref =& $ref[$key];
        }
        $ref = $value;
    }

    
    /* // ENTITY AUTOCOMPLETE NOT YET IMPLEMENTED (CF CONFIG HELPER)
    protected function addEntityAutocompleteWidget(array &$form, array $path, string $child_fld_nm, string $label, bool $required, string $placeholder, ?array $field_value = null): void {
        $target_type =  $this->filterConfigurator->getTargetTypeForField($child_fld_nm);
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
    
}