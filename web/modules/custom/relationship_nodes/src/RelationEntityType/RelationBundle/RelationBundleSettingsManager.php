<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;


class RelationBundleSettingsManager {
    
    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }


    public function getProperty(ConfigEntityBundleBase $entity, string $property): bool|string|null {
        if (!$this->isRelationProperty($property)) {
            return null;
        }
        return $entity->getThirdPartySetting('relationship_nodes', $property, null);
    }
    
    public function getProperties(ConfigEntityBundleBase $entity): ?array {
        return $entity->getThirdPartySettings('relationship_nodes');
    }

 
    public function setProperty(ConfigEntityBundleBase $entity, string $property, mixed $value): void {
        if (!$this->isRelationProperty($property)) {
            return;
        }
        $entity->setThirdPartySetting('relationship_nodes', $property, $value);
        $entity->save();
    }


    public function setProperties(ConfigEntityBundleBase $entity, array $properties) : void {
        if(!empty($properties)){
            foreach($properties as $property => $value){
                if (!$this->isRelationProperty($property)) {
                    continue;
                }
                $entity->setThirdPartySetting('relationship_nodes', $property, $value);
            }
            $entity->save();
        } else {
            $rn_settings = $entity->getThirdPartySettings('relationship_nodes');
            foreach ($rn_settings as $rn_setting => $value) {
                $entity->unsetThirdPartySetting('relationship_nodes', $rn_setting);
            }
            $entity->save();
        }   
    }  

    public function isRelationNodeType(ConfigEntityBundleBase|string $node_type) : bool{
        if(!$node_type = $this->ensureNodeType($node_type)){
            return false;
        }
        $value = $this->getProperty($node_type, 'enabled');
        return !empty($value);
    }


    public function isTypedRelationNodeType(NodeType|string $node_type) : bool{
        if(!$node_type = $this->ensureNodeType($node_type)){
            return false;
        }
        $typed = $this->getProperty($node_type, 'typed_relation');
        return $this->isRelationNodeType($node_type) && !empty($typed);
    }


    public function isRelationVocab(ConfigEntityBundleBase|string $vocab): bool{
        if(!$vocab = $this->ensureVocab($vocab)){
            return false;
        }
        $value = $this->getProperty($vocab, 'enabled');
        return !empty($value);
    }


    public function getRelationVocabType(Vocabulary|string $vocab): ?string{
        if(!$vocab = $this->ensureVocab($vocab)){
            return null;
        }
        return $this->getProperty($vocab, 'referencing_type') ?? null;
    }


    public function isMirroringVocab(Vocabulary|string $vocab): bool{
        $relation_vocab_type = $this->getRelationVocabType($vocab);
        return in_array($relation_vocab_type, ['string', 'entity_reference']);
    }


    public function isRelationEntity(ConfigEntityBundleBase|string $entity): bool {
        return ($this->isRelationNodeType($entity) || $this->isRelationVocab($entity));
    }


    public function ensureNodeType(ConfigEntityBundleBase|string $node_type):?NodeType{ 
        if(is_string($node_type)){
           $node_type = $this->entityTypeManager->getStorage('node_type')->load($node_type);
        }
        if(!$node_type instanceof NodeType){
            return null;
        }
        return $node_type;
    }


    public function ensureVocab(ConfigEntityBundleBase|string $vocab):?Vocabulary{ 
        if(is_string($vocab)){
            $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vocab);
        }
        if(!$vocab instanceof Vocabulary){
            return null;
        }
        return $vocab;
    }


    public function autoCreateTitle(NodeType|string $node_type) : bool {
        $node_type = $this->ensureNodeType($node_type);
        if (!$node_type || !$this->isRelationNodeType($node_type)) {
            return false;
        }
        $auto_title = $this->getProperty($node_type, 'auto_title');
        return !empty($auto_title);
    }
 
    
    public function isRelationProperty(string $property) : bool{
        $properties = ['rn_created', 'enabled', 'typed_relation', 'auto_title', 'referencing_type'];
        return in_array($property, $properties);
    }


    public function getEntityTypeObjectClass(string|ConfigEntityBundleBase $entity_type):?string{
        if($entity_type instanceof ConfigEntityBundleBase){
            $entity_type = $entity_type->getEntityTypeId();
        }   
        switch($entity_type){
            case 'node_type':
                return 'node';
            case 'taxonomy_vocabulary':
                return 'taxonomy_term';
            default:
                return null;
        }
    }

    public function getConfigFileEntityClasses(string $config_name):?array{
        if (str_starts_with($config_name, 'node.type.')) {
            $bundle_name = substr($config_name, strlen('node.type.'));
            $entity_type_id = 'node_type';
        } elseif (str_starts_with($config_name, 'taxonomy.vocabulary.')) {
            $bundle_name = substr($config_name, strlen('taxonomy.vocabulary.'));
            $entity_type_id = 'taxonomy_vocabulary';
        } else {
            return null;
        }
        return [
            'bundle' => $bundle_name,
            'entity_type' => $entity_type_id,
            'object_class' => $this->getEntityTypeObjectClass($entity_type_id)
        ];
    }


    public function getEntityTypeConfigPrefix(string|ConfigEntityBundleBase $entity_type):?string{
        if($entity_type instanceof ConfigEntityBundleBase){
            $entity_type = $entity_type->getEntityTypeId();
        }   
        switch($entity_type){
            case 'node_type':
                return 'node.type.';
            case 'taxonomy_vocabulary':
                return  'taxonomy.vocabulary.';
            default:
                return null;
        }
    }

    
    public function getCimProperty(array $config_data, string $property): bool|string|null {
        if (!$this->isRelationProperty($property) || empty($this->getCimProperties($config_data))) {
            return null;
        }
        return $this->getCimProperties($config_data)[$property] ?? null;
    }
    

    public function getCimProperties(array $config_data): ?array {
        return !empty($config_data['third_party_settings']['relationship_nodes'])
                    ? $config_data['third_party_settings']['relationship_nodes']
                    : null;
    }


       public function isCimRelationEntity(array $config_data): bool {
        $value = $this->getCimProperty($config_data, 'enabled');
        return !empty($value);
    }


    public function isCimTypedRelationNodeType(array $config_data) : bool{
        if(!$this->isCimRelationEntity($config_data) || !isset($config_data['typed_relation'])){
            return false;
        }
        $typed = $this->getCimProperty($config_data, 'typed_relation');
        return !empty($typed);
    }

    public function getCimRelationVocabType(array $config_data): ?string{
        if(!$this->isCimRelationEntity($config_data) || !isset($config_data['referencing_type'])){
            return null;
        }
        return $this->getCimProperty($config_data, 'referencing_type');
    }


    public function isCimMirroringVocab(array $config_data): bool{
        $relation_vocab_type = $this->getRelationVocabType($config_data);
        return in_array($relation_vocab_type, ['string', 'entity_reference']);
    }
}