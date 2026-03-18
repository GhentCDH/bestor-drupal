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
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\relationship_nodes\Display\RelationAvailability;

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
  protected LanguageManagerInterface $languageManager;
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
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
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
    LanguageManagerInterface $languageManager,
    CalculatedFieldHelper $calculatedFieldHelper,
    FieldResultParser $parser,
    MirrorProvider $mirrorProvider,
    ForeignKeyResolver $foreignKeyResolver
  ) {
    $this->nodeInfoService = $nodeInfoService;
    $this->fieldNameResolver = $fieldNameResolver;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->calculatedFieldHelper = $calculatedFieldHelper;
    $this->parser = $parser;
    $this->mirrorProvider = $mirrorProvider;
    $this->foreignKeyResolver = $foreignKeyResolver;
  }

   /**
   * Builds relationship data from relation nodes using field configurations.
   *
   * Filters out relations where referenced entities are not published in the
   * requested language before processing. When language_fallback is TRUE,
   * relations unavailable in the requested language may still be included
   * using the first available language instead.
   *
   * @param \Drupal\node\NodeInterface[] $relation_nodes
   *   Array of relation node entities.
   * @param array $settings
   *   Configuration settings:
   *   - 'field_configs': Array of field configurations (REQUIRED)
   *   - 'viewing_node': NodeInterface viewing context for calculated fields (OPTIONAL)
   *   - 'language': Language code to use. Falls back to current language (OPTIONAL)
   *   - 'language_fallback': If TRUE, show relations in a fallback language when
   *     the requested language is unavailable (OPTIONAL, default FALSE)
   *
   * @return array
   *   Array with keys:
   *   - 'items': Array of relationship data, each containing field data and
   *     '_relation_node' reference.
   *   - 'cache': CacheableMetadata object with tags for all referenced entities.
   */
  public function buildRelationshipData(array $relation_nodes, array $settings = []): array {
    $field_configs = $settings['field_configs'] ?? [];
    if (empty($field_configs)) {
      return ['items' => [], 'cache' => new CacheableMetadata()];
    }

    $enabled_configs = array_filter($field_configs, fn($config) => !empty($config['enabled']));
    if (empty($enabled_configs)) {
      return ['items' => [], 'cache' => new CacheableMetadata()];
    }

    $viewing_node = $settings['viewing_node'] ?? NULL;

    // Fall back to current language if not explicitly provided.
    $langcode = $settings['language']
      ?? $this->languageManager->getCurrentLanguage()->getId();

    $cache = new CacheableMetadata();

    // Filter out relations where referenced entities are not published in the
    // requested language. Returns items with 'node' and effective 'langcode'.
    $filtered = $this->filterByPublishedReferences(
      $relation_nodes,
      $langcode,
      $cache,
      $settings['language_fallback'] ?? FALSE
    );

    if (empty($filtered)) {
      return ['items' => [], 'cache' => $cache];
    }

    $data = [];

    foreach ($filtered as $filtered_item) {
      $relation_node = $filtered_item['node'];
      $effective_langcode = $filtered_item['langcode'];

      $cache->addCacheableDependency($relation_node);
      $item = [];

      foreach ($enabled_configs as $field_name => $config) {
        if ($this->calculatedFieldHelper->isCalculatedChildField($field_name)) {
          $field_data = $field_name === 'calculated_relation_type_name'
            ? $this->buildRelationTypeNameData($relation_node, $config, $viewing_node, $effective_langcode)
            : $this->buildCalculatedFieldData($relation_node, $field_name, $config, $viewing_node, $effective_langcode);
        }
        else {
          $field_data = $this->buildRealFieldData($relation_node, $field_name, $config, $effective_langcode);
        }

        if (!empty($field_data)) {
          $item[$field_name] = $field_data;
        }
      }

      if (empty($item)) {
        continue;
      }

      $item['_relation_node'] = $relation_node;
      $data[] = $item;
    }

    return [
      'items' => $data,
      'cache' => $cache,
    ];
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
   * @param ?string $langcode
   *   The language code.
   *
   * @return array|null
   *   Field data array with 'field_values' and 'separator', or NULL if no data.
   */
  protected function buildCalculatedFieldData(
    NodeInterface $relation_node, 
    string $field_name, 
    array $config,
    ?NodeInterface $viewing_node = NULL,
    ?string $langcode = NULL
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
    $values = $this->parser->processEntityReferences($entity_references, $config, $langcode);

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
   * @param ?string $langcode
   *   The language code.
   *
   * @return array|null
   *   Field data array with 'field_values' and 'separator', or NULL if no data.
   */
  protected function buildRealFieldData(
    NodeInterface $relation_node,
    string $field_name,
    array $config,
    ?string $langcode = NULL
  ): ?array {
    // Check if field exists on the relation node
    if (!$relation_node->hasField($field_name)) {
      return NULL;
    }

    if ($langcode && $relation_node->isTranslatable() && $relation_node->hasTranslation($langcode)) {
      $relation_node = $relation_node->getTranslation($langcode);
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
      
      $values = $this->parser->processEntityReferences($entity_references, $config, $langcode);
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
   * @param ?string $langcode
   *   The language code.
   *
   * @return array|null
   *   Field data array with 'field_values' and 'separator', or NULL if no data.
   */
  protected function buildRelationTypeNameData(
    NodeInterface $relation_node,
    array $config,
    ?NodeInterface $viewing_node = NULL,
    ?string $langcode = NULL
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

    // Get translated term if language specified
    if ($langcode && $term->hasTranslation($langcode)) {
      $term = $term->getTranslation($langcode);
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
    ? ($this->mirrorProvider->getMirrorLabelFromTerm($term) ?? $term->getName())
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


  /**
   * Filters relation nodes to those available in the requested language.
   *
   * When language_fallback is TRUE, relations where the requested language is
   * unavailable but other published languages exist will still be included,
   * using the first available language instead. Relations where no published
   * translation exists at all are always removed, regardless of fallback.
   *
   * Cache tags are always collected, even for filtered-out relations, so the
   * page invalidates when a referenced entity gets published or unpublished.
   *
   * @param \Drupal\node\NodeInterface[] $relation_nodes
   *   Relation nodes to filter.
   * @param string $langcode
   *   The requested language code.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cache metadata object to collect tags into.
   * @param bool $language_fallback
   *   If TRUE, include relations in a fallback language when the requested
   *   language is unavailable.
   *
   * @return array
   *   Array of items, each with:
   *   - 'node': The relation NodeInterface.
   *   - 'langcode': The effective language to use (may differ from $langcode
   *     when falling back).
   */
  protected function filterByPublishedReferences(
    array $relation_nodes,
    string $langcode,
    CacheableMetadata $cache,
    bool $language_fallback = FALSE
  ): array {
    $filtered = [];

    foreach ($relation_nodes as $relation_node) {
      $availability = $this->getRelationAvailability($relation_node, $langcode);

      // Always collect cache tags, including for unavailable relations, so the
      // page invalidates when a referenced entity changes publish state.
      $cache->addCacheTags($availability->getCacheTags());

      if ($availability->isAvailable()) {
        $filtered[] = [
          'node' => $relation_node,
          'langcode' => $langcode,
        ];
      }
      elseif ($language_fallback && $availability->isLanguageUnavailable()) {
        // Requested language unavailable, but other languages exist. Use the
        // first available language as fallback.
        $filtered[] = [
          'node' => $relation_node,
          'langcode' => $availability->getAvailableLanguages()[0],
        ];
      }
      // UNAVAILABLE (no published translations at all): always discard.
    }

    return $filtered;
  }


  /**
   * Determines the availability of a relation node in a given language.
   *
   * Checks all entity reference fields on the relation node and computes the
   * intersection of languages in which all referenced entities have a published
   * translation.
   *
   * @param \Drupal\node\NodeInterface $relation_node
   *   The relation node to check.
   * @param string $langcode
   *   The requested language code.
   *
   * @return \Drupal\relationship_nodes\Display\RelationAvailability
   *   Value object describing availability, available languages, and cache tags.
   */
  protected function getRelationAvailability(NodeInterface $relation_node, string $langcode): RelationAvailability {
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Start with NULL so the first entity sets the baseline language list.
    $intersection = NULL;
    $cache_tags = [];

    foreach ($relation_node->getFieldDefinitions() as $field_name => $definition) {
      if ($definition->getType() !== 'entity_reference') continue;
      if ($definition->getSetting('target_type') !== 'node') continue;
      if ($relation_node->get($field_name)->isEmpty()) continue;

      $target_id = $relation_node->get($field_name)->target_id;
      $referenced = $node_storage->load($target_id);

      if (!$referenced) {
        // Referenced entity no longer exists — hard unavailable.
        return new RelationAvailability(RelationAvailability::UNAVAILABLE, [], $cache_tags);
      }

      // Collect cache tags so callers can invalidate on publish/unpublish.
      $cache_tags = array_merge($cache_tags, $referenced->getCacheTags());

      // Build the list of languages with a published translation for this entity.
      $published_langs = [];
      foreach ($referenced->getTranslationLanguages() as $lang => $language) {
        if ($referenced->getTranslation($lang)->isPublished()) {
          $published_langs[] = $lang;
        }
      }

      if (empty($published_langs)) {
        // No published translation in any language — hard unavailable.
        return new RelationAvailability(RelationAvailability::UNAVAILABLE, [], $cache_tags);
      }

      // Narrow the intersection with each referenced entity.
      $intersection = $intersection === NULL
        ? $published_langs
        : array_values(array_intersect($intersection, $published_langs));

      if (empty($intersection)) {
        // Entities exist and are published, but share no common language.
        return new RelationAvailability(RelationAvailability::UNAVAILABLE, [], $cache_tags);
      }
    }

    // No entity reference fields found — nothing to check, treat as available.
    if ($intersection === NULL) {
      return new RelationAvailability(RelationAvailability::AVAILABLE, [], $cache_tags);
    }

    // Check whether the requested language is in the intersection.
    if (in_array($langcode, $intersection)) {
      return new RelationAvailability(RelationAvailability::AVAILABLE, $intersection, $cache_tags);
    }

    // Requested language missing, but other languages are available.
    return new RelationAvailability(RelationAvailability::LANGUAGE_UNAVAILABLE, $intersection, $cache_tags);
  }
}