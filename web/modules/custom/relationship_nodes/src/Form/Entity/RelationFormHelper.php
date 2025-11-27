<?php

namespace Drupal\relationship_nodes\Form\Entity;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeForm;
use Drupal\node\Entity\Node;


/**
 * Helper service for relationship node forms.
 */
class RelationFormHelper {

  /**
   * Gets the parent form node entity.
   *
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return Node|null
   *   The parent node or NULL.
   */
  public function getParentFormNode(FormStateInterface $form_state): ?Node {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof NodeForm) {
      return null;
    }
    $build_info = $form_state->getBuildInfo();
    if (!isset($build_info['base_form_id']) || $build_info['base_form_id'] != 'node_form') {
      return null;
    }
    $form_entity = $form_object->getEntity();
    if (!$form_entity instanceof Node) {
      return null;
    }
    return $form_entity;
  }


  /**
   * Gets all IEF widget field names from form state.
   *
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Array of field names.
   */
  public function getAllIefWidgetFields(FormStateInterface $form_state): array {
    $ief_widget_state = $form_state->get('inline_entity_form');
    return is_array($ief_widget_state) ? array_keys($ief_widget_state) : [];
  }


  /**
   * Gets relation extended widget field names.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Array of field names.
   */
  public function getRelationExtendedWidgetFields(array &$form, FormStateInterface $form_state): array {
    $ief_fields = $this->getAllIefWidgetFields( $form_state);
    $result = [];
    foreach ($ief_fields as $field_name) {
      $ief = $form[$field_name]['widget'][0]['inline_entity_form'];
      if (empty($ief) || !isset($ief['#relation_extended_widget']) || $ief['#relation_extended_widget'] != true) {
        continue;
      }
      $result[] = $field_name;
    }
    return $result;
  }


  /**
   * Checks if form is a parent form with IEF subforms.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if parent form with IEF subforms, FALSE otherwise.
   */
  public function isParentFormWithIefSubforms(array &$form, FormStateInterface $form_state): bool {
    return !empty($this->getParentFormNode($form_state)) && !empty($this->getAllIefWidgetFields($form_state));
  }


  /**
   * Checks if form is a parent form with relation subforms.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if parent form with relation subforms, FALSE otherwise.
   */
  public function isParentFormWithRelationSubforms(array &$form, FormStateInterface $form_state): bool {
    return !empty($this->getParentFormNode($form_state)) && !empty($this->getRelationExtendedWidgetFields($form, $form_state));
  }
}