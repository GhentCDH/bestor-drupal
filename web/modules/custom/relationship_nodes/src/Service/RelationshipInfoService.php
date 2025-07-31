<?php


namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;


class RelationshipInfoService {
    protected ConfigFactoryInterface $configFactory;
    protected EntityTypeManagerInterface $entityTypeManager;
    protected EntityFieldManagerInterface $fieldManager;
    protected EntityTypeBundleInfoInterface $bundleInfo;

    public function __construct(
        ConfigFactoryInterface $configFactory,
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $fieldManager,
        EntityTypeBundleInfoInterface $bundleInfo
    ) {
        $this->configFactory = $configFactory;
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldManager = $fieldManager;
        $this->bundleInfo = $bundleInfo;
    }

    protected function getConfig(): ImmutableConfig {
        return $this->configFactory->get('relationship_nodes.settings');
    }

    public function getRelationTypeField(): string {
        return $this->getConfig()->get('relationship_type_field') ?? '';
    }


    public function getRelationFormMode(): string {
        return $this->getConfig()->get('relationship_form_mode') ?? '';
    }


    public function getRelatedEntityFields(): array {
        return $this->getConfig()->get('related_entity_fields') ?? [];
    }

    public function getRelationTypeVocabPrefixes(): array {
        return $this->getConfig()->get('relationship_taxonomy_prefixes') ?? [];
    }

    public function getVocabPrefixSelf(): string {
        return $this->getRelationTypeVocabPrefixes()['selfreferencing_vocabulary_prefix'] ?? '';
    }

    public function getVocabPrefixCross(): string {
        return $this->getRelationTypeVocabPrefixes()['crossreferencing_vocabulary_prefix'] ?? '';
    }

    public function getMirrorFields(): array {
        return $this->getConfig()->get('mirror_fields') ?? [];
    }

    public function getRelationBundlePrefix(): string {
        return $this->getConfig()->get('relationship_node_bundle_prefix') ?? '';
    }

