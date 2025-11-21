<?php

namespace Drupal\relationship_nodes_search\QueryHelper;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for filter operator definitions and validation.
 * 
 * Provides operator options for Search API query conditions
 * and validates operator values.
 */
class FilterOperatorHelper {

  use StringTranslationTrait;

  /**
   * Get available operator options for filters.
   *
   * @return array
   *   Operator options keyed by operator value.
   */
  public function getOperatorOptions(): array {
    return [
      '=' => $this->t('Is equal to'),
      '!=' => $this->t('Is not equal to'),
      '<' => $this->t('Is less than'),
      '<=' => $this->t('Is less than or equal to'),
      '>' => $this->t('Is greater than'),
      '>=' => $this->t('Is greater than or equal to'),
      'IN' => $this->t('Is one of'),
      'NOT IN' => $this->t('Is not one of'),
      'BETWEEN' => $this->t('Is between'),
      'NOT BETWEEN' => $this->t('Is not between'),
      '<>' => $this->t('Contains'),
    ];
  }


  /**
   * Check if an operator is valid.
   *
   * @param string $operator
   *   The operator to validate.
   *
   * @return bool
   *   TRUE if valid.
   */
  public function isValidOperator(string $operator): bool {
    return array_key_exists($operator, $this->getOperatorOptions());
  }


  /**
   * Get the default operator.
   *
   * @return string
   *   The default operator value.
   */
  public function getDefaultOperator(): string {
    return '=';
  }


  /**
   * Determines the operator for a field condition from config and form values.
   *
   * Checks if the operator should come from exposed form input or from
   * the field configuration, then sanitizes it.
   *
   * @param array $field_config
   *   The field configuration array.
   * @param string $child_fld_nm
   *   The child field name.
   * @param array $form_values
   *   The form values array (typically $this->value from the filter).
   *
   * @return string
   *   The sanitized operator.
   */
  public function determineFieldOperator(array $field_config, string $child_fld_nm, array $form_values): string {
    // First check exposed form value
    if (!empty($field_config['expose_field_operator']) && isset($form_values[$child_fld_nm]['operator'])) {
      return $this->sanitizeOperator($form_values[$child_fld_nm]['operator']);
    }
    
    // Fall back to configured operator
    if (!empty($field_config['field_operator'])) {
      return $this->sanitizeOperator($field_config['field_operator']);
    }
    
    // Return default if no operator configured
    return $this->getDefaultOperator();
  }


  /**
   * Validate and sanitize an operator value.
   * 
   * Returns the operator if valid, otherwise returns the default operator.
   *
   * @param string|null $operator
   *   The operator to validate.
   *
   * @return string
   *   The validated operator or default.
   */
  public function sanitizeOperator(?string $operator): string {
    if (empty($operator)) {
      return $this->getDefaultOperator();
    }
    
    return $this->isValidOperator($operator) ? $operator : $this->getDefaultOperator();
  }
}