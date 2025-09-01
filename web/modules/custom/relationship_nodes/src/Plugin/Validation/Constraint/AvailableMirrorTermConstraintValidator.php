<?php

namespace Drupal\relationship_nodes\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;


class AvailableMirrorTermConstraintValidator extends ConstraintValidator {
    public function validate($value, Constraint $constraint) {
        if($value->target_id != null){    
            $updated_term_id = $value->getParent()->getEntity()->id();
            $updated_term_mirror_id = $value->target_id;     
            if($updated_term_id == $updated_term_mirror_id){
                $this->context->addViolation($constraint->noSelfMirroring);
            }         
            $all_existing_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($value->getParent()->getEntity()->bundle(), 0, NULL, TRUE);
            foreach ($all_existing_terms as $loop_term) {
                $loop_term_id = $loop_term->id();
                $mirror_reference_field = \Drupal::service('relationship_nodes.field_name_resolver')->getMirrorFields('self');
                $loop_term_mirror_id = $loop_term->$mirror_reference_field->target_id;
                if($updated_term_id != $loop_term_id && $updated_term_mirror_id == $loop_term_mirror_id){
                    $this->context->addViolation($constraint->termAlreadyMirrored);              
                }
            }
        }
    } 
}