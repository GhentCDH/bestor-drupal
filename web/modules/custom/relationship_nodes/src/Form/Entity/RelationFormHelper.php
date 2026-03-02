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
   * Detects which IEF fields use RelationIefWidget by reading the
   * #relation_extended_widget flag from the widget container in the form render
   * array. This flag is set by RelationIefWidget::formMultipleElements(), which
   * is called once per field and whose return value becomes $form[$field]['widget'].
   *
   * Form state cannot be used here: InlineEntityFormSimple::formElement() resets
   * the IEF form state to [] on every delta call, overwriting any flag written in
   * a previous delta. The render array is stable across build, AJAX rebuild, and
   * submit rebuild phases.
   *
   * For top-level node forms, makeIefId() produces implode('-', [$field_name]),
   * which equals the field name — the same key used in the form render array.
   *
   * @param array $form
   *   The form array (passed by reference).
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Array of IEF IDs that belong to relation extended widgets.
   */
  public function getRelationExtendedWidgetFields(array &$form, FormStateInterface $form_state): array {
    $result = [];
    foreach ($this->getAllIefWidgetFields($form_state) as $ief_id) {
      $widget = $form[$ief_id]['widget'] ?? NULL;
      if (is_array($widget) && !empty($widget['#relation_extended_widget'])) {
        $result[] = $ief_id;
      }
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