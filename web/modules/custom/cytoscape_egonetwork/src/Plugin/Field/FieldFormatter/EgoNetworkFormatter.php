<?php

namespace Drupal\cytoscape_egonetwork\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\cytoscape_egonetwork\Service\EgoNetworkBuilder;
use Drupal\node\NodeInterface;

/**
 * Renders the ego network graph when the field value is TRUE.
 *
 * @FieldFormatter(
 *   id = "cytoscape_egonetwork",
 *   label = @Translation("Ego network graph"),
 *   field_types = {"boolean"},
 * )
 */
class EgoNetworkFormatter extends FormatterBase {

  public static function defaultSettings(): array {
    return [
      'language_unavailable' => 'hide',
    ];
  }

  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['language_unavailable'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Relations unavailable in current language'),
      '#options'       => [
        'hide' => $this->t('Hide'),
        'fade' => $this->t('Show faded'),
      ],
      '#default_value' => $this->getSetting('language_unavailable'),
    ];
    return $form;
  }

  public function settingsSummary(): array {
    $setting = $this->getSetting('language_unavailable');
    $labels  = ['hide' => $this->t('Hidden'), 'fade' => $this->t('Shown faded')];
    return [$this->t('Unavailable languages: @val', ['@val' => $labels[$setting] ?? $setting])];
  }

  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty() || !$items->first()->value) {
      return [];
    }

    $node = $items->getEntity();
    if (!$node instanceof NodeInterface) {
      return [];
    }

    $builder = new EgoNetworkBuilder(
      \Drupal::service('entity_type.manager'),
      \Drupal::service('relationship_nodes.relation_info'),
      \Drupal::service('relationship_nodes.field_name_resolver'),
      \Drupal::service('language_manager'),
      \Drupal::service('relationship_nodes.relationship_data_builder'),
    );

    $graph = $builder->build($node, $this->getSetting('language_unavailable'));
    if (empty($graph['elements'])) {
      return [];
    }

    return [
      '#type'       => 'html_tag',
      '#tag'        => 'div',
      '#attributes' => [
        'class' => ['cytoscape-egonetwork'],
        'style' => 'height:500px',
      ],
      '#attached' => [
        'library'        => ['cytoscape_egonetwork/egonetwork'],
        'drupalSettings' => [
          'cytoscapeEgonetwork' => [
            'graph'  => $graph,
            'layout' => 'cose',
          ],
        ],
      ],
    ];
  }

  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getName() === 'show_ego_network';
  }

}