<?php

namespace Drupal\relationship_nodes\RelationEntity\UserInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;

/**
 * Service for building relationship data structures.
 *
 * Provides consistent data formatting for both field formatters and Views handlers.
 * Converts relation node data into a standardized structure suitable for templates.
 */
class RelationshipDataDisplayBuilder {

  protected RelationNodeInfoService $nodeInfoService;
  protected FieldNameResolver $fieldNameResolver;
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a RelationshipDataBuilder object.
   *
   * @param RelationNodeInfoService $nodeInfoService
   *   The relation node info service.
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    RelationNodeInfoService $nodeInfoService,
    FieldNameResolver $fieldNameResolver,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->nodeInfoService = $nodeInfoService;
    $this->fieldNameResolver = $fieldNameResolver;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Builds relationship data from relation nodes.
   *
   * Converts an array of relation node entities into a standardized data structure
   * suitable for templates. Each relationship contains field data with values and
   * optional link URLs.
   *
   * @param array $relation_nodes
   *   Array of relation node entities.
   * @param array $settings
   *   Configuration settings:
   *   - 'show_relation_type': (bool) Include relation type field
   *   - 'link_entities': (bool) Generate URLs for entity references
   *   - 'separator': (string) Separator for multiple values (default: ', ')
   *
   * @return array
   *   Array of relationship data, each item containing:
   *   - Field data keyed by field name, each with:
   *     * 'field_values': Array of ['value' => string, 'link_url' => Url|NULL]
   *     * 'separator': String separator for multiple values
   *   - 'relation_type': (if enabled) Relation type term data
   *   - '_relation_type_name': (if typed) Term label for grouping
   *   - '_relation_node': Original relation node entity
   *
   * @example
   * $builder->buildRelationshipData($nodes, [
   *   'show_relation_type' => TRUE,
   *   'link_entities' => TRUE,
   *   'separator' => ', ',
   * ]);
   * // Returns:
   * // [
   * //   [
   * //     'rn_related_entity_1' => [
   * //       'field_values' => [
   * //         ['value' => 'Company A', 'link_url' => Url object],
   * //       ],
   * //       'separator' => ', ',
   * //     ],
   * //     'rn_related_entity_2' => [...],
   * //     'relation_type' => [...],
   * //     '_relation_type_name' => 'Partnership',
   * //     '_relation_node' => Node object,
   * //   ],
   * //   ...
   * // ]
   */
  public function buildRelationshipData(array $relation_nodes, array $settings = []): array {
    $data = [];
    $show_type = $settings['show_relation_type'] ?? TRUE;
    $link_entities = $settings['link_entities'] ?? TRUE;
    $separator = $settings['separator'] ?? ', ';
    $relation_type_field = $this->fieldNameResolver->getRelationTypeField();

    foreach ($relation_nodes as $relation_node) {
      $item = [];

      // Get related entities
      $related_entities = $this->nodeInfoService->getRelatedEntityValues($relation_node);

      if (empty($related_entities)) {
        continue;
      }

      // Process each related entity field
      foreach ($related_entities as $field_name => $entity_ids) {
        $values = $this->buildFieldValues($entity_ids, $link_entities);

        if (!empty($values)) {
          $item[$field_name] = [
            'field_values' => $values,
            'separator' => $separator,
          ];
        }
      }

      // Add relation type if configured
      if ($show_type && $relation_type_field && $relation_node->hasField($relation_type_field)) {
        $type_data = $this->buildRelationTypeData($relation_node, $relation_type_field);
        if ($type_data) {
          $item = array_merge($item, $type_data);
        }
      }

      // Keep reference for extensions
      $item['_relation_node'] = $relation_node;

      $data[] = $item;
    }

    return $data;
  }

  /**
   * Builds field values from entity IDs.
   *
   * @param array $entity_ids
   *   Array of entity IDs.
   * @param bool $link_entities
   *   Whether to generate URLs.
   *
   * @return array
   *   Array of value items with 'value' and 'link_url' keys.
   */
  protected function buildFieldValues(array $entity_ids, bool $link_entities): array {
    $values = [];
    $node_storage = $this->entityTypeManager->getStorage('node');

    foreach ($entity_ids as $nid) {
      $node = $node_storage->load($nid);
      if (!$node instanceof NodeInterface) {
        continue;
      }

      $values[] = [
        'value' => $node->label(),
        'link_url' => $link_entities ? $node->toUrl() : NULL,
      ];
    }

    return $values;
  }

  /**
   * Builds relation type data from a relation node.
   *
   * @param NodeInterface $relation_node
   *   The relation node.
   * @param string $relation_type_field
   *   The relation type field name.
   *
   * @return array|null
   *   Relation type data array or NULL if no type found.
   */
  protected function buildRelationTypeData(NodeInterface $relation_node, string $relation_type_field): ?array {
    $term_field = $relation_node->get($relation_type_field);
    
    if ($term_field->isEmpty() || !($term = $term_field->entity)) {
      return NULL;
    }

    return [
      'relation_type' => [
        'field_values' => [[
          'value' => $term->label(),
          'link_url' => NULL,
        ]],
        'separator' => '',
      ],
      '_relation_type_name' => $term->label(),
    ];
  }

  /**
   * Groups relationships by their relation type.
   *
   * Takes the output of buildRelationshipData() and groups items
   * by their '_relation_type_name' value.
   *
   * @param array $relationships
   *   Array of relationship data from buildRelationshipData().
   *
   * @return array
   *   Relationships grouped by type name, keyed by type label.
   *   Each group contains relationships without internal keys
   *   (_relation_type_name, _relation_node).
   *
   * @example
   * $grouped = $builder->groupByRelationType($relationships);
   * // Returns:
   * // [
   * //   'Partnership' => [
   * //     ['rn_related_entity_1' => [...], 'rn_related_entity_2' => [...]],
   * //     [...],
   * //   ],
   * //   'Ownership' => [...],
   * // ]
   */
  public function groupByRelationType(array $relationships): array {
    $grouped = [];

    foreach ($relationships as $relationship) {
      $type_name = $relationship['_relation_type_name'] ?? $this->t('Other');

      // Remove internal keys
      $clean_relationship = $relationship;
      unset($clean_relationship['_relation_type_name']);
      unset($clean_relationship['_relation_node']);

      $grouped[$type_name][] = $clean_relationship;
    }

    return $grouped;
  }

  /**
   * Sorts relationships by a specific field value.
   *
   * @param array $relationships
   *   Array of relationship data.
   * @param string $field_name
   *   Field name to sort by.
   *
   * @return array
   *   Sorted relationships array.
   */
  public function sortByField(array $relationships, string $field_name): array {
    if (empty($field_name) || empty($relationships)) {
      return $relationships;
    }

    usort($relationships, function($a, $b) use ($field_name) {
      if (!isset($a[$field_name]) || !isset($b[$field_name])) {
        return 0;
      }

      $val_a = $a[$field_name]['field_values'][0]['value'] ?? '';
      $val_b = $b[$field_name]['field_values'][0]['value'] ?? '';

      return strcasecmp($val_a, $val_b);
    });

    return $relationships;
  }

  /**
   * Helper for translation.
   */
  protected function t($string, array $args = [], array $options = []) {
    return \Drupal::translation()->translate($string, $args, $options);
  }
}