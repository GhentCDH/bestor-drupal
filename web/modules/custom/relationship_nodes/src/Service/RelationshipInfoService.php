<?php


namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;



class RelationshipInfoService {

    protected ConfigFactoryInterface $configFactory;
    protected EntityTypeManagerInterface $entityTypeManager;
    protected EntityFieldManagerInterface $fieldManager;
    protected EntityTypeBundleInfoInterface $bundleInfo;
    protected RouteMatchInterface $routeMatch;


    public function __construct(
        ConfigFactoryInterface $configFactory,
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $fieldManager,
        EntityTypeBundleInfoInterface $bundleInfo,
        RouteMatchInterface $routeMatch
    ) {
        $this->configFactory = $configFactory;
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldManager = $fieldManager;
        $this->bundleInfo = $bundleInfo;
        $this->routeMatch = $routeMatch;
    }



    public function getRelationTypeField(): string {
        return $this->getConfig()->get('relationship_type_field') ?? '';
    }



    public function getRelationFormMode(): string {
        return $this->getConfig()->get('relationship_form_mode') ?? '';
    }



    public function getRelatedEntityFields(?int $no = null): array|string {
        $fields = $this->getConfig()->get('related_entity_fields') ?? [];

        if($no === 1 || $no === 2){
            return array_values($fields)[$no - 1] ?? '';
        }
        
        return $fields;
    }



    public function getRelationTypeVocabPrefixes(?string $type = null): array|string {
        $options = ['self' => 'selfreferencing_vocabulary_prefix', 'cross' => 'crossreferencing_vocabulary_prefix'];
        $prefixes = $this->getConfig()->get('relationship_taxonomy_prefixes') ?? [];
            
        if($type !== null && isset($options[$type])){
            return $prefixes[$options[$type]] ?? '';
        }
        
        return $prefixes;
    }



    public function getMirrorFields(?string $type = null): array|string {
        $options = ['string' => 'mirror_string_field', 'reference' => 'mirror_reference_field'];
        $fields  = $this->getConfig()->get('mirror_fields') ?? [];
        if($type !== null && isset($options[$type])){

            return $fields[$options[$type]] ?? '';
        }

        return $fields;
    }



    public function getRelationBundlePrefix(): string {
        return $this->getConfig()->get('relationship_node_bundle_prefix') ?? '';
    }

   

    public function validBasicRelationConfig(): bool{
        $bundle_prefix = $this->getRelationBundlePrefix();
        $related_fields = $this->getRelatedEntityFields();
        
        if($bundle_prefix == '' || !$this->hasRequiredKeys($related_fields, ['related_entity_field_1', 'related_entity_field_2'], true)){
            return false;
        }

        return true;
    }



    public function validTypedRelationConfig(): bool{
        if (empty($this->getRelationTypeField())) {
            return false;
        }

        $config_sets = [
            ['config' => $this->getRelationTypeVocabPrefixes(), 'required_keys' => ['selfreferencing_vocabulary_prefix', 'crossreferencing_vocabulary_prefix']],
            ['config' => $this->getMirrorFields(), 'required_keys' => ['mirror_reference_field', 'mirror_string_field']],
        ];

        foreach ($config_sets as $set) {
            if (!$this->hasRequiredKeys($set['config'], $set['required_keys'], true)) {
                return false;
            }
        }

        return true;
    }



    function allConfigAvailable() {
        if($this->getRelationBundlePrefix() === '' || $this->getRelationTypeField() === '' || $this->getRelationFormMode() === '' || $this->getRelatedEntityFields() === [] || $this->getRelationTypeVocabPrefixes() === [] || $this->getMirrorFields() === []) {
            return false;
        }
        return true;
    }



    public function isValidRelationBundle(string $bundle, $fields=[], bool $omit_fields_check = false): bool{
        if(!$this->validBasicRelationConfig()){
            return false;
        }

        $bundle_prefix = $this->getRelationBundlePrefix();

        if(!str_starts_with($bundle, $bundle_prefix)) {
            return false;
        }

        if($omit_fields_check){
            return true;
        }
        if (empty($fields)) {
            $fields = $this->getFieldDefinitions('node', $bundle);
        }
       
        foreach($this->getRelatedEntityFields() as $related_entity_field){
            if(!isset($fields[$related_entity_field])){
                return false;
            }
            $field = $fields[$related_entity_field];
            
            if ($field->getType() != 'entity_reference') {
                return false;
            }
        
            $target_bundles = $this->getFieldTargetBundles($field);
            if(count($target_bundles) != 1 || str_starts_with($target_bundles[0], $bundle_prefix)){
                return false;
            }           
        }   
        return true;
    }



    public function isValidTypedRelationBundle(string $bundle, array $fields = []): bool{
        if(!$this->validTypedRelationConfig()){
            return false;
        }

        if (empty($fields)) {
            $fields = $this->getFieldDefinitions('node', $bundle);
        }

        
        $target_bundles = $this->getFieldTargetBundles($fields[$this->getRelationTypeField()]);
          
        if(count($target_bundles) != 1 || !$this->isValidRelationTypeVocab($target_bundles[0])){
                return false;
            }

        return true;

    }



    public function isValidRelationTypeVocab(string $vocab, array $fields = []): bool{
        return $this->getRelationVocabType($vocab, $fields) !== null;
    }



