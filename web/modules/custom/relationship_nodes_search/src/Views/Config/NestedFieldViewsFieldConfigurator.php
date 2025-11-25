<?php

namespace Drupal\relationship_nodes_search\Views\Config;

use Drupal\search_api\Entity\Index;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes_search\FieldHelper\CalculatedFieldHelper;
use Drupal\relationship_nodes_search\FieldHelper\NestedFieldHelper;
use Drupal\relationship_nodes_search\FieldHelper\ChildFieldEntityReferenceHelper;

/**
 * Configuration form builder for Views field display.
 */
class NestedFieldViewsFieldConfigurator extends NestedFieldViewsConfiguratorBase {

  protected ChildFieldEntityReferenceHelper $childReferenceHelper;

  /**
   * Constructs a NestedFieldViewsFieldConfigurator object.
   *
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver service.
   * @param NestedFieldHelper $nestedFieldHelper
   *   The nested field helper service.
   * @param CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper service.
   * @param ChildFieldEntityReferenceHelper $childReferenceHelper
   *   The child reference helper service.
   */
  public function __construct(
    FieldNameResolver $fieldNameResolver,
    NestedFieldHelper $nestedFieldHelper,
    CalculatedFieldHelper $calculatedFieldHelper,
    ChildFieldEntityReferenceHelper $childReferenceHelper
  ) {
    parent::__construct($fieldNameResolver, $nestedFieldHelper, $calculatedFieldHelper);
    $this->childReferenceHelper = $childReferenceHelper;
  }

  /**
   * Build configuration form for field display in Views.
   * 
   * High-level method that orchestrates the entire form building process.
   *
   * @param array &$form
   *   The form array.
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   Parent field name.
   * @param array $child_field_names
   *   Available child field names.
   * @param array $saved_settings
   *   Current saved settings.
   */
  public function buildFieldDisplayForm(
    array &$form,
    Index $index,
    string $sapi_fld_nm,
    array $child_field_names,
    array $saved_settings
  ): void {
    // PREPARE: Build field configurations with context
    $field_configs = $this->prepareViewsFieldConfigurations(
      $index,
      $sapi_fld_nm,
      $child_field_names,
      $saved_settings
    );

    // Extract global settings
    $global_settings = [
      'sort_by_field' => $saved_settings['sort_by_field'] ?? '',
      'group_by_field' => $saved_settings['group_by_field'] ?? '',
      'template' => $saved_settings['template'] ?? 'relationship-field',
    ];

    // RENDER: Build form from configurations
    $this->buildConfigurationForm(
      $form,
      $field_configs,
      $global_settings,
      [
        'wrapper_key' => 'relation_display_settings',
        'field_settings_key' => 'field_settings',
        'context_prefix' => $this->getViewsContextPrefix(),
        'show_template' => TRUE,
        'show_grouping' => TRUE,
        'show_sorting' => TRUE,
      ]
    );
  }

  /**
   * Gets linkable fields for Views field display context.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent field name.
   * @param array $child_field_names
   *   Available field names.
   *
   * @return array
   *   Array of linkable field names.
   */
  protected function getLinkableFields(Index $index, string $sapi_fld_nm, array $child_field_names): array {
    $linkable = [];
    
    foreach ($child_field_names as $field_name) {
      if ($this->childReferenceHelper->nestedFieldCanLink($index, $sapi_fld_nm, $field_name)) {
        $linkable[] = $field_name;
      }
    }
    
    return $linkable;
  }
}