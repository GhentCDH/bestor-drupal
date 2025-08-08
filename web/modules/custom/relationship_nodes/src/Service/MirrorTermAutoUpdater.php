<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\taxonomy\TermInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\Service\ConfigManager;
use Drupal\relationship_nodes\Service\RelationshipInfoService;

class MirrorTermAutoUpdater {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected ConfigManager $configManager;
    protected RelationshipInfoService $infoService;

  
    public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigManager $configManager, RelationshipInfoService $infoService) {
        $this->entityTypeManager = $entityTypeManager;
        $this->configManager = $configManager;
        $this->infoService = $infoService;
    }

    public function getMirrorReferenceField(TermInterface $term): ?string {
        $vocab_info = $this->infoService->getRelationVocabInfo($term->bundle()) ?? [];
        if(empty($vocab_info) || empty($vocab_info['mirror_field_name'])){
            return null;
        }
        if($vocab_info['mirror_field_name'] !== $this->configManager->getMirrorFields('reference')){
            return null;
        }
        return $vocab_info['mirror_field_name'];
    }

    public function getMirrorTermId(TermInterface $term, string $field, bool $original = false): ?int{
        if ($original) {
            if(!isset($term->original)){
                return null;
            }
            $term = $term->original;
        }
        return $term->$field->target_id ?? null;
    }

    private function getMirrorTermChanges(TermInterface $term, string $field): ?array{
        $orig_id = $this->getMirrorTermId($term, $field, true) ?? null;
        $current_id = $this->getMirrorTermId($term, $field) ?? null;
        return $orig_id === $current_id ? null : ['original'=> $orig_id, 'current'=> $current_id];
    }

    private function loadTerm(int $id): ?TermInterface {
        $tax_storage = $this->entityTypeManager->getStorage('taxonomy_term');
        $term = $tax_storage->load($id);
        return $term instanceof TermInterface ? $term : null;
    }


    public function setMirrorTermLink(TermInterface $term, string $hook): void {
        $ref_field = $this->getMirrorReferenceField($term);      
        if(!$ref_field){
            return;
        }

        $changes = $this->getMirrorTermChanges($term, $ref_field);

        if(!$changes){
            return;
        }

        $term_id = $term->id();
        foreach($changes as $key => $id){
            if(!$id){
                continue;
            }
            $linked_term = $this->loadTerm($id);
            if(!$linked_term){
                continue;
            }
            if($key === 'original'){
                $linked_term->$ref_field->target_id = null;
                
            } elseif ($hook !== 'delete') {
                $linked_term->$ref_field->target_id = $term_id;
            }
            $linked_term->save();        
        }
    }
}