<?php

namespace Drupal\relationship_nodes_search\Service\Field;

/**
 * Service for managing calculated fields in relationship searches.
 * 
 * Provides a central registry of calculated field names and their properties,
 * including entity types and formatting rules.
 */
class CalculatedFieldHelper {

    /**
     * Field name definitions for calculated fields.
     */
    private const CALCULATED_FIELDS = [
        'this_entity' => [
        'id' => 'calculated_this_id',
        'name' => 'calculated_this_name',
        ],
        'related_entity' => [
        'id' => 'calculated_related_id',
        'name' => 'calculated_related_name',
        ],
        'relation_type' => [
        'name' => 'calculated_relation_type_name',
        ],
    ];


    /**
     * Entity type mapping for calculated fields.
     */
    private const ENTITY_TYPE_MAP = [
        'this_entity' => 'node',
        'related_entity' => 'node',
        'relation_type' => 'taxonomy_term',
    ];


    /**
     * Gets calculated field names.
     *
     * @param string|null $calc_entity_key
     *   Optional entity key filter ('this_entity', 'related_entity', 'relation_type').
     * @param string|null $property
     *   Optional property filter ('id', 'name').
     * @param bool $flatten
     *   Whether to return a flat array of values.
     *
     * @return array
     *   Array of calculated field names in requested format.
     */
    public function getCalculatedFieldNames(?string $calc_entity_key = null, ?string $property = null, bool $flatten = false):array{

        // Return all fields
        if ($calc_entity_key === null) {
            if ($property === null) {
                return $flatten ? $this->flattenFieldsArray(self::CALCULATED_FIELDS) : self::CALCULATED_FIELDS;
            }
            
            $result = [];
            foreach (self::CALCULATED_FIELDS as $key => $props) {
                if (isset($props[$property])) {
                    $result[$key] = $props[$property];
                }
            }
            return $flatten ? array_values($result) : $result;
        }

        // Return specific entity fields
        $calculated_entity = self::CALCULATED_FIELDS[$calc_entity_key] ?? [];
        
        if (empty($calculated_entity)) {
            return [];
        }
        
        // Filter by property if specified
        if ($property === null) {
            return $flatten ? array_values($calculated_entity) : $calculated_entity;
        }
        
        return isset($calculated_entity[$property]) ? [$calculated_entity[$property]] : [];
             
    }


    /**
     * Gets the target entity type for a calculated field.
     *
     * @param string $child_fld_nm
     *   The calculated field name.
     *
     * @return string|null
     *   The entity type ('node', 'taxonomy_term'), or NULL if not a calculated field.
     */
    public function getCalculatedFieldTargetType(string $child_fld_nm): ?string {
        $calc_fld_ids = $this->getCalculatedFieldNames(null,'id');
        if(!in_array($child_fld_nm, $calc_fld_ids)){
            return null;
        }
        foreach ($calc_fld_ids as $calc_entity_key => $calc_fld_id) {
            if ($calc_fld_id === $child_fld_nm) {
                return self::ENTITY_TYPE_MAP[$calc_entity_key] ?? null;
            }
        }
        return null;
    }

    
    /**
     * Checks if a field name is a calculated field.
     *
     * @param string $child_fld_nm
     *   The field name to check.
     *
     * @return bool
     *   TRUE if the field is calculated by this module.
     */
    public function isCalculatedChildField(string $child_fld_nm):bool{
        $all_calculated = $this->getCalculatedFieldNames(null, null, true);
        return in_array($child_fld_nm, $all_calculated, true);
    }


    /**
     * Formats a calculated field name into a human-readable label.
     *
     * Converts "calculated_this_id" to "This id".
     *
     * @param string $calc_fld_nm
     *   The calculated field name.
     *
     * @return string
     *   The formatted label.
     */
    public function formatCalculatedFieldLabel($calc_fld_nm): string {
        $label = str_replace(['calculated_', '_'], ['', ' '], $calc_fld_nm);
        return ucfirst(trim($label));
    }


    /**
     * Flattens a nested array of calculated fields.
     *
     * @param array $calc_fld_arr
     *   Nested array of calculated fields.
     *
     * @return array
     *   Flat array of field names.
     */
    protected function flattenFieldsArray(array $calc_fld_arr):array{
        $result = [];
        foreach ($calc_fld_arr as $props) {
            foreach($props as $calc_fld_nm){
                if(!empty($calc_fld_nm)){
                    $result[] = $calc_fld_nm;
                }
            }
        }
        return $result;
    }
}