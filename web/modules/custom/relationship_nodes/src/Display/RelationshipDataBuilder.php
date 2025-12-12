<?php

namespace Drupal\relationship_nodes\Display;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\relationship_nodes\RelationData\NodeHelper\RelationInfo;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationField\CalculatedFieldHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\Display\Parser\FieldResultParser;
use Drupal\relationship_nodes\RelationData\TermHelper\MirrorProvider;
use Drupal\relationship_nodes\RelationData\NodeHelper\ForeignKeyResolver;
use Drupal\taxonomy\TermInterface;

/**
 * Service for building relationship data structures.
 *
 * Provides consistent data formatting for field formatters.
 * Converts relation node data into a standardized structure suitable for templates.
 * 
 * Supports both real fields and calculated fields:
 * - Real fields: Direct field values from relation nodes
 * - Calculated fields: Runtime-resolved values based on viewing context
 *   (e.g., calculated_related_id resolves to "the other entity" in relationship)
 * 
 * Works exclusively with field configurations from FieldConfiguratorBase.
 */
class RelationshipDataBuilder {

  use StringTranslationTrait;

  protected RelationInfo $nodeInfoService;
  protected FieldNameResolver $fieldNameResolver;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected CalculatedFieldHelper $calculatedFieldHelper;
  protected FieldResultParser $parser;
  protected MirrorProvider $mirrorProvider;
  protected ForeignKeyResolver $foreignKeyResolver;
  

  /**
   * Constructs a RelationshipDataBuilder object.
   *
   * @param \Drupal\relationship_nodes\RelationData\NodeHelper\RelationInfo $nodeInfoService
   *   The relation node info service.
   * @param \Drupal\relationship_nodes\RelationField\FieldNameResolver $fieldNameResolver
   *   The field name resolver.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\relationship_nodes\RelationField\CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper.
   * @param \Drupal\relationship_nodes\Display\Parser\FieldResultParser $parser
   *   The formatter parser for entity loading.
   * @param \Drupal\relationship_nodes\RelationData\TermHelperMirrorProvider $mirrorProvider
   *   The mirror term helper service.
   * @param \Drupal\relationship_nodes\RelationData\NodeHelper\ForeignKeyResolver $foreignKeyResolver
   *   The mirror term helper service.
   */
  public function __construct(
    RelationInfo $nodeInfoService,
    FieldNameResolver $fieldNameResolver,
    EntityTypeManagerInterface $entityTypeManager,
    CalculatedFieldHelper $calculatedFieldHelper,
    FieldResultParser $parser,
    MirrorProvider $mirrorProvider,
    ForeignKeyResolver $foreignKeyResolver
  ) {
    $this->nodeInfoService = $nodeInfoService;
    $this->fieldNameResolver = $fieldNameResolver;
    $this->entityTypeManager = $entityTypeManager;
    $this->calculatedFieldHelper = $calculatedFieldHelper;
    $this->parser = $parser;
    $this->mirrorProvider = $mirrorProvider;
    $this->foreignKeyResolver = $foreignKeyResolver;
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
   */
  public function buildRelationshipData(array $relation_nodes, array $settings = []): array {
    $field_configs = $settings['field_configs'] ?? [];
    $viewing_node = $settings['viewing_node'] ?? NULL;
    
    if (empty($field_configs)) {
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
          // Special case: relation type name is calculated (mirrored) text, not entity reference
          $field_data = $field_name === 'calculated_relation_type_name'
            ? $this->buildRelationTypeNameData($relation_node, $config, $viewing_node)
            : $this->buildCalculatedFieldData($relation_node, $field_name, $config, $viewing_node);
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
    // Get all related entity IDs from the relation node
    $related_entities = $this->nodeInfoService->getRelatedEntityValues($relation_node);
    
    if (empty($related_entities)) {
      return NULL;
    }

    // Determine which entities to show based on viewing context
    $entity_ids = [];
    
    if ($viewing_node) {
      // Get FK field that contains the viewing node
      $viewing_fk = $this->foreignKeyResolver->getEntityForeignKeyField($relation_node, $viewing_node);
      
      // Show entities from the "other" FK field only
      foreach ($related_entities as $field => $ids) {
        if ($field !== $viewing_fk) {
          $entity_ids = array_merge($entity_ids, $ids);
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

    // Get target entity type for calculated field
    $target_type = $this->calculatedFieldHelper->getCalculatedFieldTargetType($field_name);
    if (empty($target_type)) {
      return NULL;
    }

    // Build entity references for parser
    $entity_references = array_map(function($id) use ($target_type) {
      return [
        'entity_type' => $target_type,
        'entity_id' => $id,
      ];
    }, $entity_ids);

    // Use parser to resolve labels/links
    $values = $this->parser->processEntityReferences($entity_references, $config);

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
    // Check if field exists on the relation node
    if (!$relation_node->hasField($field_name)) {
      return NULL;
    }

    $field = $relation_node->get($field_name);
    
    if ($field->isEmpty()) {
      return NULL;
    }

    // For entity references, use parser
    if ($field->getFieldDefinition()->getType() === 'entity_reference') {
      $entity_references = $this->parser->extractEntityReferencesFromField($relation_node, $field_name);
      
      if (empty($entity_references)) {
        return NULL;
      }
      
      $values = $this->parser->processEntityReferences($entity_references, $config);
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
   * Builds data for the calculated relation type name field.
   * 
   * Returns the relation type name with mirror support based on viewing context.
   *
   * @param \Drupal\node\NodeInterface $relation_node
   *   The relation node.
   * @param array $config
   *   Field configuration.
   * @param \Drupal\node\NodeInterface|null $viewing_node
   *   Optional viewing context node.
   *
   * @return array|null
   *   Field data array with 'field_values' and 'separator', or NULL if no data.
   */
  protected function buildRelationTypeNameData(
    NodeInterface $relation_node,
    array $config,
    ?NodeInterface $viewing_node = NULL
  ): ?array {
    // Get relation type term
    $relation_type_field = $this->fieldNameResolver->getRelationTypeField();
    
    if (!$relation_node->hasField($relation_type_field)) {
      return NULL;
    }
    
    $term_values = $relation_node->get($relation_type_field)->getValue();
    if (empty($term_values)) {
      return NULL;
    }
    
    $term_id = $term_values[0]['target_id'] ?? NULL;
    if (!$term_id) {
      return NULL;
    }
    
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($term_id);

    if(!$term instanceof TermInterface) {
      return NULL;
    }

    // Check if we need mirror label
    $use_mirror = FALSE;

    if ($viewing_node) {
      $fk_field = $this->foreignKeyResolver->getEntityForeignKeyField($relation_node, $viewing_node);
      $fk2_field = $this->fieldNameResolver->getRelatedEntityFields(2);
      $use_mirror = ($fk_field === $fk2_field);
    }
    // Get appropriate label
    $label = $use_mirror 
    ? $this->mirrorProvider->getTermMirrorLabel($term)
    : $term->getName();

    return [
      'field_values' => [[
        'value' => $label,
        'link_url' => NULL,
      ]],
      'separator' => $config['multiple_separator'] ?? ', ',
    ];
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