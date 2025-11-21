<?php


namespace Drupal\relationship_nodes\RelationEntity\RelationNode;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;


/**
 * Service for fetching information about relation nodes.
 *
 * Provides methods to inspect relation connections between nodes,
 * fetch join fields, referencing relations, and related entity values.
 */
class RelationNodeInfoService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RouteMatchInterface $routeMatch;
  protected FieldNameResolver $fieldNameResolver;
  protected RelationBundleInfoService $bundleInfoService;
  protected RelationBundleSettingsManager $settingsManager;


  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RouteMatchInterface $routeMatch,
    FieldNameResolver $fieldNameResolver,
    RelationBundleInfoService $bundleInfoService,
    RelationBundleSettingsManager $settingsManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->routeMatch = $routeMatch;
    $this->fieldNameResolver = $fieldNameResolver;
    $this->bundleInfoService = $bundleInfoService;
    $this->settingsManager = $settingsManager;
  }


  /**
   * Returns the 'related entity' fields in the relation node that reference a given target node.
   *
   * @param Node $relation_node
   *   The relation node to inspect.
   * @param array $field_names
   *   List of field names to check for references.
   * @param Node|null $target_node
   *   The target node to check connections against.
   *
   * @return array
   *   Array of 'related entity' field names that reference the target node.
   */
  public function getJoinFields(Node $relation_node, array $field_names, ?Node $target_node = NULL): array {
    $result = [];
    $bundle_connections = $this->bundleInfoService->getBundleConnectionInfo($relation_node->getType(), $target_node->getType());
    
    if(empty($bundle_connections['join_fields'])){
      return $result;
    }
    
    $target_id = $target_node->id();

    foreach($field_names as $field){
      if(in_array($field, $bundle_connections['join_fields'])){
        $references = $relation_node->get($field)->getValue();
        foreach($references as $ref){
          if(isset($ref['target_id']) && $ref['target_id'] == $target_id){
            $result[] = $field;
            break;
          }
        }
      }
    }
    return $result;
  }


  /**
   * Returns the connection info between a relation node and a target node.
   * 
   * @param Node $relation_node
   *  The relation node to inspect.
   * @param Node|null $target_node
   *  The target node to check connections against.
   *
   * @return array
   *   Array containing:
   *     - 'relation_state': 'unrelated', 'related', or error message.
   *     - 'join_fields': array of fields that connect nodes (if any).
   *     - 'relation_info': optional additional relation metadata.
   */
  public function getEntityConnectionInfo(Node $relation_node, ?Node $target_node = NULL): array {
    if(empty($target_node)){
      $target_node = $this->routeMatch->getParameter('node');
    }

    if(!$target_node instanceof Node){
      return [];
    }

    $bundle_connections = $this->bundleInfoService->getBundleConnectionInfo($relation_node->getType(), $target_node->getType());
    $result = ['relation_state' => 'unrelated'];

    if(empty($bundle_connections['join_fields'])){
      return $result;
    }

    $connections = $this->getJoinFields($relation_node, $bundle_connections['join_fields'], $target_node) ?? [];

    switch(count($connections)){
      case 0:
        break;
      case 1:
        $result = [
          'relation_state' => 'related',
          'join_fields' => $connections,
          'relation_info' => $bundle_connections['relation_info'] ?? [],
        ];
        break;
      default: 
        $result = [
          'relation_state' => 'Error: duplicate relations',
          'join_fields' => $connections,
        ];
    }

    return $result;
  }


  /**
   * Returns all relation nodes that reference a given target node through a specific relation bundle.
   *
   * @param Node $target_node
   * @param string $relation_bundle
   * @param array $join_fields
   *   Optional: list of 'related entity' fields through which the target node is referenced (in the relation bundle).
   *
   * @return array
   *   Array of referencing relation node objects, keyed by their ID.
   */
  public function getReferencingRelations(Node $target_node, string $relation_bundle, array $join_fields = []): array {
    $target_bundle = $target_node->getType();
    if(empty($join_fields)){
      $connection_info = $this->bundleInfoService->getBundleConnectionInfo($relation_bundle, $target_bundle) ?? [];
      if (empty($connection_info['join_fields'])) {
        return [];
      }
      $join_fields = $connection_info['join_fields'];
    }
    
    $target_id = $target_node->id();
    $node_storage = $this->entityTypeManager->getStorage('node');
    $result = [];
    foreach($join_fields as $join_field){
      $relations = $node_storage->loadByProperties([
        'type' => $relation_bundle,
        $join_field => $target_id,
      ]);
      if(!empty($relations)){
        $result += $relations;
      }
    }
    return $result;
  }


  /**
   * Get a list of all nodes that are related to a given target node (grouped by the relation bundle that connects them).
   *
   * @param Node $target_node
   *
   * @return array
   *  Associative array of associative arrays.
   *  The outer array is keyed by the relation bundle names and has arrays of related nodes as value [node_id => Node,...].
   */
  public function getAllReferencingRelations(Node $target_node): array {
    $result = [];
    $target_bundle_info = $this->bundleInfoService->getRelationInfoForTargetBundle($target_node->getType());    
    
    if(empty($target_bundle_info)){
      return $result;
    }

    foreach($target_bundle_info as $relation_bundle => $relation_info){
      $join_fields = isset($relation_info['join_fields']) ? $relation_info['join_fields'] : [];
      $bundle_result = $this->getReferencingRelations($target_node, $relation_bundle, $join_fields);
      if(!empty($bundle_result)){
        $result[$relation_bundle] = $bundle_result;
      }
    }

    return $result;
  }


  /**
   * Returns the target entity IDs for all related entity fields in a relation node.
   *
   * @param Node $relation_node
   *
   * @return array|null
   *  Associative array of related enity field names => array of target IDs, or NULL if not a relation node type.
   *  E.g. ['related_entity_field_1' => 101, 'related_entity_field_2' => 202]
   */
  public function getRelatedEntityValues(Node $relation_node): ?array {      
    if(!$this->settingsManager->isRelationNodeType($relation_node->getType())){
      return null;
    }

    $result = [];
    foreach($this->fieldNameResolver->getRelatedEntityFields() as $related_entity_field){
      $related_field = $relation_node->get($related_entity_field);
      if(!$related_field instanceof EntityReferenceFieldItemList){
        return null;    
      }
      $relation_references = $this->getFieldListTargetIds($related_field);
      if(empty($relation_references)){
        continue;
      }
      $result[$related_entity_field] = $relation_references;
    }
    return $result;   
  }

  
  /**
 * Extracts target IDs from an entity reference field list.SS
   *
   * @param EntityReferenceFieldItemList $list
   *
   * @return array
   *   Array of target entity IDs.
   */
  public function getFieldListTargetIds(EntityReferenceFieldItemList $list): array{
    $result = []; 
    foreach ($list->getValue() as $item) {
      if (is_array($item) && isset($item['target_id'])) {
          $result[] = (int) $item['target_id'];
      }
    }
    return $result;
  }
}