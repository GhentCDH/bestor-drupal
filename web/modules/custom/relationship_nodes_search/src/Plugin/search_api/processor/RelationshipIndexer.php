<?php

namespace Drupal\relationship_nodes_search\Plugin\search_api\processor;


use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes_search\Processor\RelationProcessorProperty;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleInfoService;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\search_api\Processor\EntityProcessorProperty;
use Drupal\relationship_nodes_search\TypedData\RelationInfoData;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api\SearchApiException;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\relationship_nodes\RelationEntity\RelationTermMirroring\MirrorTermProvider;
use Drupal\relationship_nodes_search\Service\RelationSearchService;

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

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RelationBundleInfoService $bundleInfoService;
  protected FieldNameResolver $fieldResolver;
  protected RelationBundleSettingsManager $settingsManager;
  protected MirrorTermProvider $mirrorProvider;
  protected RelationSearchService $relationSearchService; 

  /**
   * Constructs a RelationshipIndexer object.
   */
  public function __construct(
    array $configuration, 
    $plugin_id, 
    $plugin_definition, 
    EntityTypeManagerInterface $entity_type_manager, 
    RelationBundleInfoService $bundleInfoService,
    FieldNameResolver $fieldResolver, 
    RelationBundleSettingsManager $settingsManager, 
    MirrorTermProvider $mirrorProvider,
    RelationSearchService $relationSearchService
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfoService = $bundleInfoService;
    $this->fieldResolver = $fieldResolver;
    $this->settingsManager = $settingsManager;
    $this->mirrorProvider = $mirrorProvider;
    $this->relationSearchService = $relationSearchService;
  }

  /**
   * {@inheritdoc}s
   */
  public static function create(
    ContainerInterface $container, 
    array $configuration, 
    $plugin_id, 
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('relationship_nodes.relation_bundle_info_service'),
      $container->get('relationship_nodes.field_name_resolver'),
      $container->get('relationship_nodes.relation_bundle_settings_manager'),
      $container->get('relationship_nodes.mirror_term_provider'),
      $container->get('relationship_nodes_search.relation_search_service'),
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
      $related_relationships = $this->bundleInfoService->getRelationInfoForTargetBundle($node_type_in_index);
      if (!is_array($related_relationships)) {
        continue;
      }
      foreach($related_relationships as $relation_node_type => $info){
        if(!in_array($relation_node_type, $relationship_node_types)){
          $relationship_node_types[] = $relation_node_type;
        }
      }
    }

    foreach($relationship_node_types as $relationship_node_type){
      $definition = [
        'label' => $this->t('Related nodes of type @type', ['@type' => $relationship_node_type]),
        'description' => $this->t('All related @type nodes, with selectable fields.', ['@type' => $relationship_node_type]),
        'type' => 'string', 
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
        'definition_class_settings' => [
          'bundle' => $relationship_node_type,
        ],
      ];
      
      $property = new RelationProcessorProperty($definition);
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

    $item_relation_info_list = $this->bundleInfoService->getRelationInfoForTargetBundle($entity->getType());


    if (!($entity instanceof EntityInterface) || empty($item_relation_info_list)) {
      return;
    }

    $prefix = 'relationship_info__';
    $node_storage = $this->entityTypeManager->getStorage('node');

    foreach ($item->getFields() as $field) {
      $relation_nodetype_name = $field->getPropertyPath();     
      if ($field->getDatasourceId() != $item->getDatasourceId() || !str_starts_with( $relation_nodetype_name, $prefix) || !isset($field->getConfiguration()['nested_fields'])) {
        continue;
      }

      $nested_fields_config = $field->getConfiguration()['nested_fields'];
      $relationship_node_type = substr($relation_nodetype_name, strlen($prefix));
      
      if(!is_array($nested_fields_config) || empty($nested_fields_config) || !isset($item_relation_info_list[$relationship_node_type])){
        continue;
      }

      $relation_info = $item_relation_info_list[$relationship_node_type];
      $serialized = [];

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

        $calculated_fields = $this->relationSearchService->getCalculatedFieldNames(null, null, true);
        $calculated_fields = is_array($calculated_fields) ? $calculated_fields : [];

        
       foreach($entities as $relationship_entity){
          $nested_values = [];
          foreach ($nested_fields_config as $nested_field_name => $config){
            if(in_array($nested_field_name, $calculated_fields)){
              continue; // calculated fields are processed below
            }
            $field_values = $relationship_entity->get($nested_field_name)->getValue();
            if (empty($field_values)) {
              $nested_values[$nested_field_name] = NULL;
              continue;
            }

            $values = [];
            $drupal_field_info = $config['drupal_field'];
            $is_ref = $drupal_field_info['type'] === 'entity_reference';
            $target_type = $is_ref ? $drupal_field_info['target_type'] : null;

            foreach ($field_values as $field_value) {
              $extracted = $this->extractSingleValue($field_value, $target_type);
              if ($extracted !== NULL) {
                $values[] = $extracted;
              }
            }
            
            $nested_values[$nested_field_name] = count($values) === 1 ? reset($values) : $values;
          }

          $this->fillCalculatedFields($nested_values, $entity, $relationship_entity, $join_field);

          $serialized[] = $nested_values;
        }
        
      }
      if(!empty($serialized)){
        //dpm($serialized);
      }

      $field->setValues($serialized);
    }  
  }


  protected function fillCalculatedFields(array &$nested_values, $entity, $relationship_entity, string $join_field): void { 
    $calculated_fields = $this->relationSearchService->getCalculatedFieldNames();
    
    $nested_values[$calculated_fields['this_entity']['id']] = isset($nested_values[$join_field]) ? $nested_values[$join_field] : '';
    $nested_values[$calculated_fields['this_entity']['name']] = $entity->label();

    $node_storage = $this->entityTypeManager->getStorage('node');
    $other_field = $this->fieldResolver->getOppositeRelatedEntityField($join_field);
    $other_parsed = $this->relationSearchService->parseEntityReferenceValue($nested_values[$other_field] ?? null);
    $related_entity = !empty($other_parsed['id']) ? $node_storage->load($other_parsed['id']) : null;
    $nested_values[$calculated_fields['related_entity']['id']] = isset($nested_values[$other_field]) ? $nested_values[$other_field] : '';
    $nested_values[$calculated_fields['related_entity']['name']] = !empty($related_entity) ? $related_entity->label() : '';

    $relation_field = $this->fieldResolver->getRelationTypeField();
    if ($this->settingsManager->isTypedRelationNodeType($relationship_entity->getType()) && !empty($nested_values[$relation_field])) {
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');    
      $relation_parsed = $this->relationSearchService->parseEntityReferenceValue($nested_values[$relation_field]);
      $relation_term = !empty($relation_parsed['id']) ? $term_storage->load($relation_parsed['id']) : null;
      $default_label = $relation_term ? $relation_term->getName() : '';

      if ($join_field == $this->fieldResolver->getRelatedEntityFields(2) && !empty($relation_parsed['id'])) {
        $mirror_array = $this->mirrorProvider->getMirrorArray($term_storage, $relation_parsed['id']);
        $nested_values[$calculated_fields['relation_type']['name']] = reset($mirror_array);
      } else {
        $nested_values[$calculated_fields['relation_type']['name']] = $default_label;
      }
    }
  }


  protected function extractSingleValue($value, $target_type = null) {
    if (empty($value)) {
        return NULL;
    }

    if (isset($value['target_id'])) {
        if(empty($target_type)){
            return $value['target_id'];
        }
        return $target_type . '/' . $value['target_id'];   
    }
    
    if (isset($value['value'])) {
        return $value['value'];
    }
    
    if (is_array($value)) {
        return reset($value) ?: NULL;
    }
    
    return $value;
  }  
}