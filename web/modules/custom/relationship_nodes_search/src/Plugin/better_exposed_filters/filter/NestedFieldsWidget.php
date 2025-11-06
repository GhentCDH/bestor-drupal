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
    $sapi_fld_nm = $this->getSapiFieldName();

    if (empty($index) || empty($sapi_fld_nm)) {
      $form['error'] = [
        '#markup' => $this->t('Cannot load index or field configuration.'),
      ];
      return $form;
    }
    
    $available_fld_nms = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_fld_nm);
    if (empty($available_fld_nms)) {
      $form['info'] = [
        '#markup' => $this->t('No nested fields available. Please configure nested fields in the Search API index.'),
      ];
      return $form;
    }

    $child_fld_settings = $this->getChildFieldSettings();
    $facet_id = $this->handler->realField;
    $this->filterConfigurator->buildNestedWidgetConfigForm($form['advanced'], $available_fld_nms, $child_fld_settings, $facet_id);
    return $form;
  }




  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);
    $index = $this->getIndex();
    $sapi_fld_nm = $this->getSapiFieldName();

    if(empty($index) || empty($sapi_fld_nm)){
      return;
    }
    
    $field_id = $this->getExposedFilterFieldId();
     if (!isset($form[$field_id])) {
      return;
    }

    $form[$field_id] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter by @field', ['@field' => $sapi_fld_nm]),
      '#tree' => true,
      '#attributes' => ['class' => ['relationship-parent-fieldset']],
      'fields' => [
        '#type' => 'container',
        '#tree' => true,
        '#attributes' => ['class' => ['relationship-subfields-wrapper']]
      ]
    ];

    $child_fld_settings = $this->getChildFieldSettings();
    $child_fld_values = is_array($this->value) ? $this->value : [];

     $this->filterWidgetHelper->buildExposedFieldWidget(
      $form, [$field_id, 'fields'], $index, $sapi_fld_nm, $child_fld_settings, $child_fld_values
     );
  }




  protected function getChildFieldSettings():array{
      return $this->configuration['advanced']['filter_field_settings'] ?? [];
  }

  protected function getIndex(): ?Index{
    if(!$this->view instanceof ViewExecutable || !$this->view->getQuery() instanceof SearchApiQuery){
      return $form;;
    }
    $index = $this->view->getQuery()->getIndex();
    return $index instanceof Index ? $index : null;
  }

  protected function getSapiFieldName(): ?string {
    return $this->handler->configuration["search_api_field_identifier"] ?? null;
  }


}
