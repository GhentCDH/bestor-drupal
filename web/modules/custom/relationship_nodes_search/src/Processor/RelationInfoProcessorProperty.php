<?php

namespace Drupal\relationship_nodes_search\Processor;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\relationship_nodes_search\TypedData\RelationInfoDefinition;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Provides a processor property using RelationInfoDefinition.
 */
class RelationInfoProcessorProperty extends EntityDataDefinition {

  /**
   * {@inheritdoc}
   */
  public function getDataDefinition() {
    // Zorg dat je hier eventuele settings (bv. bundle) doorgeeft aan je definitie.
    $settings = $this->definition['definition_class_settings'] ?? [];
    return new RelationInfoDefinition($settings);
  }

}