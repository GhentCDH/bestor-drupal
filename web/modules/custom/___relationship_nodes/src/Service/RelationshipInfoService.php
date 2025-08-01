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
    
    protected array $fieldDefinitionsCache = [];

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


    public function getRelatedEntityFields(): array {
        return $this->getConfig()->get('related_entity_fields') ?? [];
    }


    public function getRelatedEntityField(int $i): string {
        $fields = array_values($this->getRelatedEntityFields());
        return $fields[$i - 1] ?? '';
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


    public function allConfigAvailable(): bool {
        if($this->getRelationBundlePrefix() === '' || $this->getRelationTypeField() === '' || $this->getRelationFormMode() === '' || $this->getRelatedEntityFields() === [] || $this->getRelationTypeVocabPrefixes() === [] || $this->getMirrorFields() === []) {
            return false;
        }
        return true;
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
            if(!isset($all_bundle_fields[$related_entity_field]) || !($all_bundle_fields[$related_entity_field] instanceof FieldConfig)){
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
        if(!($all_bundle_fields[$relation_field] instanceof FieldConfig)){
            return false;
        }
        $target_bundles = $this->getFieldTargetBundles($all_bundle_fields[$relation_field]);

        if(count($target_bundles) != 1 || !$this->isValidRelationTypeVocab($target_bundles[0],$this->getFieldDefinitions('taxonomy_term',$target_bundles[0]) )){
            return false;
        }

        return true;       
    }


    public function isValidRelationTypeVocab(string $vocab, array $vocab_fields): bool{
        return $this->getRelationVocabType($vocab, $vocab_fields) !== null;
    }


    public function getRelationVocabType(string $vocab, array $vocab_fields): ?string{
        if(!$this->validTypedRelationConfig() || empty($vocab_fields)){
            return null;
        }

        $is_self = str_starts_with($vocab, $this->getVocabPrefixSelf());
        $is_cross = str_starts_with($vocab, $this->getVocabPrefixCross()); 
        if (!$is_self && !$is_cross) {
            return null;
        }

        $mirror_fields_config = $this->getMirrorFields();
        $mirror_reference_field = $mirror_fields_config['mirror_reference_field'];
        $mirror_string_field = $mirror_fields_config['mirror_string_field'];

        if($is_self && isset($vocab_fields[$mirror_reference_field]) && !isset($vocab_fields[$mirror_string_field])){
            $field_def = $vocab_fields[$mirror_reference_field];
        } elseif($is_cross && isset($vocab_fields[$mirror_string_field]) && !isset($vocab_fields[$mirror_reference_field])){
            $field_def = $vocab_fields[$mirror_string_field];
        } else {
            return null;
        }

        if (!($field_def instanceof FieldConfig)) {
            return null;
        }

        $field_type = $field_def->getType();
        $targets = $this->getFieldTargetBundles($field_def);
        if($is_self && count($targets) ==1 && $targets[0] == $vocab){
            return 'self';
        } elseif($is_cross && $field_type == 'string'){
            return 'cross';
        } else {
            return null;
        }
    }


    public function getRelationBundleInfo(string $bundle, array $all_bundle_fields = []):array {
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


    public function getRelationVocabInfo(string $vocab, array $vocab_fields): array {
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

  
    public function getRelationInfoForTargetBundle(string $bundle): array { 
        $relationshipInfo = [];
        $all_node_bundles = \Drupal::service("entity_type.bundle.info")->getBundleInfo('node');
        if ($this->allConfigAvailable() === false || !isset($all_node_bundles[$bundle])) {
            return $relationshipInfo;
        }
      
        $related_entity_fields = $this->getRelatedEntityFields();

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
                    $relationshipInfo[$bundle_name] = $relationship_node_info;
                }      
            }
        }
        return $relationshipInfo;
    }


    public function getConnectionInfo(Node $relation_node, bool $current = true, ?Node $target_node = NULL): array {
        if(($current && $target_node != null) || (!$current && $target_node == null) || !$this->isValidRelationBundle($relation_node->getType())){
            return [];
        }

        if($current == true){
            $target_node = $this->routeMatch->getParameter('node');
        }
        
        if(!($target_node instanceof Node)){
            return [];
        }
        
        $relation_info =$this->getRelationBundleInfo($relation_node->getType());
        
        if(empty($relation_info)){
            return [];
        }

        $join_fields = [];
        foreach($this->getRelatedEntityFields() as $related_entity_field){
            if(!in_array($target_node->getType(), $relation_info['related_bundles_per_field'][$related_entity_field])){
                continue;
            }
            $referenced_entities = $relation_node->get($related_entity_field)->referencedEntities() ?? [];
            if(empty($referenced_entities) || !in_array($target_node, $referenced_entities)){
                continue;
            }
            $join_fields[] = $related_entity_field;
        }

        $result = [];

        switch(count($join_fields)){
            case 0:
                $result = [
                    'relation_state' => 'unrelated',
                ];
                break;
            case 1:
                $result = [
                    'relation_state' => 'related',
                    'join_field' => $join_fields[0],
                    'relation_info' => $relation_info,
                ];
                break;
            default: 
                $result = [];
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
        $key = $entityType . '::' . $bundle;
        if (!isset($this->fieldDefinitionsCache[$key])) {
            $this->fieldDefinitionsCache[$key] = $this->fieldManager->getFieldDefinitions($entityType, $bundle);
        }
        return $this->fieldDefinitionsCache[$key];
    }


    protected function getStorage(string $entity_type):EntityStorageInterface  {
        return $this->entityTypeManager->getStorage($entity_type);
    }


    protected function getFieldTargetBundles(FieldConfig $field_config):array {
        if($field_config->getType() != 'entity_reference'){
            return [];
        }
        $settings = $field_config->getSettings();
        $handler_settings = $settings['handler_settings'] ?? [];
        $target_bundles = $handler_settings['target_bundles'] ?? [];
        
        return is_array($target_bundles) ? array_values($target_bundles) : [];
    }

    
     protected function ensureFieldDefinitions(string $entity_type, string $bundle, array $fields): array {
       dpm($fields);
       dpm(empty($fields));
        if (empty($fields)) {;
            return $this->getFieldDefinitions($entity_type, $bundle);
        }
        return $fields;
    }
}




