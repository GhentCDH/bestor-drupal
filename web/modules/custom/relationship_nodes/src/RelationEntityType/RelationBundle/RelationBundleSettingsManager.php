<?php

namespace Drupal\relationship_nodes\RelationEntityType\RelationBundle;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;


class RelationBundleSettingsManager {
    
    use StringTranslationTrait;

    protected EntityTypeManagerInterface $entityTypeManager;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }

    
    private function isRelationProperty(string $property) : bool{
        $properties = ['rn_created', 'enabled', 'typed_relation', 'auto_title', 'referencing_type'];
        return in_array($property, $properties);
    }


    public function getProperty(ConfigEntityBundleBase $entity, string $property): bool|string|null {
        if (!$this->isRelationProperty($property)) {
            return null;
        }
        return $entity->getThirdPartySetting('relationship_nodes', $property, null);
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


    public function getEntityTypeId(ConfigEntityBundleBase $entity): string {
        if ($entity instanceof NodeType) {
            return 'node';
        } elseif ($entity instanceof Vocabulary) {
            return 'taxonomy_term';
        }
        return '';
    }


    public function isRelationNodeType(ConfigEntityBundleBase|string $node_type) : bool{
        if(!$node_type = $this->ensureNodeType($node_type)){
            return false;
        }
        $value = $this->getProperty($node_type, 'enabled');
        return !empty($value);
    }


    public function isTypedRelationNode(NodeType|string $node_type) : bool{
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


    public function getRelationVocabType(Vocabulary|string $vocab): string{
        if(!$vocab = $this->ensureVocab($vocab)){
            return '';
        }
        return $this->getProperty($vocab, 'referencing_type') ?? '';
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


    public function removeRnThirdPartySettings(ConfigEntityBase $entity): void {
        $rn_settings = $entity->getThirdPartySettings('relationship_nodes');
        foreach ($rn_settings as $key => $value) {
            $entity->unsetThirdPartySetting('relationship_nodes', $key);
        }
        if (method_exists($entity, 'setLocked')) {
            $entity->setLocked(FALSE);
        }
        $entity->save();
    }
}