    public function getRelationVocabType(string $vocab, array $fields = []): ?string{
        if(!$this->validTypedRelationConfig()){
            return null;
        }

        $is_self = str_starts_with($vocab, $this->getRelationTypeVocabPrefixes('self'));
        $is_cross = str_starts_with($vocab, $this->getRelationTypeVocabPrefixes('cross')); 
 
        if (!$is_self && !$is_cross) {
            return null;
        }

        if (empty($fields)) {
            $fields = $this->getFieldDefinitions('taxonomy_term', $vocab);
        }

        $mirror_fields_config = $this->getMirrorFields();
        $mirror_reference_field = $mirror_fields_config['mirror_reference_field'];
        $mirror_string_field = $mirror_fields_config['mirror_string_field'];

        if($is_self && isset($fields[$mirror_reference_field]) && !isset($fields[$mirror_string_field])){
            $field_config = $fields[$mirror_reference_field];
        } elseif($is_cross && isset($fields[$mirror_string_field]) && !isset($fields[$mirror_reference_field])){
            $field_config = $fields[$mirror_string_field];
        } else {
            return null;
        }

        if (!($field_config instanceof FieldConfig)) {
            return null;
        }

        $field_type = $field_config->getType();
       
        if($is_self && $field_type == 'entity_reference'){
            $target_bundles = $field_config->get('settings')['handler_settings']['target_bundles'] ?? [];
            if(!is_array($target_bundles) || !in_array($vocab, $target_bundles)){
                return null;
            }
            return 'self';
        } elseif($is_cross && $field_type == 'string'){
            return 'cross';
        } else {
            return null;
        }
    }



    public function getRelationBundleInfo(string $bundle, array $fields = []):array {
        if (empty($fields)) {
            $fields = $this->getFieldDefinitions('node', $bundle);
        }
        if (!$this->isValidRelationBundle($bundle, $fields)) {
            return [];
        }
        $related_bundles = [];

        foreach ($this->getRelatedEntityFields() as $field_name) {
            $related_bundles[$field_name] = $this->getFieldTargetBundles($fields[$field_name]);
  
        }

        $info = [
            'related_bundles_per_field' => $related_bundles,
            'has_relationtype' => false
        ];

        $target_bundles = $this->getFieldTargetBundles($fields[$this->getRelationTypeField()]);            
        
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
            $fields = $this->getFieldDefinitions('taxonomy_term', $vocab);
        }
        switch($this->getRelationVocabType($vocab, $fields)){
            case 'cross':
                $result = [
                    'mirror_field_type' => 'string',
                    'mirror_field_name' => $this->getMirrorFields('string'),
                    'referencing_type' => 'crossreferencing'
                ];
                break;
            case 'self':
                $result = [
                    'mirror_field_type' => 'entity_reference_selfreferencing',
                    'mirror_field_name' => $this->getMirrorFields('reference'),
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
        if($target_node === NULL){
            $target_node = $this->routeMatch->getParameter('node');
            if(!($target_node instanceof Node)){
                return [];
            }
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



    public function getEntityFormForeignKeyField(array $entity_form, FormStateInterface $form_state):?string {
        $relation_entity = $entity_form['#entity'];
        $form_entity = $form_state->getFormObject()->getEntity();
    
        if(!($relation_entity instanceof Node) || !($form_entity instanceof Node)){
            return null;
        } 

        $relation_type = $relation_entity->getType();
        $form_entity_type = $form_entity->getType();


        if($relation_entity->isNew()){
            $connection_info = $this->getBundleConnectionInfo($relation_type, $form_entity_type) ?? [];
        } else {
            $connection_info = $this->getEntityConnectionInfo($relation_entity, $form_entity) ?? [];
        }

        if(empty($connection_info) || empty($connection_info['join_fields'])){
            return null;
        }

        $join_fields = $connection_info['join_fields'];

        if(!is_array($join_fields)){
            return null;
        }

        if(count($join_fields) > 1){
            $join_fields = [$this->getRelatedEntityFields(1)];
        }

        return $join_fields[0];
    }


    public function getReferencingRelations(Node $target_node, string $relation_bundle, array $join_fields = []): array {
        $target_bundle = $target_node->type();
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
                $result = array_merge($result, $relations);
            }
        }
        return $result;
    }


    protected function getConfig(): ImmutableConfig {
        return $this->configFactory->get('relationship_nodes.settings');
    }



    protected function hasRequiredKeys(array $array, array $subfields, bool $requireNonEmpty = false): bool {
        if(!is_array($array)){
            return false;
        } 

        foreach ($subfields as $subfield) {
            if (!array_key_exists($subfield, $array)) {
                return false;
            }

            if ($requireNonEmpty && empty($array[$subfield])) {
                return false;
            }
        }

        return true;
    }



    protected function getFieldDefinitions(string $entityType, string $bundle): array {
        return $this->fieldManager->getFieldDefinitions($entityType, $bundle);;
    }



    protected function getStorage(string $entity_type):EntityStorageInterface  {
        return $this->entityTypeManager->getStorage($entity_type);
    }



    protected function getFieldTargetBundles(FieldConfig $field_config):array {
        if($field_config->getType() != 'entity_reference'){
            return [];
        }

        $settings = $field_config->getSettings() ?? [];
        $handler_settings = $settings['handler_settings'] ?? [];
        $target_bundles = $handler_settings['target_bundles'] ?? [];
       
        return is_array($target_bundles) ? array_values($target_bundles) : [];
    }
}