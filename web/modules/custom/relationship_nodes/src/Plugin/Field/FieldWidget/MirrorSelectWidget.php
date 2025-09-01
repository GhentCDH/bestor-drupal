<?php

namespace Drupal\relationship_nodes\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\relationship_nodes\RelationEntity\RelationTermMirroring\MirrorTermProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 *
 * @FieldWidget(
 *   id = "mirror_select_widget",
 *   label = @Translation("Mirror Select Widget"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class MirrorSelectWidget extends OptionsSelectWidget {
  protected MirrorTermProvider $mirrorProvider;

  public function __construct(
    $plugin_id, $plugin_definition, 
    FieldDefinitionInterface $field_definition, 
    array $settings, 
    array $third_party_settings, 
    ?ElementInfoManagerInterface $elementInfoManager = NULL, 
    MirrorTermProvider $mirrorProvider
    ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $elementInfoManager);
    $this->mirrorProvider = $mirrorProvider;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.element_info'),
      $container->get('relationship_nodes.mirror_term_provider')
    );
  }
    
  
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    if(
      !$this->mirrorProvider->elementSupportsMirroring($items, $form, $form_state) || 
      !$this->mirrorProvider->mirroringRequired($form, $form_state)
    ) {
      return $element;
    }

    $element['#options'] = $this->mirrorProvider->getMirrorOptions($element['#options']);
     
    return $element;
  }  
}