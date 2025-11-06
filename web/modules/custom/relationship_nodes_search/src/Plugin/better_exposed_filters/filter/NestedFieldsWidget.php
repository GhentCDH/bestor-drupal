<?php

namespace Drupal\relationship_nodes_search\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\facets\FacetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relationship_nodes_search\Service\NestedFilterConfigurationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\facets_exposed_filters\Plugin\views\filter\FacetsFilter;
use Drupal\views\ViewExecutable;
use Drupal\search_api\Plugin\views\query\SearchApiQuery; 
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes_search\Service\RelationSearchService;
use Drupal\relationship_nodes_search\Service\NestedFilterExposedWidgetHelper;

/**
 * @BetterExposedFiltersFilterWidget(
 *   id = "nested_fields_widget",
 *   label = @Translation("Nested fields dropdown"),
 *   description = @Translation("Renders nested field facets as dropdowns")
 * )
 */
class NestedFieldsWidget extends FilterWidgetBase implements ContainerFactoryPluginInterface{

  protected RelationSearchService $relationSearchService;
  protected NestedFilterConfigurationHelper $filterConfigurator;
  protected NestedFilterExposedWidgetHelper $filterWidgetHelper;

    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        RelationSearchService $relationSearchService,
        NestedFilterConfigurationHelper $filterConfigurator,
        NestedFilterExposedWidgetHelper $filterWidgetHelper
    ){
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->relationSearchService = $relationSearchService;
        $this->filterConfigurator = $filterConfigurator;
        $this->filterWidgetHelper = $filterWidgetHelper;
    }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('relationship_nodes_search.relation_search_service'),
      $container->get('relationship_nodes_search.nested_filter_configuration_helper'),
      $container->get('relationship_nodes_search.nested_filter_exposed_widget_helper'),
    );
  }

  public static function isApplicable(mixed $filter = NULL, array $filter_options = []): bool {
    if ($filter instanceof FacetsFilter && !empty($filter_options['facet']['processor_configs']['nested_build_processor'])) {
      return true;
    }
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['advanced'][] = 'filter_field_settings';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $index = $this->getIndex();
    $sapi_field = $this->getSapiField();
  

    if (empty($index) || empty($sapi_field)) {
      $form['error'] = [
        '#markup' => $this->t('Cannot load index or field configuration.'),
      ];
      return $form;
    }
    
    $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_field);
    if (empty($available_fields)) {
      $form['info'] = [
        '#markup' => $this->t('No nested fields available. Please configure nested fields in the Search API index.'),
      ];
      return $form;
    }

    $filter_field_settings = $this->getFieldSettings();
    $this->filterConfigurator->buildNestedWidgetConfigForm($form['advanced'], $available_fields, $filter_field_settings, true);
    return $form;
  }

  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);
    $index = $this->getIndex();
    $sapi_field = $this->getSapiField();
    if(empty($index) || empty($sapi_field)){
      return;
    }
    // zie erelationshipfitler > er zal van daar naar een gemene klasse (ie exposedwidgethelper) moeten verplaatst worden om code duplicatie te vermijden
    $field_id = $this->getExposedFilterFieldId();
     if (isset($form[$field_id])) {
      $form[$field_id]['#type'] = 'container';
      $form[$field_id]['#tree'] = TRUE;
    }

    $field_settings = $this->getFieldSettings();
    $enabled_fields = $this->filterWidgetHelper->getEnabledAndSortedFields($field_settings);    
    foreach ($enabled_fields as $child_field => $field_config) {
        $field_value = $this->value[$child_field] ?? null;
        $this->filterWidgetHelper->buildExposedFieldWidget($form, $index, $sapi_field, $child_field, $field_config, $field_value);
    }

    dpm($form, 'form in exposed form alter of nested fields widget');
  }

  protected function getFieldSettings():array{
      return $this->configuration['advanced']['filter_field_settings'] ?? [];
  }

  protected function getIndex(): ?Index{
    if(!$this->view instanceof ViewExecutable || !$this->view->getQuery() instanceof SearchApiQuery){
      return $form;;
    }
    $index = $this->view->getQuery()->getIndex();
    return $index instanceof Index ? $index : null;
  }

  protected function getSapiField(): ?string {
    return $this->handler->configuration["search_api_field_identifier"] ?? null;
  }


}
