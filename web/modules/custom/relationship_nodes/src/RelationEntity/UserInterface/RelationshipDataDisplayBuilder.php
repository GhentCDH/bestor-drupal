<?php

namespace Drupal\relationship_nodes\RelationEntity\UserInterface;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationField\CalculatedFieldHelper;

/**
 * Service for building relationship data structures.
 *
 * Provides consistent data formatting for both field formatters and Views handlers.
 * Converts relation node data into a standardized structure suitable for templates.
 * 
 * Supports both real fields and calculated fields:
 * - Real fields: Direct field values from relation nodes
 * - Calculated fields: Runtime-resolved values based on viewing context
 *   (e.g., calculated_related_id resolves to "the other entity" in relationship)
 * 
 * Works exclusively with field configurations from NestedFieldConfiguratorBase.
 */
class RelationshipDataDisplayBuilder {

  use StringTranslationTrait;

  protected RelationNodeInfoService $nodeInfoService;
  protected FieldNameResolver $fieldNameResolver;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected CalculatedFieldHelper $calculatedFieldHelper;

  /**
   * Constructs a RelationshipDataDisplayBuilder object.
   *
   * @param \Drupal\relationship_nodes\RelationEntity\RelationNode\RelationNodeInfoService $nodeInfoService
   *   The relation node info service.
   * @param \Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver $fieldNameResolver
   *   The field name resolver.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\relationship_nodes\FieldHelper\CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper.
   */
  public function __construct(
    RelationNodeInfoService $nodeInfoService,
    FieldNameResolver $fieldNameResolver,
    EntityTypeManagerInterface $entityTypeManager,
    CalculatedFieldHelper $calculatedFieldHelper
  ) {
    $this->nodeInfoService = $nodeInfoService;
    $this->fieldNameResolver = $fieldNameResolver;
    $this->entityTypeManager = $entityTypeManager;
    $this->calculatedFieldHelper = $calculatedFieldHelper;
  }

  /**
   * Builds relationship data from relation nodes using field configurations.
   * 
   * Processes relation nodes and extracts configured fields, supporting both:
   * - Real fields: Direct values from the relation node entity
   * - Calculated fields: Values resolved at runtime based on viewing context
   * 
   * The viewing_node parameter determines perspective for calculated fields.
   * If not provided, calculated fields will show all related entities.
   *
   * @param \Drupal\node\NodeInterface[] $relation_nodes
   *   Array of relation node entities.
   * @param array $settings
   *   Configuration settings:
   *   - 'field_configs': Array of field configurations from configurator (REQUIRED)
   *   - 'viewing_node': NodeInterface viewing context for calculated fields (OPTIONAL)
   *
   * @return array
   *   Array of relationship data, each item containing:
   *   - Field data keyed by field name with 'field_values' and 'separator'
   *   - '_relation_node': Original relation node entity
   *   
   *   Example structure:
   *   [
   *     [
   *       'calculated_related_id' => [
   *         'field_values' => [
   *           ['value' => 'Company B', 'link_url' => Url object]
   *         ],
   *         'separator' => ', '
   *       ],
   *       'field_notes' => [
   *         'field_values' => [
   *           ['value' => 'Partnership since 2020', 'link_url' => NULL]
   *         ],
   *         'separator' => ', '
   *       ],
   *       '_relation_node' => Node object
   *     ]
   *   ]
   */
  public function buildRelationshipData(array $relation_nodes, array $settings = []): array {
    $field_configs = $settings['field_configs'] ?? [];
    $viewing_node = $settings['viewing_node'] ?? NULL;
    
    if (empty($field_configs)) {
      // No configurations provided - cannot build data
      return [];
    }

    $data = [];
    
    // Filter to only enabled fields
    $enabled_configs = array_filter($field_configs, fn($config) => !empty($config['enabled']));

    if (empty($enabled_configs)) {
      return [];
    }

    foreach ($relation_nodes as $relation_node) {
      if (!$relation_node instanceof NodeInterface) {
        continue;
      }

      $item = [];

      // Process each enabled field
      foreach ($enabled_configs as $field_name => $config) {
        // Check if this is a calculated field
        if ($this->calculatedFieldHelper->isCalculatedChildField($field_name)) {
          $field_data = $this->buildCalculatedFieldData($relation_node, $field_name, $config, $viewing_node);
        } else {
          $field_data = $this->buildRealFieldData($relation_node, $field_name, $config);
        }

        if (!empty($field_data)) {
          $item[$field_name] = $field_data;
        }
      }

      if (empty($item)) {
        continue;
      }

      // Keep reference for extensions
      $item['_relation_node'] = $relation_node;

      $data[] = $item;
    }

    return $data;
  }

