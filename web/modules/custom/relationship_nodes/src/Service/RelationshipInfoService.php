<?php


namespace Drupal\relationship_nodes\Service;

use \Drupal\Core\Entity\EntityTypeManagerInterface;
use \Drupal\Core\Entity\EntityFieldManagerInterface;
use \Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use \Drupal\node\Entity\Node;
use \Drupal\taxonomy\Entity\Term;
use \Drupal\Core\Field\FieldStorageDefinitionInterface;
use \Drupal\Core\Entity\EntityTypeInterface;
use \Drupal\Core\Routing\RouteMatchInterface;


class RelationshipInfoService {


  /**
   *
   * @param string $node_type
   *
   * @return array
   */
  public function relationshipNodeInfo($node_type) {

    $result = ['relationnode' => false];
    $all_node_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
    $config = \Drupal::config('relationship_nodes.settings');
    if(isset($all_node_types[$node_type]) && $config->get('relationship_node_bundle_prefix') != null && $config->get('related_entity_fields') != null && count($config->get('related_entity_fields')) == 2){ 
        $relationship_node_bundle_prefix = $config->get('relationship_node_bundle_prefix');
        $related_entity_fields = $config->get('related_entity_fields');
        if (strpos($node_type, $relationship_node_bundle_prefix) === 0){
            $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $node_type);
            $related_entity_bundles = [];
            foreach ($related_entity_fields as $related_entity_field) {
                if (isset($fields[$related_entity_field])) {
                    if($fields[$related_entity_field]->getType() == 'entity_reference' && isset($fields[$related_entity_field]->getSettings()['handler_settings']['target_bundles'])){
                        $field_target_bundle = $fields[$related_entity_field]->getSettings()['handler_settings']['target_bundles'];      
                        if(count($field_target_bundle) == 1 && strpos(array_values($field_target_bundle)[0], $relationship_node_bundle_prefix) === false){
                            $related_entity_bundles[$related_entity_field] = array_values($field_target_bundle)[0];
                        }
                    }
                }
            }
            if(count($related_entity_bundles) == 2){
                $result = [
                    'relationnode' => true,
                    'relationship_bundle' => $node_type,
                    'related_entity_fields' => $related_entity_bundles,
                    'relationnodetype' => '',
                    'relationtypefield' => ['relationtypefield' => '', 'vocabulary' => '', 'mirrorfieldtype' => '']
                ];
                $relationtype_field_info = [];
                if ($config->get('relationship_type_field') != null && isset($fields[$config->get('relationship_type_field')])){
                    $relationship_type_field = $fields[$config->get('relationship_type_field')];
                    if(isset($relationship_type_field->getSettings()['handler_settings']['target_bundles'])){
                        $target_bundles = $relationship_type_field->getSettings()['handler_settings']['target_bundles'];
                        if(count($target_bundles) == 1){
                            $relationtype_field_info = $this->relationshipTaxonomyVocabularyInfo(array_values($target_bundles)[0]);
                            $result['relationtypefield']['relationtypefield'] = $relationtype_field_info['relationtypevocabulary'];
                            $result['relationtypefield']['relationtypefield'] == true ? $result['relationtypefield']['vocabulary'] = array_values($target_bundles)[0] : '';
                        }                    
                    }          
                }
                if(array_values($related_entity_bundles)[0] == array_values($related_entity_bundles)[1]){
                    $result['relationnodetype'] = 'selfreferencing';
                    if($result['relationtypefield']['relationtypefield'] == true && isset($relationtype_field_info['mirrorfieldtype']) && $relationtype_field_info['mirrorfieldtype'] == 'entity_reference_selfreferencing'){
                        $result['relationtypefield']['mirrorfieldtype'] = 'entity_reference_selfreferencing';
                    }            
                } else {
                    $result['relationnodetype'] = 'crossreferencing';
                    if($result['relationtypefield']['relationtypefield'] == true && isset($relationtype_field_info['mirrorfieldtype']) && $relationtype_field_info['mirrorfieldtype'] == 'string'){
                        $result['relationtypefield']['mirrorfieldtype'] = 'string';
                    }
                }
            }
        }
    }
    return $result;
  }
 
  /**
   * Controleer of een taxonomie bundle bepaalde velden heeft.
   *
   * @param string $taxonomy_vocabulary
   * @param array $vocabulary_fields    
   *
   * @return array
   */
  public function relationshipTaxonomyVocabularyInfo($taxonomy_vocabulary, $vocabulary_fields = null) {
    $result = ['relationtypevocabulary' => false];
    $all_taxonomy_vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    $config = \Drupal::config('relationship_nodes.settings');
    if(isset($all_taxonomy_vocabularies[$taxonomy_vocabulary]) && $config->get('relationship_taxonomy_prefixes') != null){

        $selfreferencing_vocabulary_prefix = $config->get('relationship_taxonomy_prefixes')['selfreferencing_vocabulary_prefix'];
        $crossreferencing_vocabulary_prefix = $config->get('relationship_taxonomy_prefixes')['crossreferencing_vocabulary_prefix'];
        if(strpos($taxonomy_vocabulary, $selfreferencing_vocabulary_prefix) === 0 || strpos($taxonomy_vocabulary, $crossreferencing_vocabulary_prefix) === 0){
            $mirror_fields = [];
            $mirror_reference_field = '';
            $mirror_string_field = '';
            $result = [
                'relationtypevocabulary' => true,
                'mirrorfieldname' => false,
                'mirrorfieldtype' => false
            ];
            if($config->get('mirror_fields') != null){
                $mirror_fields = $config->get('mirror_fields');
                $mirror_reference_field = isset($mirror_fields['mirror_reference_field']) ? $mirror_fields['mirror_reference_field'] : false;
                $mirror_string_field = isset($mirror_fields['mirror_string_field']) ? $mirror_fields['mirror_string_field'] : false;
            }

            if(!isset($vocabulary_fields) && \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', $taxonomy_vocabulary) != null){
                $vocabulary_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', $taxonomy_vocabulary);  
            }
            if(isset($vocabulary_fields)){
                if($selfreferencing_vocabulary_prefix != null && strpos($taxonomy_vocabulary, $selfreferencing_vocabulary_prefix) === 0){
                    if($mirror_reference_field != '' && isset($vocabulary_fields[$mirror_reference_field])){
                        $mirror_reference_field_entity = $vocabulary_fields[$mirror_reference_field];
                        if($mirror_reference_field_entity->getType() == 'entity_reference' && $mirror_reference_field_entity->getSettings()['handler_settings']['target_bundles'] == [$taxonomy_vocabulary => $taxonomy_vocabulary]){
                            $result['mirrorfieldname'] = $mirror_reference_field;
                            $result['mirrorfieldtype'] = 'entity_reference_selfreferencing';
                        }
                    }
                } else if ($crossreferencing_vocabulary_prefix != null && strpos($taxonomy_vocabulary, $crossreferencing_vocabulary_prefix) === 0){
                    if($mirror_string_field != '' && isset($vocabulary_fields[$mirror_string_field]) && $vocabulary_fields[$mirror_string_field]->getType() == 'string'){
                        $result['mirrorfieldname'] = $mirror_string_field;
                        $result['mirrorfieldtype'] = 'string';
                    }
                }
            }
        }  
    }
    return $result;
  }


  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param string $bundle
   *
   * @return array
   */
    function relationshipInfoForRelatedItemNodeType(EntityTypeInterface $entity_type, $bundle) { 
        $relationshipInfo = [];
        if($entity_type->id() == 'node'){
            $config = \Drupal::config('relationship_nodes.settings');
            if($config->get('related_entity_fields') != null && count($config->get('related_entity_fields')) == 2){
                $related_entity_fields = $config->get('related_entity_fields');
                $all_node_bundles = \Drupal::service("entity_type.bundle.info")->getBundleInfo('node');
                foreach($all_node_bundles as $single_bundle_id => $single_bundle){
                    if(isset($single_bundle['relationship_info_bundle']['relationnode']) && isset($single_bundle['relationship_info_bundle']['related_entity_fields'])){ 
                        $join_fields = [];
                        $relationship_node_info = $single_bundle['relationship_info_bundle'];
                        $related_entity_bundles = $relationship_node_info['related_entity_fields'];
                        foreach($related_entity_bundles as $related_entity_field_id => $related_entity_bundle){
                            if($related_entity_bundle == $bundle){
                                $join_fields[] = $related_entity_field_id;
                            } 
                        } 
                        if(count($join_fields) > 0){
                            $relationship_node_info['join_fields'] = $join_fields;
                            $relationship_node_info['this_bundle'] = $bundle;
                            $relationship_node_info['related_bundle'] =  $related_entity_bundles[$related_entity_fields['related_entity_field_1' ]] == $bundle ? $related_entity_bundles[$related_entity_fields['related_entity_field_2' ]] : $related_entity_bundles[$related_entity_fields['related_entity_field_1' ]];
                            $relationshipInfo[] = $relationship_node_info;
                        }      
                    }
                }
                
            } 
        }
        return $relationshipInfo;
    }

    
    /**
     * @param \Drupal\node\Entity\Node $relationship_node
     *
     * @return array
     * 
     * Deze functie checkt of een relatie node (input) een join field heeft met de huidige node en geeft terug welke.
     */
    function getRelationInfoForCurrentForm($relationship_node){
        $joinFields = [];
        $relationship_node_status = '';
        $config = \Drupal::config('relationship_nodes.settings');
        if($config->get('related_entity_fields') != null && isset($config->get('related_entity_fields')['related_entity_field_1']) && isset($config->get('related_entity_fields')['related_entity_field_2'])){
          $relationshipnode_info = $this->relationshipNodeInfo($relationship_node->getType());
          if(isset($relationshipnode_info['relationnode'])){
            $route = \Drupal::routeMatch()->getParameters();
            $current_node_type = $route->get('node')->getType();  
            if(in_array($current_node_type, $relationshipnode_info['related_entity_fields'])){
              $current_node_id = $route->get('node')->id();
              $related_entity_field_1 = $config->get('related_entity_fields')['related_entity_field_1'];
              $related_entity_field_2 = $config->get('related_entity_fields')['related_entity_field_2'];
              $related_entity_field_1_id_value = $relationship_node->get($related_entity_field_1)->getValue()[0]['target_id'] ?? null;
              $related_entity_field_2_id_value = $relationship_node->get($related_entity_field_2)->getValue()[0]['target_id'] ?? null;
              if(isset($related_entity_field_1_id_value) && isset($related_entity_field_2_id_value)){
                if($current_node_id == $related_entity_field_1_id_value){
                  $joinFields[] = $related_entity_field_1;
                } else if($current_node_id == $related_entity_field_2_id_value){
                  $joinFields[] = $related_entity_field_2;
                }

                // Deze functie moet veel beter uitgewerkt worden. het moet eigen duidelijk zijn,  niet alleen in welke related entity velden het een join is, maar ook wat de reden is als er geen related enities zijn
                if(count($joinFields) == 1){
                    $relationship_node_status = 'Existing';
                } else if (count($joinFields) == 2){
                    $relationship_node_status = 'Error: both related entities are the same.';
                } else if (count($joinFields) == 0){
                    if($related_entity_field_1_id_value == null && $related_entity_field_2_id_value == null){
                        $relationship_node_status = 'New';
                    } else {
                        $relationship_node_status = 'Error: unrelated relationship node';
                    }
                }

            }
          }
        }
        return ['current_node_join_fields' => $joinFields, 'relationship_node_status' => $relationship_node_status, 'general_relationship_info' => $relationshipnode_info];
      }
    }
}

