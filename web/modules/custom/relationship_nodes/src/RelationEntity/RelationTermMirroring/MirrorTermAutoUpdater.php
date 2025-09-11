<?php

namespace Drupal\relationship_nodes\RelationEntity\RelationTermMirroring;

use Drupal\taxonomy\TermInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;


class MirrorTermAutoUpdater {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected FieldNameResolver $fieldNameResolver;


    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        FieldNameResolver $fieldNameResolver   
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldNameResolver = $fieldNameResolver;
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
        $ref_field = $this->fieldNameResolver->getMirrorFields('entity_reference');  
        if(empty($ref_field)){
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