<?php

namespace Drupal\customviewfilters\Plugin\views\filter;

use Drupal\config_views\Plugin\views\filter\StringEntity;
use Drupal\relationship_nodes\Service\RelationshipInfoService;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides an extended string filter with extra operators.
 *
 * @ViewsFilter("custom_extended_string_filter")
 */

class CustomExtendedStringFilter extends StringEntity {

 /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();
    $operators['NOT_STARTS_WITH'] = [
      'title' => $this->t('Does not start with'),
      'short' => $this->t('not begins'),
      'method' => 'opNotStartsWith',
      'values' => 1,
    ];
    $operators['LINKED_TO_BUNDLE'] = [
      'title' => $this->t('The vocabulary is linked to a bundle'),
      'short' => $this->t('Give the machine name of the bundle (only applicable to a vid field)'),
      'method' => 'linkedToBundle',
      'values' => 1,
    ];
    return $operators;
  }

  /**
     * Implements the 'NOT_STARTS_WITH' operator with EntityQuery workaround.
     *
     * @param string $field
     *   The field to filter on.
     */
  protected function opNotStartsWith($field) {
    $value = $this->value;
    $query = $this->query;
    $helpquery = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->getQuery();

    $matching_vids = $helpquery
    ->accessCheck(TRUE)
    ->condition($field, $value, 'STARTS_WITH')
    ->execute();

    if (empty($matching_vids)) {
      return;
    }
    $query->condition($this->options['group'], $field, $matching_vids, 'NOT IN');
  }

  protected function linkedToBundle($field) {
    if($field != 'vid') {
      return;
    }
    $bundle_info_service = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
    $value = $this->value;
    if (empty($value) || !is_string($value) || !in_array($value, array_keys($bundle_info_service))) {
      return;
    }
    $query = $this->query;
    $bundle_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $value);
    $matching_vids = [];
    foreach($bundle_fields as $bundle_field){
      if($bundle_field->getType() == 'entity_reference' && $bundle_field->getSetting('target_type') == 'taxonomy_term') {
        $matching_vids = array_merge($matching_vids, $bundle_field->getSettings()['handler_settings']['target_bundles']);
      }
    }

    $relationship_nodes = \Drupal::service('relationship_nodes.relationship_info_service')->relationshipInfoForRelatedItemNodeType(\Drupal::entityTypeManager()->getDefinition('node'), $value);
    if (is_array($relationship_nodes) && !empty($relationship_nodes)) {
      foreach($relationship_nodes as $relationship_node) {
        if (isset($relationship_node['relationtypeinfo']) && is_array($relationship_node['relationtypeinfo']) && isset($relationship_node['relationtypeinfo']['vocabulary']) && $relationship_node['relationtypeinfo']['vocabulary'] != ''){
           $matching_vids[($relationship_node['relationtypeinfo']['vocabulary'])] = $relationship_node['relationtypeinfo']['vocabulary'];
        }
      }
    }
    $query->condition($this->options['group'], $field, array_unique($matching_vids), 'IN');
  }
}