  /**
   * Builds data for a calculated field.
   * 
   * Calculated fields are resolved at runtime based on viewing context:
   * - calculated_related_id: Shows "the other entity" in the relationship
   * - calculated_relation_type_name: Shows relation type from viewer's perspective
   * 
   * If no viewing context is provided, shows all related entities.
   *
   * @param \Drupal\node\NodeInterface $relation_node
   *   The relation node.
   * @param string $field_name
   *   The calculated field name (e.g., 'calculated_related_id').
   * @param array $config
   *   Field configuration.
   * @param \Drupal\node\NodeInterface|null $viewing_node
   *   Optional viewing context node.
   *
   * @return array|null
   *   Field data array with 'field_values' and 'separator', or NULL if no data.
   */
  protected function buildCalculatedFieldData(
    NodeInterface $relation_node, 
    string $field_name, 
    array $config,
    ?NodeInterface $viewing_node = NULL
  ): ?array {


    dpm($relation_node, 'input build calc field data');
    dpm($field_name);
    dpm($config);
    dpm($viewing_node);






    // Get all related entity IDs from the relation node
    $related_entities = $this->nodeInfoService->getRelatedEntityValues($relation_node);
    
    if (empty($related_entities)) {
      return NULL;
    }

    // Determine which entities to show based on viewing context
    $entity_ids = [];
    
    if ($viewing_node) {
      // Show only "the other" entity (not the viewing entity)
      $viewing_id = $viewing_node->id();
      foreach ($related_entities as $field => $ids) {
        foreach ($ids as $id) {
          if ($id != $viewing_id) {
            $entity_ids[] = $id;
          }
        }
      }
    } else {
      // No viewing context - show all related entities
      foreach ($related_entities as $field => $ids) {
        $entity_ids = array_merge($entity_ids, $ids);
      }
    }

    if (empty($entity_ids)) {
      return NULL;
    }

    // Build field values using standard formatter
    $values = $this->buildFieldValues($entity_ids, $config);

    if (empty($values)) {
      return NULL;
    }

    return [
      'field_values' => $values,
      'separator' => $config['multiple_separator'] ?? ', ',
    ];
  }

  /**
   * Builds data for a real (non-calculated) field.
   * 
   * Extracts field values directly from the relation node entity.
   * Supports both entity reference fields and other field types.
   *
   * @param \Drupal\node\NodeInterface $relation_node
   *   The relation node.
   * @param string $field_name
   *   The real field name (e.g., 'field_notes').
   * @param array $config
   *   Field configuration.
   *
   * @return array|null
   *   Field data array with 'field_values' and 'separator', or NULL if no data.
   */
  protected function buildRealFieldData(
    NodeInterface $relation_node,
    string $field_name,
    array $config
  ): ?array {


    dpm($relation_node, 'input Build realfield data');
    dpm($field_name);
    dpm($config);






    // Check if field exists on the relation node
    if (!$relation_node->hasField($field_name)) {
      return NULL;
    }

    $field = $relation_node->get($field_name);
    
    if ($field->isEmpty()) {
      return NULL;
    }

    // For entity references, extract target IDs and use standard formatter
    if ($field->getFieldDefinition()->getType() === 'entity_reference') {
      $entity_ids = [];
      foreach ($field->getValue() as $item) {
        if (isset($item['target_id'])) {
          $entity_ids[] = $item['target_id'];
        }
      }
      
      if (empty($entity_ids)) {
        return NULL;
      }
      
      $values = $this->buildFieldValues($entity_ids, $config);
    } else {
      // For non-entity-reference fields, extract raw values
      $values = [];
      foreach ($field->getValue() as $item) {
        $value = $item['value'] ?? reset($item);
        if ($value !== NULL && $value !== '') {
          $values[] = [
            'value' => (string) $value,
            'link_url' => NULL,
          ];
        }
      }
    }

    if (empty($values)) {
      return NULL;
    }

    return [
      'field_values' => $values,
      'separator' => $config['multiple_separator'] ?? ', ',
    ];
  }

  /**
   * Builds field values from entity IDs using field configuration.
   *
   * Uses the field configuration to determine display mode (raw/label/link).
   * 
   * This method is shared by both calculated and real entity reference fields.
   *
   * @param array $entity_ids
   *   Array of entity IDs.
   * @param array $config
   *   Field configuration with display_mode and linkable properties.
   *
   * @return array
   *   Array of value items with 'value' and 'link_url' keys.
   */
  protected function buildFieldValues(array $entity_ids, array $config): array {
    $values = [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    $display_mode = $config['display_mode'] ?? 'label';
    $link_entities = !empty($config['linkable']) && $display_mode === 'link';

    foreach ($entity_ids as $nid) {
      $node = $node_storage->load($nid);
      if (!$node instanceof NodeInterface) {
        continue;
      }

      // Determine value based on display mode
      $value = match($display_mode) {
        'raw' => (string) $nid,
        'link', 'label' => $node->label(),
        default => $node->label(),
      };

      $values[] = [
        'value' => $value,
        'link_url' => $link_entities ? $node->toUrl() : NULL,
      ];
    }

    return $values;
  }

  /**
   * Groups relationships by a specific field value.
   * 
   * Takes the first value of the specified field from each relationship
   * and uses it as the grouping key.
   * 
   * Useful for organizing relationships by type, category, or status.
   *
   * @param array $relationships
   *   Array of relationship data from buildRelationshipData().
   * @param string $field_name
   *   Field name to group by (can be calculated or real field).
   *
   * @return array
   *   Relationships grouped by field value, keyed by the field's first value.
   *   Example: ['Partnership' => [...], 'Sponsorship' => [...]]
   */
  public function groupByField(array $relationships, string $field_name): array {
    if (empty($field_name) || empty($relationships)) {
      return [];
    }

    $grouped = [];

    foreach ($relationships as $relationship) {
      if (!isset($relationship[$field_name])) {
        continue;
      }

      $group_key = $relationship[$field_name]['field_values'][0]['value'] ?? 'ungrouped';

      if (!isset($grouped[$group_key])) {
        $grouped[$group_key] = [];
      }

      // Remove internal keys before adding
      $clean_relationship = $relationship;
      unset($clean_relationship['_relation_node']);

      $grouped[$group_key][] = $clean_relationship;
    }

    return $grouped;
  }

  /**
   * Sorts relationships by a specific field value.
   * 
   * Uses case-insensitive alphabetical sorting based on the first value
   * of the specified field.
   *
   * @param array $relationships
   *   Array of relationship data from buildRelationshipData().
   * @param string $field_name
   *   Field name to sort by (can be calculated or real field).
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
}