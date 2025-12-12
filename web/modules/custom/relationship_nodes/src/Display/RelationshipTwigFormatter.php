<?php

namespace Drupal\relationship_nodes\Display;

use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\relationship_nodes\RelationField\VirtualFieldManager;
use Drupal\relationship_nodes\Display\Configurator\FormatterConfigurator;
use Drupal\relationship_nodes\Display\RelationshipDataBuilder;

/**
 * Generic service for formatting relationships for Twig rendering.
 * 
 * Provides simple, performant relationship data structures optimized
 * for template rendering without complex nested loops.
 */
class RelationshipTwigFormatter {

  protected RendererInterface $renderer;
  protected VirtualFieldManager $virtualFieldManager;
  protected RelationshipDataBuilder $dataBuilder;
  protected FormatterConfigurator $configurator;

  /**
   * Constructs a RelationshipTwigFormatter object.
   */
  public function __construct(
    RendererInterface $renderer,
    VirtualFieldManager $virtualFieldManager,
    RelationshipDataBuilder $dataBuilder,
    FormatterConfigurator $configurator
  ) {
    $this->renderer = $renderer;
    $this->virtualFieldManager = $virtualFieldManager;
    $this->dataBuilder = $dataBuilder;
    $this->configurator = $configurator;
  }

  /**
   * Get all relation field names for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $bundle_order
   *   Optional custom bundle sort order. Empty array = alphabetical.
   *   Example: ['story', 'person', 'institution']
   *
   * @return array|null
   *   Array of relation field names, or NULL if none.
   */
  public function getAllRelationFields(NodeInterface $node, array $bundle_order = []): ?array {
    $fields = $this->virtualFieldManager->getReferencingRelationshipFields($node);
    
    if (!$fields) {
      return NULL;
    }

    if (empty($bundle_order)) {
      // Alphabetical by related bundle name
      usort($fields, function($a, $b) use ($node) {
        $bundle_a = $this->extractRelatedBundle($a,);
        $bundle_b = $this->extractRelatedBundle($b);
        return strcasecmp($bundle_a, $bundle_b);
      });
    } else {
      // Custom order
      usort($fields, function($a, $b) use ($bundle_order, $node) {
        $bundle_a = $this->extractRelatedBundle($a);
        $bundle_b = $this->extractRelatedBundle($b);
        
        $pos_a = array_search($bundle_a, $bundle_order);
        $pos_b = array_search($bundle_b, $bundle_order);
        
        if ($pos_a === FALSE) $pos_a = 999;
        if ($pos_b === FALSE) $pos_b = 999;
        
        return $pos_a <=> $pos_b;
      });
    }
    
    return $fields;
  }

  /**
   * Get formatted relationships ready for Twig rendering.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The viewing node.
   * @param string $relation_field_name
   *   Field name like 'computed_relationshipfield__person__concept'
   * @param array $options
   *   Options:
   *   - 'default_fields': Array of field names to include 
   *     (default: ['calculated_related_id', 'calculated_relation_type_name'])
   *   - 'format': 'simple' (default, flat strings) or 'rich' (with metadata)
   *   - 'include_links': Whether to render entity references as links (default: TRUE)
   *   - 'extra_fields': Associative array per relation field:
   *       ['computed_relationshipfield__person__person' => ['field_date_start', 'field_municipality']]
   * 
   * @param array $field_settings
   *   Custom field configuration per field. Merges with defaults
   *   Example: [
   *    'calculated_related_id' => [
   *      'display_mode' => 'raw',
   *      'hide_label' => FALSE,
   *    ]
   *   ]
   *
   * @return array|null
   *   Formatted relationship data, or NULL if none.
   *   Structure: [
   *     'title' => 'Concept',  // Auto-generated from bundle
   *     'field_name' => 'computed_relationshipfield__person__concept',
   *     'relation_bundle' => 'relationnode__person_concept',
   *     'items' => [
   *       ['related_id' => 'Name', 'relation_type_name' => 'Parent', '_extra_fields' => [...]],
   *       ...
   *     ]
   *   ]
   */
  public function getFormattedRelationships(
    NodeInterface $node,
    string $relation_field_name,
    array $options = [],
    array $field_settings = []
  ): ?array {
    // Load relation nodes
    $relation_nodes = $this->loadRelationNodes($node, $relation_field_name);
    if (!$relation_nodes) {
      return NULL;
    }

    // Get relation bundle
    $relation_bundle = $this->getRelationBundle($node, $relation_field_name);
    if (!$relation_bundle) {
      return NULL;
    }

    // Build field configurations
    $field_configs = $this->buildFieldConfigurations(
      $relation_bundle,
      $relation_field_name,
      $options,
      $field_settings
    );

    // Build relationship data
    $relationships = $this->dataBuilder->buildRelationshipData($relation_nodes, [
      'field_configs' => $field_configs,
      'viewing_node' => $node,
    ]);

    if (empty($relationships)) {
      return NULL;
    }

    // Simplify for Twig
    $extra_fields = $this->getExtraFields($relation_field_name, $options);
    $items = $this->simplifyForTwig(
      $relationships,
      $options['format'] ?? 'simple',
      $options['include_links'] ?? TRUE,
      $extra_fields
    );

    // Generate title from bundle name
    $title = $this->extractRelatedBundle($relation_field_name);

    return [
      'title' => $title,
      'field_name' => $relation_field_name,
      'relation_bundle' => $relation_bundle,
      'items' => $items,
    ];
  }

