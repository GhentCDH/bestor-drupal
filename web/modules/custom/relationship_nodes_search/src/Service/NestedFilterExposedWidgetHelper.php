<?php

namespace Drupal\relationship_nodes_search\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relationship_nodes_search\Service\NestedFilterConfigurationHelper;


class NestedFilterExposedWidgetHelper {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;
    protected LoggerChannelFactoryInterface $loggerFactory;
    protected CacheBackendInterface $cache;
    protected RelationSearchService $relationSearchService;
    protected NestedAggregationService $nestedAggregationService;
    protected NestedFilterConfigurationHelper $filterConfigurator;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        LoggerChannelFactoryInterface $loggerFactory,
        CacheBackendInterface $cache,
        RelationSearchService $relationSearchService,
        NestedAggregationService $nestedAggregationService,
        NestedFilterConfigurationHelper $filterConfigurator
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->loggerFactory = $loggerFactory;
        $this->cache = $cache;
        $this->relationSearchService = $relationSearchService;
        $this->nestedAggregationService = $nestedAggregationService;
        $this->filterConfigurator = $filterConfigurator;
    }

    /**
     * Get enabled fields from configuration.
     */
    public function getEnabledFields(array $filter_field_settings): array {
        return array_filter($filter_field_settings, function($config) {
            return !empty($config['enabled']);
        });
    }

    /**
     * Get enabled fields sorted by weight.
     */
    public function getEnabledAndSortedFields(array $filter_field_settings): array {
        $enabled = $this->getEnabledFields($filter_field_settings);

        uasort($enabled, function($a, $b) {
            return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
        });

        return $enabled;
    }

    public function getDropdownOptions(Index $index, string $parent_field, string $child_field, string $display_mode = 'label'): array {
        $cache_key = $this->getCacheKey($index, $parent_field, $child_field, $display_mode);
        if (1 == 2 && $cached = $this->cache->get($cache_key)) {
            $this->loggerFactory->get('relationship_nodes_search')->debug(
                'Cache hit for dropdown options [@field] with @count items',
                ['@field' => $child_field, '@count' => count($cached->data ?? [])]
            );
            return $cached->data;
        }
        try {
            $options = $this->fetchOptionsFromIndex($index, $parent_field, $child_field, $display_mode);
            $this->cache->set($cache_key, $options, Cache::PERMANENT, ['relationship_filter_options']);
           
            return $options;
        }
        catch (\Exception $e) {
            $this->loggerFactory->get('relationship_nodes_search')->error(
                'Failed to fetch options for @index:@field: @message',
                ['@index' => $index->id(), '@field' => $child_field, '@message' => $e->getMessage()]
            );
            return [];
        }
    }

    
    protected function getCacheKey(Index $index, string $parent_field, string $child_field, string $display_mode = 'label', array $extra_context = []): string {
        $parts = [
            'relationship_filter',
            $index->id(),
            str_replace([':', '.', '/'], '_', $parent_field),
            str_replace([':', '.', '/'], '_', $child_field),
            $display_mode,
        ];

        if (!empty($extra_context)) {
            ksort($extra_context);
            $parts[] = md5(json_encode($extra_context));
        }

        return implode(':', $parts);
    }

    protected function fetchOptionsFromIndex(Index $index, string $parent_field, string $child_field, string $display_mode): array {
        $field_id = $parent_field . ':' . $child_field;
        $full_field_path = $this->relationSearchService->colonsToDots($field_id);

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
        $facets = $results->getExtraData('search_api_facets', []);
        if (empty($facets[$field_id])) {
            return [];
        }

        $facet_values = array_column($facets[$field_id], 'filter');
        return $this->convertFacetResultsToOptions($facet_values, $index, $parent_field, $child_field, $display_mode);
    }

    public function convertFacetResultsToOptions(array $results, Index $index, string $parent_field, string $child_field, string $display_mode = 'raw'): array {
        if (empty($results)) {
            return [];
        }
        $results = $this->nestedAggregationService->cleanFacetResults($results);
        
        if ($display_mode === 'raw') {
            return array_combine($results, $results);
        }

        $target_type = $this->relationSearchService->isCalculatedChildField($child_field) 
            ? $this->relationSearchService->getCalculatedFieldTargetType($child_field)
            : $this->relationSearchService->getNestedFieldTargetType($index, $parent_field, $child_field);
        
        if(!in_array($target_type, ['node', 'taxonomy_term'])){
            return [];
        }

        try {
            $int_ids = $this->relationSearchService->extractIntIdsFromStringIds($results, $target_type);
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

     /**
     * Build exposed field widget in exposed filter.
     */
    public function buildExposedFieldWidget(
        array &$form, 
        array $path,
        Index $index,
        string $parent_field_name,
        string $child_field_name, 
        array $field_config, 
        ?array $field_value = null,
        bool $expose_operators = false,  
    ): void {
        $widget_type = $field_config['widget'] ?? 'textfield';
        $label = $field_config['label'] ?? $this->relationSearchService->formatCalculatedFieldLabel($child_field_name);
        $required = !empty($field_config['required']);
        $placeholder = $field_config['placeholder'] ?? '';
        $expose_field_operator = !empty($field_config['expose_field_operator']);

        $form['value'][$child_field_name] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['relationship-filter-field-wrapper']],
        ];

        if ($expose_operators && $expose_field_operator) {
            $this->addOperatorWidget($form, $child_field_name, $field_config, $field_value);
        }

        switch ($widget_type) {
            case 'entity_autocomplete':
                $this->addEntityAutocompleteWidget($form, $child_field_name, $label, $required, $placeholder);
                break;

            case 'select':
                $this->addSelectWidget($form, $index, $parent_field_name, $child_field_name, $field_config, $label, $required, $field_value);
                break;

            case 'textfield':
            default:
                $this->addTextfieldWidget($form, $child_field_name, $label, $required, $placeholder, $field_value);
                break;
        }
    }

    /**
     * Add operator selector widget.
     */
    protected function addOperatorWidget(array &$form, string $field_name, array $field_config, ?array $field_value = null): void {
        $form['value'][$field_name]['operator'] = [
            '#type' => 'select',
            '#title' => $this->t('Operator'),
            '#options' => $this->filterConfigurator->getOperatorOptions(),
            '#default_value' => $field_value['operator'] ?? $field_config['field_operator'] ?? '=',
            '#attributes' => ['class' => ['relationship-filter-operator']],
        ];
    }

    /**
     * Add entity autocomplete widget.
     */
    protected function addEntityAutocompleteWidget(array &$form, string $field_name, string $label, bool $required, string $placeholder): void {
        $target_type =  $this->filterConfigurator->getTargetTypeForField($field_name);
        $default_entity = $this->getDefaultEntityValue($field_name, $target_type);
        
        $form['value'][$field_name]['value'] = [
            '#type' => 'entity_autocomplete',
            '#title' => $label,
            '#target_type' => $target_type,
            '#default_value' => $default_entity,
            '#required' => $required,
            '#placeholder' => $placeholder,
        ];
    }


    /**
     * Add dropdown select widget.
     */
    protected function addSelectWidget(array &$form, Index $index, string $sapi_field, string $field_name, array $field_config, string $label, bool $required, ?array $field_value = null): void {
        $display_mode = $field_config['select_display_mode'] ?? 'raw';
    
        $options = $this->getDropdownOptions($index, $sapi_field, $field_name, $display_mode);
        if (!$required && !empty($options)) {
            $options = ['' => $this->t('- Any -')] + $options;
        }
        
        $form['value'][$field_name]['value'] = [
            '#type' => 'select',
            '#title' => $label,
            '#options' => $options,
            '#default_value' => $field_value['value'] ?? $this->value[$field_name] ?? '',
            '#required' => $required,
            '#empty_option' => $required ? NULL : $this->t('- Any -'),
        ];
    }


    /**
     * Add textfield widget.
     */
    protected function addTextfieldWidget(array &$form, string $field_name, string $label, bool $required, string $placeholder, ?array $field_value = null): void {
        $form['value'][$field_name]['value'] = [
            '#type' => 'textfield',
            '#title' => $label,
            '#default_value' => $field_value['value'] ?? $this->value[$field_name] ?? '',
            '#required' => $required,
            '#placeholder' => $placeholder,
        ];
    }


    /**
     * Get default entity value for autocomplete field.
     */
    protected function getDefaultEntityValue(string $field_name, string $target_type) {
        $value = $this->value[$field_name]['value'] ?? $this->value[$field_name] ?? NULL;
        
        if (empty($value) || !is_numeric($value)) {
            return NULL;
        }

        try {
            return $this->entityTypeManager->getStorage($target_type)->load($value);
        } catch (\Exception $e) {
            return NULL;
        }
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
}