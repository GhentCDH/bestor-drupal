<?php

namespace Drupal\relationship_nodes_search\Plugin\search_api\processor;


use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\search_api\Processor\EntityProcessorProperty;
use Drupal\relationship_nodes_search\TypedData\RelationInfoData;
use Drupal\search_api\Utility\Utility;

use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds nested relationship data to specified fields.
 *
 * @SearchApiProcessor(
 *   id = "relationship_indexer",
 *   label = @Translation("Relationship Indexer"),
 *   description = @Translation("Nests relationship data into specified fields."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = false,
 *   hidden = false,
 * )
 */

class RelationshipIndexer extends ProcessorPluginBase  implements ContainerFactoryPluginInterface {

  /**
   * The relationship info service.
   *
   * @var \Drupal\relationship_nodes\RelationshipInfoService
   */
  protected $infoService;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RelationshipIndexer object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RelationshipInfoService $infoService, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->infoService = $infoService;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('relationship_nodes.relationship_info_service'),
      $container->get('entity_type.manager')
    );
  }


  /**
   * {@inheritdoc}
   */
    public static function supportsIndex(\Drupal\search_api\IndexInterface $index) {
    // Check if the index has entity datasources.
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId()) {
        return TRUE;
      }
    }
    return FALSE;
  }



  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource || !$datasource->getEntityTypeId()) {
      return $properties;
    }

    $node_types_in_index = $datasource->getConfiguration()['bundles']['selected']  ?? [];
    $relationship_node_types = [];
    foreach($node_types_in_index as $node_type_in_index){
      $related_relationships = $this->infoService->getRelationInfoForTargetBundle($node_type_in_index);
      foreach($related_relationships as $related_relationship){
        if(!in_array($related_relationship['relationship_bundle'], $relationship_node_types)){
          $relationship_node_types[] = $related_relationship['relationship_bundle'];
        }
      }
    }

    foreach($relationship_node_types as $relationship_node_type){
      $definition = [
        'label' => $this->t('Related nodes of type @type', ['@type' => $relationship_node_type]),
        'description' => $this->t('All related @type nodes, with selectable fields.', ['@type' => $relationship_node_type]),
        'type' => 'relationship_info', 
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
        'definition_class_settings' => [
          'bundle' => $relationship_node_type,
        ],
      ];
      
      $property = new \Drupal\relationship_nodes_search\Processor\RelationInfoProcessorProperty($definition);
      $properties["relationship_info__{$relationship_node_type}"] = $property;

    }
    return $properties;
  }


  /**
   * Cf Partially based on code of ReverseEntityReferences
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return;
    }

    $item_relation_info_list = $this->infoService->getRelationInfoForTargetBundle($entity->getType());

    if (!($entity instanceof \Drupal\Core\Entity\EntityInterface) || empty($item_relation_info_list)) {
      return;
    }

    $prefix = 'relationship_info__';
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    foreach ($item->getFields() as $field) {
      $relation_nodetype_name = $field->getPropertyPath();     
      
      if ($field->getDatasourceId() != $item->getDatasourceId() || !str_starts_with( $relation_nodetype_name, $prefix) || !isset($field->getConfiguration()['nested_fields'])) {
        continue;
      }

      $nested_fields = $field->getConfiguration()['nested_fields'];
      $relationship_node_type = substr($relation_nodetype_name, strlen($prefix));


      if(!is_array($nested_fields) || empty($nested_fields) || !isset($item_relation_info_list[$relationship_node_type])){
        continue;
      }

      $relation_info = $item_relation_info_list[$relationship_node_type];
      $values = [];
      $serialized = []; // ipv values, eenvoudiger - zonder custom typed data
      
      foreach($relation_info['join_fields'] as $join_field){
        $result = $node_storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('type', $relationship_node_type)
          ->condition($join_field, $entity->id())
          ->execute();
        
        if(empty($result)){
          continue;
        }

        $entities = $node_storage->loadMultiple($result);
      
        if (!$entities) {
          continue;
        }

        
        foreach($entities as $relationship_entity){
          $nested_values = [];
          foreach ($nested_fields as $nested_field){
                $nested_values[$nested_field] = $relationship_entity->get($nested_field)->getValue();
          }
           $values[] = new RelationInfoData($nested_values, $field->getDataDefinition());
           $serialized[] = $nested_values;
        }
      }
      if(!empty($values)){
        dpm($values);
        dpm($serialized);
      }
      $field->setValues($serialized);
    }  
  }
}