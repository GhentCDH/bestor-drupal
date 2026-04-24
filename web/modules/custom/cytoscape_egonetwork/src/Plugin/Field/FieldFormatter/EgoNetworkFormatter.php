<?php

namespace Drupal\cytoscape_egonetwork\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\cytoscape_egonetwork\Service\EgoNetworkBuilder;
use Drupal\node\NodeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\relationship_nodes\RelationData\TermHelper\MirrorProvider;

/**
 * Renders the ego network graph when the field value is TRUE.
 *
 * @FieldFormatter(
 *   id = "cytoscape_egonetwork",
 *   label = @Translation("Ego network graph"),
 *   field_types = {"boolean"},
 * )
 */
class EgoNetworkFormatter extends FormatterBase implements ContainerFactoryPluginInterface {
    
  protected EgoNetworkBuilder $networkBuilder;
  protected MirrorProvider $mirrorProvider;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    EgoNetworkBuilder $networkBuilder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->networkBuilder = $networkBuilder;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('cytoscape_egonetwork.ego_network_builder'),
    );
  }

  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty() || !$items->first()->value) {
      return [];
    }

    $node = $items->getEntity();
    if (!$node instanceof NodeInterface) {
      return [];
    }

    $builder = new EgoNetworkBuilder(
      \Drupal::service('entity_type.manager'),
      \Drupal::service('relationship_nodes.relation_info'),
      \Drupal::service('relationship_nodes.field_name_resolver'),
    );

    $graph = $builder->build($node);
    if (empty($graph['elements'])) {
      return [];
    }

    return [
      '#type'       => 'html_tag',
      '#tag'        => 'div',
      '#attributes' => [
        'class' => ['cytoscape-egonetwork'],
        'style' => 'height:500px',
      ],
      '#attached' => [
        'library'        => ['cytoscape_egonetwork/egonetwork'],
        'drupalSettings' => [
          'cytoscapeEgonetwork' => [
            'graph'  => $graph,
            'layout' => 'cose',
          ],
        ],
      ],
    ];
  }

  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'show_ego_network';
  }

}