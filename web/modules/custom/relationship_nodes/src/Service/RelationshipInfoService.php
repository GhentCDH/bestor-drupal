<?php


namespace Drupal\relationship_nodes\Service;

use \Drupal\Core\Entity\EntityTypeManagerInterface;
use \Drupal\Core\Entity\EntityFieldManagerInterface;
use \Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use \Drupal\field\Entity\FieldConfig;
use \Drupal\field\Entity\FieldStorageConfig;
use \Drupal\node\Entity\Node;
use \Drupal\taxonomy\Entity\Term;
use \Drupal\Core\Field\FieldStorageDefinitionInterface;
use \Drupal\Core\Entity\EntityTypeInterface;
use \Drupal\Core\Routing\RouteMatchInterface;


class RelationshipInfoService {

    function getRerelationshipNodeBundlePrefix() {
        $config = \Drupal::config('relationship_nodes.settings');
        return $config->get('relationship_node_bundle_prefix') ?? '';
    }

    function getRelationshipTypeField() {
        $config = \Drupal::config('relationship_nodes.settings');
        return $config->get('relationship_type_field') ?? '';
    }

    function getRelationshipFormMode() {
        $config = \Drupal::config('relationship_nodes.settings');
        return $config->get('relationship_form_mode') ?? '';
    }

    function getRelatedEntityFields() {
        $config = \Drupal::config('relationship_nodes.settings');
        if (!$config->get('related_entity_fields') ||!is_array($config->get('related_entity_fields')) || count($config->get('related_entity_fields')) !== 2 || !isset($config->get('related_entity_fields')['related_entity_field_1']) || !isset($config->get('related_entity_fields')['related_entity_field_2'])) {
            return [];
        }
        return $config->get('related_entity_fields');
    }

    function getRelationshipTaxonomyPrefixes() {
        $config = \Drupal::config('relationship_nodes.settings');
        if (!$config->get('relationship_taxonomy_prefixes') || !is_array($config->get('relationship_taxonomy_prefixes')) || count($config->get('relationship_taxonomy_prefixes')) !== 2 || !isset($config->get('relationship_taxonomy_prefixes')['selfreferencing_vocabulary_prefix']) || !isset($config->get('relationship_taxonomy_prefixes')['crossreferencing_vocabulary_prefix'])) {
            return [];
        }
        return $config->get('relationship_taxonomy_prefixes');
    }

    function getMirrorFields() {
        $config = \Drupal::config('relationship_nodes.settings');
        if (!$config->get('mirror_fields') || !is_array($config->get('mirror_fields')) || count($config->get('mirror_fields')) !== 2 || !isset($config->get('mirror_fields')['mirror_reference_field']) || !isset($config->get('mirror_fields')['mirror_string_field'])) {
            return [];
        }
        return $config->get('mirror_fields');
    }

    function getRelationshipNodeBundlePrefix() {
        $config = \Drupal::config('relationship_nodes.settings');
        return $config->get('relationship_node_bundle_prefix') ?? '';
    }

    function allConfigAvailable() {
        if($this->getRelationshipNodeBundlePrefix() === '' || $this->getRelationshipTypeField() === '' || $this->getRelationshipFormMode() === '' || $this->getRelatedEntityFields() === [] || $this->getRelationshipTaxonomyPrefixes() === [] || $this->getMirrorFields() === []) {
            return false;
        }
        return true;
    }

  /**
   *
   * @param string $node_type
   *
   * @return array
   */
  public function relationshipNodeInfo($node_type) {

    $result = ['relationnode' => false];
    $node_types = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();

    if (!isset($node_types[$node_type]) || $this->allConfigAvailable() === false) {
        return $result;
    }

    $bundle_prefix = $this->getRelationshipNodeBundlePrefix();
    $related_fields = $this->getRelatedEntityFields();

    if (strpos($node_type, $bundle_prefix) !== 0) {
        return $result;
    }

    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $node_type);
    $related_bundles = [];
    foreach ($related_fields as $field_name) {
        if (!isset($fields[$field_name])) {
            continue;
        }
        $field = $fields[$field_name];
        try {
            if ($field->getType() === 'entity_reference') {
                $settings = $field->getSettings();
                $target_bundles = $settings['handler_settings']['target_bundles'] ?? [];      
                if (count($target_bundles) === 1) {
                    $target = array_values($target_bundles)[0];
                    if (strpos($target, $bundle_prefix) === false) {
                      $related_bundles[$field_name] = $target;
                    }
                }
            }
        } catch (\Exception $e) {} 
    }
    if (count($related_bundles) !== 2) {
        return $result;
    }
    $result = [
        'relationnode' => true,
        'relationship_bundle' => $node_type,
        'related_entity_fields' => $related_bundles,
        'relationnodetype' => '',
        'relationtypeinfo' => ['relationtypefield' => '', 'vocabulary' => '', 'mirrorfieldtype' => '']
    ];
    
