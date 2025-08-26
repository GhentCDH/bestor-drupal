<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\relationship_nodes\Service\RelationEntityTypePreparer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

use Drupal\Core\StringTranslation\StringTranslationTrait;


class RelationFieldConfigHelper {

    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;
    protected RouteMatchInterface $routeMatch;
    protected RelationEntityTypePreparer $relationPreparer;

    
    public function __construct(EntityTypeManagerInterface $entityTypeManager, RouteMatchInterface $routeMatch, RelationEntityTypePreparer $relationPreparer) {
        $this->entityTypeManager = $entityTypeManager;
        $this->routeMatch = $routeMatch;
        $this->relationPreparer = $relationPreparer;
    }


    

    public function getRelationFieldConfigUrl(FieldConfig $field_config):?Url{
        
        $url_info = $this->relationPreparer->getDefaultRoutingInfo($field_config->getTargetEntityTypeId());

        if(empty($url_info)){
            return null;
        }

        return Url::fromRoute($url_info['rn_field_edit_route'],[
            $url_info['bundle_param_key'] => $field_config->getTargetBundle(), 
            'field_config' => $field_config->id(),
        ]);
    }

    public function getRelationFieldDeleteUrl($field_config): ?url{
        $url = Url::fromRoute('relationship_nodes.rn_field_delete',['field_config' => $field_config->id(),]);
        return $url ?? null;
    }


    public function overrideOperationsEdit(array &$row, FieldConfig $field_config, array $original_operations) : void {   
        if(!$this->relationPreparer->isRnCreatedField($field_config)){
            return;
        }

        //vuil. moet nog worden opgeschoond. pas op id is niet hetzelfde als name, geen underscores maar dasehs
        if(!in_array($row['id'], ['related-entity-1', 'related-entity-2', 'relation-type', 'mirror-field-self', 'mirror-field-cross'])){
            return;
        }

        unset($row['data']['operations']);
        unset($row['class']['menu-disabled']); 
        
        $row['data'] = $row['data'] + $original_operations; 
        $url = $this->getRelationFieldConfigUrl($field_config);
        $row['data']['operations']['data']['#links']['edit']['url'] = $url;

        if(!$this->currentRouteIsRelationEntity()){
            $delete_url = $this->getRelationFieldDeleteUrl($field_config);
            $row['data']['operations']['data']['#links']['delete'] = [
                'title'=> t('Delete'),
                'weight' => 999, 
                'url' => $delete_url,
            ];
        }  
    }


    


    public function overrideLocalTasksEdit(&$local_tasks): void{

        $field_config = $this->routeMatch->getParameter('field_config');

        if (!$field_config instanceof FieldConfig || !$this->relationPreparer->isRnCreatedField($field_config)) {
            return;
        }

        $routing_info = $this->relationPreparer->getDefaultRoutingInfo($field_config->getTargetEntityTypeId());

        if(empty($routing_info)){
            return;
        }

        $route_name = $local_tasks[$routing_info['field_edit_local_task']]['route_name'];
        if(empty($route_name) ||  $route_name !== $routing_info['field_edit_form_route']){
            return;
        }

        // niet zo expliciet. dynamisch ophalen.
        if(!in_array($field_config->getName(), ['related_entity_1', 'related_entity_2', 'relation_type', 'mirror_field_self', 'mirror_field_cross'])){
            return;
        }

        $local_tasks[$routing_info['field_edit_local_task']]['route_name'] = $routing_info['rn_field_edit_route'];
    }

    

    public function getBundleFromCurrentRoute():NodeType|Vocabulary|null{
        $entity_type_id = $this->routeMatch->getParameter('entity_type_id');
        switch($entity_type_id){
            case 'node':
                $bundle = $this->routeMatch->getParameter('node_type');
                break;
            case 'taxonomy_term':
                $bundle = $this->routeMatch->getParameter('taxonomy_vocabulary');
                break;
            default:
                $bundle = null;
                break;
        }
        if(is_string($bundle)){
            $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
            $bundle = $entity_storage->load($bundle);;
        }

        if(!($bundle instanceof NodeType || $bundle instanceof Vocabulary)){
            return null;
        }
        return $bundle;
    }

    public function currentRouteIsRelationEntity():bool{
        $bundle_entity = $this->getBundleFromCurrentRoute();
        if (!$bundle_entity) {
            return false;
        }
        return $this->relationPreparer->isRelationEntity($bundle_entity);
    }

}