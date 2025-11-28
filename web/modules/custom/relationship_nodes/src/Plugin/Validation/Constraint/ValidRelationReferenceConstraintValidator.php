<?php

namespace Drupal\relationship_nodes\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;


/**
 * Validates the ValidRelatedEntitiesConstraint constraint.
 */
class ValidRelationReferenceConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $error_type = \Drupal::service('relationship_nodes.relation_entity_validator')->checkRelationsValidity($entity);
    if (!empty($error_type)) {
      $this->context->addViolation($constraint->$error_type);  
    }
  } 
}