    $relation_type_field_name = $this->getRelationshipTypeField();
    if ($relation_type_field_name && isset($fields[$relation_type_field_name])) {
        try {
            $target_bundles =  $fields[$relation_type_field_name]->getSettings()['handler_settings']['target_bundles'];
            if(count($target_bundles) == 1){
                $vocab = array_values($target_bundles)[0];
                $vocab_info = $this->relationshipTaxonomyVocabularyInfo($vocab);
                if ($vocab_info['relationtypevocabulary']) {              
                    $result['relationtypeinfo']['relationtypefield'] = $vocab_info['relationtypevocabulary'];
                    $result['relationtypeinfo']['relationtypefield'] == true ? $result['relationtypeinfo']['vocabulary'] = array_values($target_bundles)[0] : '';
                    $result['relationtypeinfo']['mirrorfieldtype'] = $vocab_info['mirrorfieldtype'] ?? '';
                }
            }                    
        } catch (\Exception $e) {}          
    }
    $result['relationnodetype'] = (array_values($related_bundles)[0] === array_values($related_bundles)[1]) ? 'selfreferencing' : 'crossreferencing';    
    
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
    $result = [
        'relationtypevocabulary' => false,
        'mirrorfieldname' => false,
        'mirrorfieldtype' => false,
      ];
    
    $vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();

    if ($this->allConfigAvailable() === false) {
        return $result;
    }

    $prefixes = $this->getRelationshipTaxonomyPrefixes();
    $mirror_fields_config = $this->getMirrorFields();
  
    $self_prefix = $prefixes['selfreferencing_vocabulary_prefix'];
    $cross_prefix = $prefixes['crossreferencing_vocabulary_prefix'];

    $is_self = $self_prefix && str_starts_with($taxonomy_vocabulary, $self_prefix);
    $is_cross = $cross_prefix && str_starts_with($taxonomy_vocabulary, $cross_prefix); 
    if (!$is_self && !$is_cross) {
      return $result;
    }

    $result['relationtypevocabulary'] = true;
    
    
    if($vocabulary_fields === null) {
        $vocabulary_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('taxonomy_term', $taxonomy_vocabulary);
    }
    $mirror_reference_field = $mirror_fields_config['mirror_reference_field'] ?? '';
    $mirror_string_field = $mirror_fields_config['mirror_string_field'] ?? '';

