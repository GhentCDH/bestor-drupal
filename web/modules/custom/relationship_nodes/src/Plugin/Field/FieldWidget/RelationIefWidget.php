<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormSimple;
use Drupal\relationship_nodes\Form\Entity\RelationEntityFormHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;



/**
 * Plugin implementation of the 'relation_extended_ief_widget' widget.
 *
 * @FieldWidget(
 *   id = "relation_extended_ief_widget",
 *   label = @Translation("Relation extended IEF simple widget"),
 *   description = @Translation("Entity form provided by the relationship nodes module - extends the inline entity form simple widget."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class RelationIefWidget extends InlineEntityFormSimple {

  protected RelationEntityFormHandler $relationFormHandler;


  /**
   * Constructs a RelationIefWidget object.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param RelationEntityFormHandler $relationFormHandler
   *   The relation entity form handler.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    RelationEntityFormHandler $relationFormHandler
    ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition, 
      $field_definition, 
      $settings, 
      $third_party_settings, 
      $entity_type_bundle_info, 
      $entity_type_manager, 
      $entity_display_repository
    );
    $this->relationFormHandler = $relationFormHandler; 
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
      $configuration['third_party_settings'],
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('relationship_nodes.relation_entity_form_handler')
    );
  }


  /**
   * {@inheritdoc}
   *
   * Sets #relation_extended_widget on the widget container so that
   * getRelationExtendedWidgetFields() can identify this widget via the form
   * render array in all phases (build, AJAX rebuild, submit rebuild).
   *
   * This flag cannot be reliably set in formElement() or in form state:
   * InlineEntityFormSimple::formElement() resets the entire IEF form state to []
   * on every delta call, overwriting anything written in a previous delta.
   * formMultipleElements() is called once per field and its return value becomes
   * $form[$field_name]['widget'], making it the only safe location for the flag.
   *
   * This method also snapshots all current entity IDs into a dedicated form state
   * key (rn_summary_entities) before parent::formMultipleElements() is called.
   * That snapshot is used by extractFormValues() to restore non-rendered entities
   * and prevent getRemovedRelations() from falsely treating them as deleted.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $ief_id = $this->makeIefId(array_merge($form['#parents'], [$field_name]));

    // Snapshot entity IDs before parent calls formElement() per delta, each of
    // which resets $form_state['inline_entity_form'][$ief_id] to [].
    $all_entities = [];
    foreach ($items as $delta => $item) {
      if ($item->entity) {
        $all_entities[$delta] = $item->entity;
      }
    }
    $form_state->set(['rn_summary_entities', $ief_id], $all_entities);

    $element = parent::formMultipleElements($items, $form, $form_state);

    // Flag on the widget container, readable as $form[$field_name]['widget']['#relation_extended_widget'].
    $element['#relation_extended_widget'] = TRUE;

    return $element;
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    if (!empty($element['inline_entity_form'])) {
      $element['inline_entity_form']['#relation_extended_widget'] = true;
    }
    return $element;
  }


  /**
   * {@inheritdoc}
   *
   * Safety net: parent::extractFormValues() replaces $items with only the entities
   * that had an IEF element in the form render array. Any entity rendered as a
   * summary row (no IEF subform) is dropped, which would cause getRemovedRelations()
   * to falsely delete it from the DB.
   *
   * This override re-adds those non-rendered entities to $items using the snapshot
   * stored by formMultipleElements() in rn_summary_entities. The snapshot must be
   * read from there — NOT from widget_state['entities'], which parent already
   * overwrote with only the rendered entities before this code runs.
   *
   * Non-rendered entities are intentionally NOT added to widget_state['entities']
   * so that parent::doSubmit() does not re-save them.
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    parent::extractFormValues($items, $form, $form_state);

    $field_name = $this->fieldDefinition->getName();
    $ief_id = $this->makeIefId(array_merge($form['#parents'], [$field_name]));

    $all_entities = $form_state->get(['rn_summary_entities', $ief_id]) ?? [];
    $current_ids = array_filter(array_map(
      fn($item) => $item->entity?->id(),
      iterator_to_array($items)
    ));

    foreach ($all_entities as $entity) {
      if (!in_array($entity->id(), $current_ids, TRUE)) {
        $items->appendItem(['target_id' => $entity->id(), 'entity' => $entity]);
      }
    }
  }


  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    return $this->relationFormHandler->clearEmptyRelationsFromInput($values, $form, $form_state, $this->fieldDefinition);
  }
}