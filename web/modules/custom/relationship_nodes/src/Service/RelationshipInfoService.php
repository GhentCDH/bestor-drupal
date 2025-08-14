<?php


namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\relationship_nodes\Service\ConfigManager;
use Drupal\relationship_nodes\Service\RelationEntityValidator;
use Drupal\relationship_nodes\Service\ReferenceFieldHelper;


class RelationshipInfoService {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected EntityFieldManagerInterface $fieldManager;
    protected EntityTypeBundleInfoInterface $bundleInfo;
    protected RouteMatchInterface $routeMatch;
    protected ConfigManager $configManager;
    protected RelationEntityValidator $relationEntityValidator;
    protected ReferenceFieldHelper $referenceFieldHelper;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $fieldManager,
        EntityTypeBundleInfoInterface $bundleInfo,
        RouteMatchInterface $routeMatch,
        ConfigManager $configManager,
        RelationEntityValidator $relationEntityValidator,
        ReferenceFieldHelper $referenceFieldHelper
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldManager = $fieldManager;
        $this->bundleInfo = $bundleInfo;
        $this->routeMatch = $routeMatch;
        $this->configManager = $configManager;
        $this->relationEntityValidator = $relationEntityValidator;
        $this->referenceFieldHelper = $referenceFieldHelper;
    }


    public function getRelationBundleInfo(string $bundle, array $fields = []):array {
        if (empty($fields)) {
            $fields = $this->fieldManager->getFieldDefinitions('node', $bundle);
        }

        if (!$this->relationEntityValidator->isValidRelationBundle($bundle, $fields)) {
            return [];
        }

        $related_bundles = [];

        foreach ($this->configManager->getRelatedEntityFields() as $field_name) {
            $related_bundles[$field_name] = $this->referenceFieldHelper->getFieldTargetBundles($fields[$field_name]);
        }

        $info = [
            'related_bundles_per_field' => $related_bundles,
            'has_relationtype' => false
        ];

        $target_bundles = $this->referenceFieldHelper->getFieldTargetBundles($fields[$this->configManager->getRelationTypeField()]);            
        
        if(count($target_bundles) != 1){
            return $info;
        }

        $vocab = $target_bundles[0];
        
        $vocab_info = $this->getRelationVocabInfo($vocab);
   
        if(empty($vocab_info)){
            return $info;
        }           

        $info['has_relationtype'] = true;
        $info['relationtypeinfo'] = $vocab_info;
        $info['relationtypeinfo']['vocabulary'] = $vocab;

        return $info;
    }
 

    public function getRelationVocabInfo(string $vocab, array $fields = []): array {
        if (empty($fields)) {
            $fields = $this->fieldManager->getFieldDefinitions('taxonomy_term', $vocab);
        }

        switch($this->relationEntityValidator->identifyRelationVocab($vocab, $fields)){
            case 'cross':
                $result = [
                    'mirror_field_type' => 'string',
                    'mirror_field_name' => $this->configManager->getMirrorFields('string'),
                    'referencing_type' => 'crossreferencing'
                ];
                break;
            case 'self':
                $result = [
                    'mirror_field_type' => 'entity_reference_selfreferencing',
                    'mirror_field_name' => $this->configManager->getMirrorFields('reference'),
                    'referencing_type' => 'selfreferencing'
                ];
                break;
            default:
                $result = [];
        }

        return $result ?? [];
    }


    public function getRelationInfoForTargetBundle(string $target_bundle): array { 
        $all_bundles_info = $this->bundleInfo->getBundleInfo('node');
        $relation_info = [];

        foreach($all_bundles_info as $bundle_id => $bundle_array){
            if (empty($bundle_array['relation_bundle']) || empty($bundle_array['relation_bundle']['related_bundles_per_field'])) {
                continue;
            }

            $related_bundles_per_field = $bundle_array['relation_bundle']['related_bundles_per_field'];
            $join_fields = [];
            $other_bundles = [];

            foreach($related_bundles_per_field as $field_name => $related_bundles){   
                if(in_array($target_bundle, $related_bundles)){
                    $join_fields[] = $field_name; 
                } else{
                    $other_bundles = $related_bundles;
                }
            } 

            if (empty($join_fields)) {
                continue;
            }

            $relation_info[$bundle_id] = [
                'join_fields' => $join_fields,
                'related_bundles' =>  count($join_fields) == 1 ? $other_bundles : [$target_bundle],
                'relation_bundle_info' => $bundle_array['relation_bundle'],
            ];
        }

        return $relation_info;
    }


    public function getBundleConnectionInfo(string $relation_bundle, string $target_bundle):array{
        $relation_info = $this->getRelationBundleInfo($relation_bundle);
        if(empty($relation_info) || empty($relation_info['related_bundles_per_field'])){
            return [];
        }

        $join_fields = [];
        foreach($relation_info['related_bundles_per_field'] as $field => $bundles_arr){
            if(in_array($target_bundle, $bundles_arr)){
                $join_fields[] = $field;
            }
        }

        return empty($join_fields) ? [] : ['join_fields' => $join_fields, 'relation_info' => $relation_info];
    }


    public function getJoinFields(Node $relation_node, Node $target_node = NULL, array $field_names): array {
        $result = [];
        $bundle_connections = $this->getBundleConnectionInfo($relation_node->getType(), $target_node->getType());
        
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


    public function getEntityConnectionInfo(Node $relation_node, ?Node $target_node = NULL): array {
        $target_node = $this->ensureTargetNode($target_node);
        if(!$target_node){
            return [];
        }

        $bundle_connections = $this->getBundleConnectionInfo($relation_node->getType(), $target_node->getType());
        $result = ['relation_state' => 'unrelated'];

        if(empty($bundle_connections['join_fields'])){
            return $result;
        }

        $connections = $this->getJoinFields($relation_node, $target_node, $bundle_connections['join_fields']) ?? [];

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

   
    public function getDefaultBundleForeignKeyField(string $relation_bundle, string $target_bundle = null): ?string{       
        if(!$target_bundle){
            $target_entity = $this->ensureTargetNode($target_entity);
            if(!($target_entity instanceof Node)){
                return null;
            }
            $target_bundle = $target_entity->getType();
        }        
        
        $connection_info = $this->getBundleConnectionInfo($relation_bundle, $target_bundle) ?? [];
        return $this->connectionInfoToForeignKey($connection_info);
    }



    public function getEntityForeignKeyField(Node $relation_entity, ?Node $target_entity = NULL): ?string {
        $target_entity = $this->ensureTargetNode($target_entity);
        if(!$target_entity){
            return null;
        }
        $relation_type = $relation_entity->getType();
        $target_entity_type = $target_entity->getType();
        if($relation_entity->isNew() || $target_entity->isNew()){
            $connection_info = $this->getBundleConnectionInfo($relation_type, $target_entity_type) ?? [];
        } else {
            $connection_info = $this->getEntityConnectionInfo($relation_entity, $target_entity) ?? [];
        }
        return $this->connectionInfoToForeignKey($connection_info);
    }


    public function getEntityFormForeignKeyField(array $entity_form, FormStateInterface $form_state):?string {
        if(!isset($entity_form['#entity']) || !($entity_form['#entity'] instanceof Node)){
            return null;
        }   
        $relation_entity = $entity_form['#entity'];
        $form_entity = $form_state->getFormObject()->getEntity();
        return $this->getEntityForeignKeyField($relation_entity,  $form_entity);   
    }


    public function getReferencingRelations(Node $target_node, string $relation_bundle, array $join_fields = []): array {
        $target_bundle = $target_node->getType();
        if(empty($join_fields)){
            $connection_info = $this->getBundleConnectionInfo($relation_bundle, $target_bundle) ?? [];
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


    public function getAllReferencingRelations(Node $target_node): array {
        $result = [];
        $target_bundle_info = $this->getRelationInfoForTargetBundle($target_node->getType());    
       
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


    public function getRelatedEntityValues(Node $relation_node): ?array {      
        if(!$this->relationEntityValidator->isValidRelationBundle($relation_node->getType())){
            return null;
        }

        $result = [];
        foreach($this->configManager->getRelatedEntityFields() as $related_entity_field){
            $related_field = $relation_node->get($related_entity_field);
             if(!$related_field instanceof EntityReferenceFieldItemList){
                return null;    
            }
            $relation_references = $this->referenceFieldHelper->getFieldListTargetIds($related_field);
            if(empty($relation_references)){
                continue;
            }
            $result[$related_entity_field] = $relation_references;
        }
        return $result;   
    }


    private function connectionInfoToForeignKey(array $connection_info): ?string{
        if(empty($connection_info['join_fields'])){
            return null;
        }
        $join_fields = $connection_info['join_fields'];

        if(!is_array($join_fields)){
            return null;
        }
        return $join_fields[0] ?? null;
    }
    

    private function ensureTargetNode(?Node $node): ?Node {
        if($node instanceof Node){
            return $node;
        }
        $current_node = $this->routeMatch->getParameter('node');
        return $current_node instanceof Node ? $current_node : null;
    }
}