    if($is_self && $mirror_reference_field && isset($vocabulary_fields[$mirror_reference_field])){
        $field_def = $vocabulary_fields[$mirror_reference_field];
        if ($field_def instanceof FieldConfig) {
            try {
                $storage = FieldStorageConfig::loadByName('taxonomy_term', $mirror_reference_field);
                if ($storage) {
                    $settings = $field_def->getSettings();
                    if ($field_def->getType() === 'entity_reference' && isset($settings['handler_settings']['target_bundles']) && $settings['handler_settings']['target_bundles'] === [$taxonomy_vocabulary => $taxonomy_vocabulary]) {
                        $result['mirrorfieldname'] = $mirror_reference_field;
                        $result['mirrorfieldtype'] = 'entity_reference_selfreferencing';
                    }
                }
            } catch (\Drupal\Core\Field\FieldException $e) {}
        }          
    } else if ($is_cross && $mirror_string_field && isset($vocabulary_fields[$mirror_string_field])){
        $field_def = $vocabulary_fields[$mirror_string_field];
        if($field_def->getType() == 'string'){
            $result['mirrorfieldname'] = $mirror_string_field;
            $result['mirrorfieldtype'] = 'string';
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
        if ($this->allConfigAvailable() === false || $entity_type->id() !== 'node') {
            return $relationshipInfo;
        }
      
        $related_entity_fields = $this->getRelatedEntityFields();


        $all_node_bundles = \Drupal::service("entity_type.bundle.info")->getBundleInfo('node');

        foreach($all_node_bundles as $bundle_name => $bundle_array){
            if(isset( $bundle_array['relationship_info_bundle']['relationnode']) &&  $bundle_array['relationship_info_bundle']['relationnode'] === true && isset($bundle_array['relationship_info_bundle']['related_entity_fields'])){ 
                $join_fields = [];
                $relationship_node_info =  $bundle_array['relationship_info_bundle'];
                $related_bundles = $relationship_node_info['related_entity_fields'];
                foreach($related_bundles as $field_name => $related_bundle){
                    
                    if($related_bundle == $bundle){
                        $join_fields[] = $field_name; 
                    }
                } 
                if (!empty($join_fields)) {
                    $relationship_node_info['join_fields'] = $join_fields;
                    $relationship_node_info['this_bundle'] = $bundle;
                    $relationship_node_info['related_bundle'] =  $related_bundles[$related_entity_fields['related_entity_field_1' ]] == $bundle ? $related_bundles[$related_entity_fields['related_entity_field_2' ]] : $related_bundles[$related_entity_fields['related_entity_field_1' ]];
                    $relationship_node_info['relationship_bundle']= $bundle_name;
                    $relationshipInfo[] = $relationship_node_info;
                }      
            }
        }
        return $relationshipInfo;
    }


     /**
     * @param \Drupal\node\Entity\Node $relationship_node
     * @param string $bundle
     *
     * @return array
     * 
     * Deze functie checkt of een relatie node (input) een join field heeft met de huidige node en geeft terug welke.
     */

    function getForeignKeyField(Node $relationship_node, $bundle_name){

    }
    
    /**
     * @param \Drupal\node\Entity\Node $relationship_node
     *
     * @return array
     * 
     * Deze functie checkt of een relatie node (input) een join field heeft met de huidige node en geeft terug welke.
     */
    function getRelationInfoForCurrentForm(Node $relationship_node){
        
        $node_info = $this->relationshipNodeInfo($relationship_node->getType());
 
        if(!$relationship_node->id() ||  $this->allConfigAvailable() === false|| !isset($node_info['relationnode']) || !$node_info['relationnode']){
            return [];
        }
        $related_fields = $this->getRelatedEntityFields();
        $joinFields = [];
        $status = '';

        $current_node = \Drupal::routeMatch()->getParameter('node');
        if (!($current_node instanceof Node && in_array( $current_node->getType(), $node_info['related_entity_fields']))) {
          return [];
        }

        $related_entity_field_1 = $related_fields['related_entity_field_1'];
        $related_entity_field_2 = $related_fields['related_entity_field_2'];
        $referenced_entity_1 = $relationship_node->get($related_entity_field_1)->referencedEntities();
        $referenced_entity_2 = $relationship_node->get($related_entity_field_2)->referencedEntities();
        $related_entity_value_1 = isset($referenced_entity_1[0]) ? $referenced_entity_1[0]->id() : null;
        $related_entity_value_2 =  isset($referenced_entity_2[0]) ? $referenced_entity_2[0]->id() : null;

        if($current_node->id() == $related_entity_value_1){
            $joinFields[] = $related_entity_field_1;
        } 
        if($current_node->id() == $related_entity_value_2){
            $joinFields[] = $related_entity_field_2;
        }

        if(count($joinFields) === 1){
            $status = 'Existing';
        } elseif (count($joinFields) == 2){
            $status = 'Error: both related entities are the same.';
        } elseif (count($joinFields) == 0){
            if(is_null($related_entity_value_1) && is_null($related_entity_value_2)){
                $status = 'New';
            } else {
                $status = 'Error: unrelated relationship node';
            }
        }
        return  ['current_node_join_fields' => $joinFields, 'relationship_node_status' => $status, 'general_relationship_info' => $node_info];
    }

}

