<?php

namespace Drupal\relationship_nodes_search\FieldHelper;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Drupal\relationship_nodes_search\Processor\RelationProcessorProperty;
use Drupal\relationship_nodes\RelationEntityType\RelationField\CalculatedFieldHelper;

/**
 * Service for inspecting nested field structures in Search API indices.
 *
 * Provides utilities to analyze field configurations, validate nested paths,
 * and determine field properties for relationship-based search functionality.
 * 
 * "Nested" refers to Elasticsearch nested objects that contain relationship
 * data indexed as child documents within parent entities.
 */
class NestedFieldHelper {

  protected FieldNameResolver $fieldNameResolver;
  protected CalculatedFieldHelper $calculatedFieldHelper; 


  /**
   * Constructs a NestedFieldHelper object.
   *
   * @param FieldNameResolver $fieldNameResolver
   *   The field name resolver service.
   * @param CalculatedFieldHelper $calculatedFieldHelper
   *   The calculated field helper service.
   */
  public function __construct(
    FieldNameResolver $fieldNameResolver, 
    CalculatedFieldHelper $calculatedFieldHelper
  ) {
    $this->fieldNameResolver = $fieldNameResolver;
    $this->calculatedFieldHelper = $calculatedFieldHelper;
  }


  /**
   * Validates and parses a nested field path.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $path
   *   The field path in "parent:child" format.
   *
   * @return array|null
   *   Array with 'parent' and 'child' keys, or NULL if invalid.
   */
  public function validateNestedPath(Index $index, string $path): ?array {
    if (strpos($path, ':') === FALSE) {
      return NULL;
    }
    [$sapi_fld_nm, $child_fld_nm] = explode(':', $path, 2);
    $sapi_fld_nm = trim($sapi_fld_nm);
    $child_fld_nm  = trim($child_fld_nm);

    if (empty($sapi_fld_nm) || empty($child_fld_nm)) {
      return NULL;
    }

    // Validate child exists in parent
    $child_fld_nms = $this->getAllNestedChildFieldNames($index, $sapi_fld_nm);
    if (!in_array($child_fld_nm, $child_fld_nms)) {
      return NULL;
    }

    // Validate field structure
    $sapi_fld = $this->getIndexFieldInstance($index, $sapi_fld_nm);
    if (!$sapi_fld) {
      return NULL;
    }

    $prop = $this->getNestedFieldProperty($sapi_fld);

    if (!$prop instanceof RelationProcessorProperty) {
      return NULL;
    }

    return ['parent' => $sapi_fld_nm, 'child' => $child_fld_nm];
  }
  

  /**
   * Gets the RelationProcessorProperty for a field.
   *
   * @param Field $sapi_fld
   *   The Search API field.
   *
   * @return RelationProcessorProperty|null
   *   The property object, or NULL if not found.
   */
  public function getNestedFieldProperty(Field $sapi_fld): ?RelationProcessorProperty {
    $property = $sapi_fld->getDataDefinition();
    return $property instanceof RelationProcessorProperty ? $property : NULL;
  }


  /**
   * Gets a field instance from an index.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The field name.
   *
   * @return Field|null
   *   The field instance, or NULL if not found.
   */
  public function getIndexFieldInstance(Index $index, string $sapi_fld_nm): ?Field {
    $index_flds = $index->getFields();
  
    if (!isset($index_flds[$sapi_fld_nm])) {
      return NULL;
    }
    
    $sapi_fld = $index_flds[$sapi_fld_nm];
    return $sapi_fld instanceof Field ? $sapi_fld : NULL;     
  }


  /**
   * Converts a colon-separated path to dot-separated format.
   *
   * Converts Search API field paths ("parent:child") to Elasticsearch
   * paths ("parent.child").
   *
   * @param string $str
   *   The string to convert.
   *
   * @return string
   *   The converted string.
   */  
  public function colonsToDots(string $str): string {
    return str_replace(':', '.', $str);
  }
  

  /**
   * Gets all nested child field names for a parent field.
   *
   * @param Index $index
   *   The Search API index.
   * @param string $sapi_fld_nm
   *   The parent field name.
   *
   * @return array
   *   Array of all child field names.
   */
  public function getAllNestedChildFieldNames(Index $index, string $sapi_fld_nm): array {
    $sapi_fld = $this->getIndexFieldInstance($index, $sapi_fld_nm);

    if (!$sapi_fld) {
      return [];
    }
    
    return array_keys($this->getAllNestedChildFieldsConfig($sapi_fld));
  }


  /**
   * Gets all nested child field configuration.
   *
   * @param Field $sapi_fld
   *   The Search API field.
   *
   * @return array
   *   Configuration array of nested child fields.
   */
  protected function getAllNestedChildFieldsConfig(Field $sapi_fld): array {
    if (!$this->isNestedSapiField($sapi_fld)) {
      return [];
    }
    $config = $sapi_fld->getConfiguration();
    return is_array($config) && isset($config['nested_fields']) ? $config['nested_fields'] : [];
  }


  /**
   * Checks if a field is a nested parent field containing child fields.
   *
   * @param Field $sapi_fld
   *   The Search API field.
   *
   * @return bool
   *   TRUE if the field contains nested fields.
   */
  protected function isNestedSapiField(Field $sapi_fld): bool {
    $index_field_config = $sapi_fld->getConfiguration() ?? [];
    if (!is_array($index_field_config) || empty($index_field_config['nested_fields'])) {
      return FALSE;
    }
    return TRUE;
  }
}