  /**
   * Load relation nodes from a field.
   */
  protected function loadRelationNodes(NodeInterface $node, string $field_name): ?array {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    $relation_nodes = [];
    foreach ($node->get($field_name) as $item) {
      if ($relation_node = $item->entity) {
        $relation_nodes[] = $relation_node;
      }
    }

    return $relation_nodes ?: NULL;
  }

  /**
   * Get relation bundle from field definition.
   */
  protected function getRelationBundle(NodeInterface $node, string $field_name): ?string {
    $field_def = $node->get($field_name)->getFieldDefinition();
    $target_bundles = $field_def->getSetting('handler_settings')['target_bundles'] ?? [];
    return reset($target_bundles) ?: NULL;
  }

  /**
   * Build field configurations for relationship data builder.
   */
  protected function buildFieldConfigurations(
    string $relation_bundle,
    string $relation_field_name,
    array $options,
    array $field_settings = []
  ): array {
    $field_names = $this->configurator->getAvailableFieldNames($relation_bundle);
    
    $default_fields = $options['default_fields'] ?? ['calculated_related_id', 'calculated_relation_type_name'];
    $extra_fields = $this->getExtraFields($relation_field_name, $options);
    $fields_to_enable = array_merge($default_fields, $extra_fields);
    
    $field_configs = [];
    foreach ($field_names as $field_name) {
      if (in_array($field_name, $fields_to_enable)) {
         $defaults = [
          'field_name' => $field_name,
          'enabled' => TRUE,
          'display_mode' => 'link',
          'linkable' => TRUE,
          'is_calculated' => str_starts_with($field_name, 'calculated_'),
          'label' => $field_name,
          'weight' => 0,
          'hide_label' => TRUE,
          'multiple_separator' => ', ',
        ];

        $custom = $field_settings[$field_name] ?? [];
        $field_configs[$field_name] = array_merge($defaults, $custom);
      }
    }

    return $field_configs;
  }

  /**
   * Get extra fields for a specific relation field.
   */
  protected function getExtraFields(string $relation_field_name, array $options): array {
    $all_extra = $options['extra_fields'] ?? [];
    return $all_extra[$relation_field_name] ?? [];
  }

  /**
   * Simplify relationship data for Twig rendering.
   */
  protected function simplifyForTwig(
    array $relationships,
    string $format,
    bool $include_links,
    array $extra_fields
  ): array {
    $simple = [];

    foreach ($relationships as $rel) {
      $item = ['_main' => [], '_extra' => []];

      foreach ($rel as $field_name => $field_data) {
        if ($field_name === '_relation_node') {
          continue;
        }

        $first_value = $field_data['field_values'][0] ?? NULL;
        if (!$first_value) {
          continue;
        }

        $clean_name = str_replace('calculated_', '', $field_name);
        $is_extra = !empty($extra_fields) && in_array($field_name, $extra_fields);
        $target_key = $is_extra ? '_extra' : '_main';
        
        if ($format === 'simple') {
          if ($include_links && !empty($first_value['link_url'])) {
            $item[$target_key][$clean_name] = $this->renderLink(
              $first_value['value'],
              $first_value['link_url']
            );
          } else {
            $item[$target_key][$clean_name] = $first_value['value'];
          }
        } else {
          $item[$target_key][$clean_name] = [
            'value' => $first_value['value'],
            'url' => $first_value['link_url'],
          ];
        }
      }

      if (!empty($item['_main'])) {
        $simple[] = array_merge($item['_main'], ['_extra_fields' => $item['_extra']]);
      }
    }

    return $simple;
  }

  /**
   * Render a link using render arrays.
   */
  protected function renderLink(string $title, $url): Markup {
    $link_array = [
      '#type' => 'link',
      '#title' => $title,
      '#url' => $url,
      '#options' => [
        'attributes' => [
          'class' => ['relationship-link'],
        ],
      ],
    ];
    return Markup::create($this->renderer->renderPlain($link_array));
  }

  /**
   * Extract related bundle from field name.
   * 
   * Format: computed_relationshipfield__bundle1__bundle2
   * Returns bundle2 (always the related/target bundle).
   */
  protected function extractRelatedBundle(string $field_name): string {
    $parts = explode('__', $field_name);
    return $parts[2] ?? '';
  }
}