    public function validBasicRelationConfig(): bool{
        $bundle_prefix = $this->getRelationBundlePrefix();
        $related_fields = $this->getRelatedEntityFields();
        if($bundle_prefix == '' || $related_fields == []){
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

    public function allConfigAvailable(): bool {
        if($this->getRelationBundlePrefix() === '' || $this->getRelationTypeField() === '' || $this->getRelationFormMode() === '' || $this->getRelatedEntityFields() === [] || $this->getRelationTypeVocabPrefixes() === [] || $this->getMirrorFields() === []) {
            return false;
        }
        return true;
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



    public function getAllRelationBundles(): array{
        $node_types = $this->getStorage('node_type')->loadMultiple();   
        $relation_bundles = [];
        foreach($node_types as $node_type_id => $node_type){
            if ($this->isValidRelationBundle($node_type_id)) {
                $relation_bundles[] = $node_type_id;
            }   
        }
        return $relation_bundles;
    }



    public function isValidRelationBundle(string $bundle, array $all_bundle_fields = []): bool{
        if(!$this->validBasicRelationConfig()){
            return false;
        }

        $bundle_prefix = $this->getRelationBundlePrefix();
        if(!str_starts_with($bundle, $bundle_prefix)) {
            return false;
        }


        $all_bundle_fields = $this->ensureFieldDefinitions('node', $bundle, $all_bundle_fields);

        foreach($this->getRelatedEntityFields() as $related_entity_field){
            if(!isset($all_bundle_fields[$related_entity_field])){
                return false;
            }
            
            $field = $all_bundle_fields[$related_entity_field];
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

    public function isValidTypedRelationBundle(string $bundle, array $all_bundle_fields = []): bool{
        if(!$this->validTypedRelationConfig()){
            return false;
        }
        $all_bundle_fields = $this->ensureFieldDefinitions('node', $bundle, $all_bundle_fields);
        $relation_field = $this->getRelationTypeField();
        $target_bundles = $this->getFieldTargetBundles($all_bundle_fields[$relation_field]);

        if(count($target_bundles) != 1 || !$this->isValidRelationTypeVocab($target_bundles[0])){
            return false;
        }

        return true;       
    }

    public function isValidRelationTypeVocab(string $vocab, array $all_vocab_fields = []): bool{
        return $this->getRelationVocabType($vocab, $all_vocab_fields) !== null;
    }

    public function getRelationVocabType(string $vocab, array $all_vocab_fields = []): ?string{
        if(!$this->validTypedRelationConfig()){
            return null;
        }

        $prefixes = $this->getRelationTypeVocabPrefixes();
        $is_self = str_starts_with($vocab, $this->getVocabPrefixSelf());
        $is_cross = str_starts_with($vocab, $this->getVocabPrefixCross()); 
        if (!$is_self && !$is_cross) {
            return null;
        }

        $all_vocab_fields = $this->ensureFieldDefinitions('taxonomy_term', $vocab, $all_vocab_fields);

        $mirror_fields_config = $this->getMirrorFields();
        $mirror_reference_field = $mirror_fields_config['mirror_reference_field'];
        $mirror_string_field = $mirror_fields_config['mirror_string_field'];

        if($is_self && isset($all_vocab_fields[$mirror_reference_field]) && !isset($all_vocab_fields[$mirror_string_field])){
            $field_def = $all_vocab_fields[$mirror_reference_field];
        } elseif($is_cross && isset($all_vocab_fields[$mirror_string_field]) && !isset($all_vocab_fields[$mirror_reference_field])){
            $field_def = $all_vocab_fields[$mirror_string_field];
        } else {
            return null;
        }

        if (!($field_def instanceof FieldConfig)) {
            return null;
        }

        $field_type = $field_def->getType();

        if($is_self && $this->getFieldTargetBundles($field_def)[0] == $vocab){
            return 'self';
        } elseif($is_cross && $field_type == 'string'){
            return 'cross';
        } else {
            return null;
        }
    }



  public function getRelationBundleInfo(string $bundle, array $all_bundle_fields = []):array {
    $info = [];

    $all_bundle_fields = $this->ensureFieldDefinitions('node', $bundle, $all_bundle_fields);

    if (!$this->isValidRelationBundle($bundle, $all_bundle_fields)) {
        return $info;
    }
    
    $related_bundles = [];

    foreach ($this->getRelatedEntityFields() as $field_name) {
        $related_bundles[$field_name] = $this->getFieldTargetBundles($all_bundle_fields[$field_name])[0];
    }

    $info = [
        'related_bundles_per_field' => $related_bundles,
        'includes_relationtype' => false
    ];
    
    if(!$this->isValidTypedRelationBundle($bundle, $all_bundle_fields)){
        return $info;
    }

    $vocab = $this->getFieldTargetBundles($all_bundle_fields[$this->getRelationTypeField()])[0];            
    $vocab_info = $this->getRelationVocabInfo($vocab);
    if(empty($vocab_info)){
        return $info;
    }           

    $info['includes_relationtype'] = true;
    $info['relationtypeinfo'] = $vocab_info;
    $info['relationtypeinfo']['vocabulary'] = $vocab;
                
    return $info;
  }
 


  public function getRelationVocabInfo(string $vocab, array $all_vocab_fields = []): array {
    if(!$this->validTypedRelationConfig() || !$this->isValidRelationTypeVocab($vocab, $all_vocab_fields)){
        return [];
    }
    $result = [];
    $mirror_field_names = $this->getMirrorFields();
    switch($this->getRelationVocabType($vocab, $all_vocab_fields)){
        case 'cross':
            $result['mirrorfieldtype'] = 'string';
            $result['mirrorfieldname'] = $mirror_field_names['mirror_string_field'];
            $result['referencingtype'] = 'crossreferencing';
            break;
        case 'self':
            $result['mirrorfieldtype'] = 'entity_reference_selfreferencing';
            $result['mirrorfieldname'] = $mirror_field_names['mirror_reference_field'];
            $result['referencingtype'] = 'selfreferencing';
            break;
    }
    return $result;
}


  
    function getRelationInfoForTargetBundle(string $bundle): array { 

        $relation_bundles = $this->getAllRelationBundles();
        $all_bundles_info = \Drupal::service("entity_type.bundle.info")->getBundleInfo('node');
        $info_array = [];
        foreach($relation_bundles as $relation_bundle){
            if (!isset($all_bundles_info[$relation_bundle]['related_bundles_per_field'])) {
                continue;
            }
            $bundle_info = $all_bundles_info[$relation_bundle];
            $related_bundles_per_field = $bundle_info['related_bundles_per_field'];
            $join_fields = [];
            $other_bundle = '';
            $relation_info = [];
            foreach($related_bundles_per_field as $field => $related_bundle){
                if($related_bundle == $bundle){
                    $join_fields[] = $field; 
                } else {
                    $other_bundle = $related_bundle;
                }
            } 
            if (empty($join_fields)) {
                continue;
            }
            $relation_info['join_fields'] = $join_fields;
            $relation_info['related_bundle'] =  $other_bundle;
            $relation_info['relationship_bundle']= $relation_bundle;

            $info_array[$relation_bundle] =  $relation_info;
        }

        return $info_array;
    }


    /**
     * 
     * Deze functie checkt of een relatie node (input) een join field heeft met de huidige node / een opgegeven nid en geeft terug welke.
     */
    function getRelationInfoForNode(Node $relationship_node, ?int $target_nid = NULL, bool $current = true): array {
        $node_info = $this->getRelationBundleInfo($relationship_node->getType());
 
        if(!$relationship_node->id() ||  $this->allConfigAvailable() === false|| !isset($node_info['relationnode']) || !$node_info['relationnode']){
            return [];
        }
        $related_fields = $this->getRelatedEntityFields();
        $joinFields = [];
        $status = '';
        if ($target_nid === NULL) {
            $current_node = \Drupal::routeMatch()->getParameter('node');
        } else {
            $current_node = \Drupal::entityTypeManager()->getStorage('node')->load($target_nid);
        }
        
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

    protected function getFieldDefinitions(string $entityType, string $bundle): array {
        return $this->fieldManager->getFieldDefinitions($entityType, $bundle);
    }

    protected function getStorage(string $entity_type):EntityStorageInterface  {
        return $this->entityTypeManager->getStorage($entity_type);
    }

    protected function getFieldTargetBundles(FieldConfig $field_config):array {
        if($field_config->getType() != 'entity_reference'){
            return [];
        }
        $settings = $field_config->getSettings();
        if(empty($settings['handler_settings']['target_bundles'])){
            return [];
        }
        $target_bundles = $settings['handler_settings']['target_bundles'] ?? [];
        
        return is_array($target_bundles) ? $target_bundles : [];
    }

    protected function ensureFieldDefinitions(string $entity_type, string $bundle, array $fields): array {
        return empty($fields) ? $this->getFieldDefinitions($entity_type, $bundle) : $fields;
    }

}