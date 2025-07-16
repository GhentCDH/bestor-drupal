<?php

namespace Drupal\relationship_nodes_search\Plugin\search_api\processor;

use Drupal\node\Entity\Node;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * @SearchApiProcessor(
 *   id = "relationship_nodes_processor",
 *   label = @Translation("Relationship Nodes Processor"),
 *   description = @Translation("Indexes referenced and referencing entity IDs generically."),
 *   stages = {
 *     "add_properties" = 0,
 *     "pre_index_save" = -10
 *   }
 * )
 */
class RelationshipNodesProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    $properties = [];

    $properties['referenced_entities'] = new ProcessorProperty([
      'label' => $this->t('Referenced entities'),
      'description' => $this->t('IDs of entities this node refers to via entity reference fields.'),
      'type' => 'integer',
      'is_list' => TRUE,
    ]);

    $properties['referencing_entities'] = new ProcessorProperty([
      'label' => $this->t('Referencing entities'),
      'description' => $this->t('IDs of other nodes that reference this node.'),
      'type' => 'integer',
      'is_list' => TRUE,
    ]);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();

    if (!$node instanceof Node) {
      return;
    }

    $referenced_ids = [];
    $referencing_ids = [];

    // Find referenced entity IDs from entity reference fields.
    foreach ($node->getFields() as $field) {
      if ($field->getFieldDefinition()->getType() === 'entity_reference') {
        foreach ($field as $value) {
          if (!empty($value->target_id)) {
            $referenced_ids[] = $value->target_id;
          }
        }
      }
    }

    // Find referencing nodes using reverse entity reference query.
    $referencers = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'relationship') // <== Pas dit aan naar jouw relationele content types
      ->condition('field_related_item', $node->id()) // <== eventueel meerdere velden opnemen
      ->execute();

    $referencing_ids = array_values($referencers);

    if (isset($item->getFields()['referenced_entities'])) {
      $item->getFields()['referenced_entities']->addValues($referenced_ids);
    }

    if (isset($item->getFields()['referencing_entities'])) {
      $item->getFields()['referencing_entities']->addValues($referencing_ids);
    }
  }
}
