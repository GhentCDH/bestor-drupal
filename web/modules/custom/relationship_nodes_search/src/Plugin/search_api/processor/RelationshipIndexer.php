<?php

namespace Drupal\relationship_nodes_search\Plugin\search_api\processor;

use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Adds nested relationship data to specified fields.
 *
 * @SearchApiProcessor(
 *   id = "relationship_indexer",
 *   label = @Translation("Relationship Indexer"),
 *   description = @Translation("Nests relationship data into specified fields."),
 *   stages = {
 *     "add_properties" = 0,
 *     "alter_items" = 0,
 *   }
 * )
 */
class RelationshipIndexer extends ProcessorPluginBase implements ContainerFactoryPluginInterface {

  protected RelationshipInfoService $infoService;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RelationshipInfoService $infoService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->infoService = $infoService;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('relationship_nodes.relationship_info_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $entity = $item->getOriginalObject()->getValue();

    if (!$entity || !$entity->hasField('nid')) {
      return;
    }

    $bundle_prefix = $this->infoService->getRelationshipNodeBundlePrefix();
    $related_entity_fields = $this->infoService->getRelatedEntityFields();
    $rel_type_field = $this->infoService->getRelationshipTypeField();
    $nid = $entity->id();

    // Itereer over alle velden in dit item.
    foreach ($item->getFields() as $field_id => $field) {
      // Enkel velden van het type object verwerken die ook expliciet geselecteerd zijn.
      if ($field->getType() !== 'object') {
        continue;
      }

      $configured_fields = array_map('trim', explode(',', $this->configuration['field_names']));
      if (!in_array($field_id, $configured_fields)) {
        continue;
      }

      // Zoek de relatie nodes die dit entity targetten.
      $query = \Drupal::entityQuery('node')
        ->condition('type', $bundle_prefix . '%', 'LIKE')
        ->condition($related_entity_fields['related_entity_field_1'] . '.target_id', $nid);

      $result = $query->execute();

      if (empty($result)) {
        continue;
      }

      $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($result);
      $nested_data = [];

      foreach ($nodes as $rel_node) {
        $target = $rel_node->get($related_entity_fields['related_entity_field_2'])->entity;
        if (!$target) {
          continue;
        }

        $rel_type = $rel_node->get($rel_type_field)->value;

        $nested_data[] = [
          'type' => $rel_type,
          'target_id' => $target->id(),
          'target_label' => $target->label(),
        ];
      }

      // Zet de geneste objecten als field value.
      $field->addValue($nested_data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'field_names' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['field_names'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Machine names of fields to nest'),
      '#default_value' => $this->configuration['field_names'],
      '#description' => $this->t('Comma-separated list of field machine names to apply relationship nesting to. Only object-type fields will be used.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['field_names'] = $form_state->getValue('field_names');
  }
}
