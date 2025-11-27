<?php

namespace Drupal\relationship_nodes\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;


/**
 * Validates that related entities in relationships are valid.
 *
 * Ensures that:
 * - Relation nodes have all required related entity fields filled.
 * - Entities do not have self-referential relationships.
 *
 * @Constraint(
 *   id = "valid_related_entities_constraint",
 *   label = @Translation("Valid Related Entities", context = "Validation"),
 * )
 */
class ValidRelatedEntitiesConstraint extends Constraint {
  /**
   * The message for incomplete relations.
   *
   * @var string
   */
  public $incomplete = 'A relation cannot have empty related item fields.';

  /**
   * The message for self-referential relations.
   *
   * @var string
   */
  public $selfReferring = 'An item cannot have a relation with itself.';
}