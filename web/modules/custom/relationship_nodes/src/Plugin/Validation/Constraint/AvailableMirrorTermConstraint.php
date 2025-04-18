<?php

namespace Drupal\relationship_nodes\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;



/**
 * Provides a Custom Desk Constraint.
 *
 * @Constraint(
 *   id = "Relationship_nodesAvailableMirrorTermConstraint",
 *   label = @Translation("Term Mirror Validation", context = "Validation"),
 * )
 */
class AvailableMirrorTermConstraint extends Constraint {
    public $termAlreadyMirrored = 'The selected mirror term is already linked to another relationship type. Please choose a different mirror term or remove the existing link before proceeding.';
    public $noSelfMirroring = 'A relationship type cannot mirror itself. For one-way (unidirectional) relationships, leave the mirror field blank. For directional relationships, select a different term for the reverse relationship or remove the existing link first.';
}

