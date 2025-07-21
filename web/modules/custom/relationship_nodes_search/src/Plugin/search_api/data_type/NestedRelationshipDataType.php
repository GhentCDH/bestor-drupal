<?php

namespace Drupal\relationship_nodes_search\Plugin\search_api\data_type;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\DataType\DataTypePluginBase;
use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes\Service\RelationshipInfoService;

/**
 *
 * @SearchApiDataType(
 *   id = "relationship_nodes_search_nested_relationship",
 *   label = @Translation("Nested relationship object"),
 *   description = @Translation("Stores nested relationship data as an object."),
 *   fallback_type = "search_api_elasticsearch_client_object"
 * )
 */
class NestedRelationshipDataType extends DataTypePluginBase implements ContainerFactoryPluginInterface {
    protected RelationshipInfoService $infoService;

    public function __construct(array $configuration, $plugin_id, $plugin_definition, RelationshipInfoService $infoService) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->infoService = $infoService;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('relationship_nodes.relationship_info_service')
        );
    }


    /**
     * {@inheritdoc}
     */
    public function getValues($item, DatasourceInterface $datasource, Index $index) {
        return [];
 /*       $entity = $item->getOriginalObject()->getValue();

        if (!$entity || !$entity->id()) {
            return [];
        }

        $bundle_prefix = $this->infoService->getRelationshipNodeBundlePrefix();
        $related_entity_fields = $this->infoService->getRelatedEntityFields();
        $rel_type_field = $this->infoService->getRelationshipTypeField();
        $nid = $entity->id();

        //dpm($item);


        $nids = [];

        $query = \Drupal::entityQuery('node')
        ->condition('type', ['relation_type_1', 'relation_type_2'], 'IN')
        ->condition(
            ['field_related_item_1', 'field_related_item_2'],
            $entity->id(),
            'IN'
        );
        $result = $query->execute();

        if ($result) {
        $nids = array_values($result);
        }

        return $nids ?? [];*/
    }
}