<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes\RelationEntity\UserInterface\RelationshipDataDisplayBuilder;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'relationship_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "relationship_formatter",
 *   label = @Translation("Relationship Formatter"),
 *   description = @Translation("Display relationship nodes with their connected entities."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class RelationshipFormatter extends EntityReferenceFormatterBase implements ContainerFactoryPluginInterface {

  protected RelationshipDataDisplayBuilder $displayBuilder;
  protected FieldNameResolver $fieldNameResolver;

  /**
   * Constructs a RelationshipFormatter object.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param RelationshipDataDisplayBuilder $displayBuilder
   *   The relationship data display builder.
   * @param FieldNameResolver $field_name_resolver
   *   The field name resolver.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    RelationshipDataDisplayBuilder $displayBuilder,
    FieldNameResolver $field_name_resolver
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->displayBuilder = $displayBuilder;
    $this->fieldNameResolver = $field_name_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('relationship_nodes.relationship_data_display_builder'),
      $container->get('relationship_nodes.field_name_resolver')
    );
  }

  public static function defaultSettings() {
    return [
      'show_relation_type' => TRUE,
      'show_field_labels' => TRUE,
      'link_entities' => TRUE,
      'group_by_type' => FALSE,
      'separator' => ', ',
    ] + parent::defaultSettings();
  }

  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['show_relation_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show relation type'),
      '#default_value' => $this->getSetting('show_relation_type'),
    ];

    $elements['show_field_labels'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show field labels'),
      '#default_value' => $this->getSetting('show_field_labels'),
    ];

    $elements['link_entities'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to entities'),
      '#default_value' => $this->getSetting('link_entities'),
    ];

    $elements['group_by_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Group by relation type'),
      '#default_value' => $this->getSetting('group_by_type'),
    ];

    $elements['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Separator'),
      '#default_value' => $this->getSetting('separator'),
      '#size' => 10,
    ];

    return $elements;
  }

  public function settingsSummary() {
    $summary = [];

    if ($this->getSetting('show_relation_type')) {
      $summary[] = $this->t('Show relation type');
    }
    if ($this->getSetting('link_entities')) {
      $summary[] = $this->t('Link to entities');
    }
    if ($this->getSetting('group_by_type')) {
      $summary[] = $this->t('Group by type');
    }

    return $summary;
  }

  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    if ($items->isEmpty()) {
      return $elements;
    }

    // Collect relation nodes
    $relation_nodes = [];
    foreach ($items as $item) {
      if ($relation_node = $item->entity) {
        $relation_nodes[] = $relation_node;
      }
    }

    if (empty($relation_nodes)) {
      return $elements;
    }

    // Build data using shared service
    $relationships = $this->displayBuilder->buildRelationshipData($relation_nodes, [
      'show_relation_type' => $this->getSetting('show_relation_type'),
      'link_entities' => $this->getSetting('link_entities'),
      'separator' => $this->getSetting('separator'),
    ]);

    // Group if configured
    $grouped = [];
    if ($this->getSetting('group_by_type')) {
      $grouped = $this->displayBuilder->groupByRelationType($relationships);
    }

    $elements[0] = [
      '#theme' => 'relationship_field',
      '#relationships' => $grouped ? [] : $relationships,
      '#grouped' => $grouped,
      '#fields' => $this->buildFieldsMetadata(),
      '#summary' => [
        'total' => count($relation_nodes),
        'has_groups' => !empty($grouped),
        'group_count' => count($grouped),
      ],
      '#attached' => [
        'library' => ['relationship_nodes/relationship_field'],
      ],
    ];

    return $elements;
  }

  protected function buildFieldsMetadata(): array {
    $fields = [];
    $related_entity_fields = $this->fieldNameResolver->getRelatedEntityFields();
    $show_labels = $this->getSetting('show_field_labels');

    $weight = 0;
    foreach ($related_entity_fields as $field_name) {
      $fields[$field_name] = [
        'name' => $field_name,
        'label' => $this->formatFieldLabel($field_name),
        'hide_label' => !$show_labels,
        'weight' => $weight++,
      ];
    }

    if ($this->getSetting('show_relation_type')) {
      $fields['relation_type'] = [
        'name' => 'relation_type',
        'label' => $this->t('Type'),
        'hide_label' => !$show_labels,
        'weight' => -1,
      ];
    }

    return $fields;
  }

  protected function formatFieldLabel(string $field_name): string {
    $label = str_replace(['rn_', '_'], ['', ' '], $field_name);
    return ucwords($label);
  }
}