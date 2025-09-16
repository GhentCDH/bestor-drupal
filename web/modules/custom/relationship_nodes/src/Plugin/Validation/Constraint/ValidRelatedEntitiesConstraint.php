<?php

namespace Drupal\relationship_nodes\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;



/**
 * Provides a Custom Desk Constraint.
 *
 * @Constraint(
 *   id = "valid_related_entities_constraint",
 *   label = @Translation("Term Mirror Validation", context = "Validation"),
 * )
 */
class ValidRelatedEntitiesConstraint extends Constraint {
   public $incomplete = 'A relation cannot have empty related item fields.';
   public $selfReferring = 'An item cannot have a relation with itself.';
}

