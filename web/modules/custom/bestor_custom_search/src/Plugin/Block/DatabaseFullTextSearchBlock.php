<?php

namespace Drupal\bestor_custom_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Database Search' block.
 *
 * @Block(
 *   id = "database_full_text_search_block",
 *   admin_label = @Translation("Database Search"),
 * )
 */
class DatabaseFullTextSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\bestor_custom_search\Form\DatabaseFullTextSearchForm');
    
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'views-exposed-form',
          'bef-exposed-form',
          'block-views',
          'block-views-exposed-filter-blocksearch-page-1',
        ],
      ],
      'form' => $form,
    ];
  }

}