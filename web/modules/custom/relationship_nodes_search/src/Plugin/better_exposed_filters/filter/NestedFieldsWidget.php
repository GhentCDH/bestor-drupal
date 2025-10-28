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

    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        RelationSearchService $relationSearchService,
        NestedFilterConfigurationHelper $filterConfigurator
    ){
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->relationSearchService = $relationSearchService;
        $this->filterConfigurator = $filterConfigurator;
    }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('relationship_nodes_search.relation_search_service'),
      $container->get('relationship_nodes_search.nested_filter_configuration_helper'),
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
    if(!$this->view instanceof ViewExecutable || !$this->view->getQuery() instanceof SearchApiQuery){
      return $form;;
    }
    $index = $this->view->getQuery()->getIndex();
    $field_name = $this->handler->configuration["search_api_field_identifier"];

    if (!$index instanceof Index || empty($field_name)) {
      $form['error'] = [
        '#markup' => $this->t('Cannot load index or field configuration.'),
      ];
      return $form;
    }
    
    $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $field_name);
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
    // zie erelationshipfitler > er zal van daar naar een gemene klasse (ie exposedwidgethelper) moeten verplaatst worden om code duplicatie te vermijden
    
    dpm($form, 'exposed form alter');
  }

  protected function getFieldSettings():array{
      return $this->configuration['advanced']['filter_field_settings'] ?? [];
  }
}
