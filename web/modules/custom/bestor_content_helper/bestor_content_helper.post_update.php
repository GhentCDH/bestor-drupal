<?php

/**
 * @file
 * Post update functions for Bestor Content Helper.
 */

declare(strict_types=1);

/**
 * Moves the "Show ego network" checkboxes to field_show_ego_network.
 *
 * The ego network is a view now (network_views) instead of a field of
 * cytoscape_egonetwork. The configuration import that follows the database
 * updates uninstalls cytoscape_egonetwork, which deletes its checkbox values,
 * so they are copied first: the new field is created from config/sync (with
 * the same UUIDs, so the import keeps it) and the values are copied.
 */
function bestor_content_helper_post_update_ego_network_field(): string {
  $database = \Drupal::database();
  if (!$database->schema()->fieldExists('node_field_data', 'show_ego_network')) {
    return 'cytoscape_egonetwork is not installed: nothing to move.';
  }
  $entity_type_manager = \Drupal::entityTypeManager();
  $sync = \Drupal::service('config.storage.sync');
  $names = ['field.storage.node.field_show_ego_network'];
  foreach ($sync->listAll('field.field.node.') as $name) {
    if (str_ends_with($name, '.field_show_ego_network')) {
      $names[] = $name;
    }
  }
  foreach ($names as $name) {
    $entity_type_id = str_starts_with($name, 'field.storage.') ? 'field_storage_config' : 'field_config';
    $storage = $entity_type_manager->getStorage($entity_type_id);
    $record = $sync->read($name);
    if ($record && !$storage->load($record['id'])) {
      $storage->createFromStorageRecord($record)->save();
    }
  }

  // The checkbox was not translatable: one value per node.
  $nids = $database->query('SELECT DISTINCT nid FROM {node_field_data} WHERE show_ego_network = 1')->fetchCol();
  $node_storage = $entity_type_manager->getStorage('node');
  $node_storage->resetCache();
  foreach ($node_storage->loadMultiple($nids) as $node) {
    if ($node->hasField('field_show_ego_network')) {
      $node->set('field_show_ego_network', TRUE);
      $node->setNewRevision(FALSE);
      $node->setSyncing(TRUE);
      $node->save();
    }
  }
  return sprintf('The ego network is shown on %d nodes.', count($nids));
}
