<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

class ConfigManager {

    protected ConfigFactoryInterface $configFactory;
    
    public function __construct(ConfigFactoryInterface $configFactory) {
        $this->configFactory = $configFactory;
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
        if (empty( $this->getRelationTypeField())) {
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
}