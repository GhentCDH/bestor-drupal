<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormSimple;
use Drupal\relationship_nodes\Form\RelationExtendedEntityInlineForm;
use Drupal\relationship_nodes\Service\RelationSanitizer;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Plugin implementation of the 'ief_validated_relations_simple' widget.
 *
 * @FieldWidget(
 *   id = "ief_validated_relations_simple",
 *   label = @Translation("Inline entity form - Validated relations (simple)"),
 *   description = @Translation("Entity form with validation to skip incomplete relation entities."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class IefValidatedRelationsSimple extends InlineEntityFormSimple {

    protected RelationSanitizer $relationSanitizer;

    public function __construct(
        $plugin_id,
        $plugin_definition,
        FieldDefinitionInterface $field_definition,
        array $settings,
        array $third_party_settings,
        EntityTypeBundleInfoInterface $entity_type_bundle_info,
        EntityTypeManagerInterface $entity_type_manager,
        EntityDisplayRepositoryInterface $entity_display_repository,
        RelationSanitizer $relationSanitizer
        ) {
        parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_type_bundle_info, $entity_type_manager, $entity_display_repository);
        $this->relationSanitizer = $relationSanitizer;
    }

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
            $container->get('relationship_nodes.relation_sanitizer')
        );
    }

    public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
        $element = parent::formElement($items, $delta, $element, $form, $form_state);
        if(!empty($element['inline_entity_form'])){
            $element['inline_entity_form']['#relation_extension_widget'] = true;
        }
        return $element;
    }

    public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
        parent::extractFormValues($items, $form, $form_state);
        $field_name = $this->fieldDefinition->getName();
        $parents = array_merge($form['#parents'], [$field_name]);
        $ief_id = $this->makeIefId($parents);
        $form_state->set(['inline_entity_form', $ief_id, 'relation_extension_widget'], true);
    }




 
    public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
        return $this->relationSanitizer->clearEmptyRelationsFromInput($values, $form_state, $this->fieldDefinition->getName());
    }
}