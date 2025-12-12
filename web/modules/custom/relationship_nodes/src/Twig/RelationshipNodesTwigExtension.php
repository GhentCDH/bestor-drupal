<?php

namespace Drupal\relationship_nodes\Twig;

use Drupal\relationship_nodes\Display\RelationshipTwigFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for relationship nodes rendering.
 */
class RelationshipNodesTwigExtension extends AbstractExtension {

  protected RelationshipTwigFormatter $formatter;

  /**
   * Constructs a RelationshipNodesTwigExtension object.
   */
  public function __construct(RelationshipTwigFormatter $formatter) {
    $this->formatter = $formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('rn', [$this, 'rn']),
    ];
  }

  /**
   * Twig function for relationship nodes operations.
   *
   * @param string $operation
   *   The operation to perform:
   *   - 'relation_fields_list': Get all relation field names
   *   - 'formatted_relations': Get formatted relationship data
   * @param mixed ...$args
   *   Additional arguments for the operation.
   *
   * @return mixed
   *   The result of the operation.
   */
  public function rn(string $operation, ...$args) {
    return match($operation) {
      'relation_fields_list' => $this->formatter->getAllRelationFields(...$args),
      'formatted_relations' => $this->formatter->getFormattedRelationships(...$args),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return 'relationship_nodes.twig_extension';
  }
}