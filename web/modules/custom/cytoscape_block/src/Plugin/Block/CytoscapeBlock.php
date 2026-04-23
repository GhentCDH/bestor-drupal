<?php

namespace Drupal\cytoscape_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Renders a Cytoscape.js graph from drupalSettings.cytoscape_block_data.
 *
 * Any module that wants to display a graph sets:
 * drupalSettings.cytoscape_block_data = { elements: [...], meta: {...} }
 *
 * @Block(
 *   id = "cytoscape_block",
 *   admin_label = @Translation("Cytoscape graph"),
 *   category = @Translation("Graph"),
 * )
 */
class CytoscapeBlock extends BlockBase {

  public function defaultConfiguration(): array {
    return [
      'height' => 500,
      'layout' => 'cose',
    ];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form['height'] = [
      '#type' => 'number',
      '#title' => $this->t('Graph height (px)'),
      '#default_value' => $this->configuration['height'],
      '#min' => 100,
      '#required' => TRUE,
    ];
    $form['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => [
        'cose' => 'CoSE (force-directed, good default)',
        'breadthfirst' => 'Breadth-first (tree-like)',
        'circle' => 'Circle',
        'grid' => 'Grid',
        'concentric' => 'Concentric',
      ],
      '#default_value' => $this->configuration['layout'],
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['height'] = (int) $form_state->getValue('height');
    $this->configuration['layout'] = $form_state->getValue('layout');
  }

  public function build(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['cytoscape-block'],
        'style' => 'height:' . (int) $this->configuration['height'] . 'px',
      ],
      '#attached' => [
        'library' => ['cytoscape_block/graph'],
        'drupalSettings' => [
          'cytoscapeBlock' => [
            'layout' => $this->configuration['layout'],
          ],
        ],
      ],
    ];
  }

}