<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\bestor_content_helper\Service\FacetResultsProvider;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\bestor_content_helper\Service\CustomTranslations;
/**
 * Service for analyzing and formatting node content.
 * 
 * Provides utilities for extracting key data from lemma nodes,
 * calculating reading times, and formatting field values.
 */
class StandardNodeFieldProcessor {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RendererInterface $renderer;
  protected FacetResultsProvider $facetResultsProvider;
  protected CustomTranslations $customTranslations;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RendererInterface $renderer,
    FacetResultsProvider $facetResultsProvider,
    CustomTranslations $customTranslations
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
    $this->facetResultsProvider = $facetResultsProvider;
    $this->customTranslations = $customTranslations;
  }


/**
   * Get formatted key data for a lemma node.
   *
   * @param NodeInterface $node
   *   The lemma node.
   * @param string $display_type
   *   Display type ('full' or 'teaser').
   * @param string|null $search_view_id
   *   Optional: view ID for facet links.
   * @param string|null $search_view_display
   *   Optional: view display ID for facet links.
   *
   * @return array|null
   *   Array of formatted key data elements, or NULL if none.
   *   Structure: ['element_key' => ['value' => string, 'label_key' => string, 'icon' => string]]
   */
  public function getLemmaKeyData(NodeInterface $node, string $display_type = 'full', string $search_view_id = NULL, string $search_view_display = NULL): ? array {
    $node_type = $node->getType();
    $lemma_elements = $this->getLemmaTypeToElementMapping($node_type, $display_type);

    if (!$lemma_elements) {
      return NULL;
    }

    $return_values = [];
    foreach ($lemma_elements as $el_key){
      $el_def = $this->getElementToFieldMapping($el_key);

      if(empty($el_def['fields'])){
        continue;
      }

      $fld_vals = $this->getFieldValues($node, $el_def['fields'], [
        'link_type' => $search_view_id ? 'facet' : NULL,
        'view_id' => $search_view_id,
        'view_display' => $search_view_display,
      ]);

      if ($fld_vals){
        $return_values[$el_key] = [
          'value' => $this->formatLemmaKeyData($fld_vals, $el_key, $display_type),
          'label' => $this->getElementLabel($node, $el_def),
          'icon' => $el_def['icon']
        ];
      }
    }
    
    return empty($return_values) ? NULL : $return_values;
  }


  /**
 * Get element label with fallback logic.
 *
 * @param NodeInterface $node
 *   The node (to get field definition).
 * @param array $element_definition
 *   Element definition with 'label_key' and 'fields'.
 *
 * @return string
 *   Translated label or field label as fallback.
 */
protected function getElementLabel(NodeInterface $node, array $element_definition): string {
  // 1. Try translation key if present
  if (!empty($element_definition['label_key'])) {
    $translation = $this->customTranslations->get($element_definition['label_key']);
    
    if ($translation !== $element_definition['label_key']) {
      return $translation;
    }
  }
  
  // 2. Fallback: use label of first field
  if (!empty($element_definition['fields'])) {
    $first_field = reset($element_definition['fields']);
    
    if ($node->hasField($first_field)) {
      $field_definition = $node->get($first_field)->getFieldDefinition();
      return $field_definition->getLabel();
    }
  }
  
  // 3. Last resort: return element key
  return $element_definition['label_key'] ?? '';
}


  /**
 * Flatten value for string output (handles nested arrays).
 *
 * @param mixed $value
 *   Value to flatten.
 *
 * @return string
 *   Flattened string value.
 */
protected function flattenValue($value): string {
  if (is_array($value)) {
    return implode(', ', array_map([$this, 'flattenValue'], $value));
  }
  return (string) $value;
}



  /**
   * Get field values ready for display.
   *
   * @param NodeInterface $node
   *   The node.
   * @param string|array $field_names
   *   Single field name or array of field names.
   * @param array $options
   *   Options array:
   *   - 'format': 'array' (default) or 'string'
   *   - 'link_type': NULL (default), 'entity', or 'facet'
   *   - 'view_id': View ID for facet links
   *   - 'view_display': View display for facet links
   *
   * @return string|array|null
   *   Formatted values ready for display.
   */
  public function getFieldValues(
    NodeInterface $node, 
    string|array $field_names,
    array $options = []
  ): string|array|null {
    
    // Defaults
    $options += [
      'format' => 'array',
      'link_type' => NULL,
      'view_id' => NULL,
      'view_display' => NULL,
    ];
    
    $is_single = is_string($field_names);
    $field_names = (array) $field_names;
    
    $values = [];
    foreach($field_names as $fld_nm) {
      if (!$node->hasField($fld_nm) || $node->get($fld_nm)->isEmpty()) {
        continue;
      }

      $fld_def = $node->get($fld_nm)->getFieldDefinition();
      
      $value = match($fld_def->getType()) {
        'entity_reference' => $this->getEntityReferenceValue(
          $node,
          $fld_def,
          $options['link_type'],
          $options
        ),
        'datetime' => $this->getDateFieldValue($node, $fld_nm),
        'string', 'list_string' => $this->getStringFieldValue($node, $fld_nm),
        'boolean' => !empty($node->get($fld_nm)->value),
        default => $node->get($fld_nm)->value ?? NULL,
      };
      
      if ($value !== NULL) {
        $values[$fld_nm] = $value;
      }
    }
    
    if (empty($values)) {
      return NULL;
    }
    
    // Single field: return flat
    if ($is_single) {
      $result = reset($values);
    } else {
      $result = $values;
    }
    
    // Format as string if requested
    if ($options['format'] === 'string') {
      return is_array($result) ? implode(', ', array_map([$this, 'flattenValue'], $result)) : $result;
    }
    
    return $result;
  }



/**
 * Get entity reference values as rendered strings.
 */
  protected function getEntityReferenceValue(
    NodeInterface $node, 
    $field_definition, 
    ?string $link_type,
    array $options
  ): ?array {
    $target_type = $field_definition->getSettings()['target_type'];
    if (!in_array($target_type, ['node', 'taxonomy_term'])) {
      return NULL;
    }
    
    $field_name = $field_definition->getName();
    $ids = array_filter(
      array_column($node->get($field_name)->getValue(), 'target_id')
    );
    
    if (empty($ids)) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage($target_type);
    $ents = $storage->loadMultiple($ids);
    $result = [];
    
    foreach($ents as $id => $ent) {
      $title = $target_type === 'taxonomy_term' 
        ? $ent->getName() 
        : $ent->getTitle();
      
      // Determine URL
      $url = NULL;
      
      if ($link_type === 'entity') {
        $url = $ent->toUrl();
      } elseif ($link_type === 'facet' && !empty($options['view_id']) && !empty($options['view_display'])) {
        $facet_id = $this->getFacetQueryIdForField($field_name);
        if ($facet_id) {
          $url = $this->facetResultsProvider->getEnableFacetUrl($options['view_id'], $options['view_display'], $facet_id, $id);
        }
      }
      
      if ($url instanceof Url) {
        $render_array = $this->facetResultsProvider->getEnableFacetLinkRenderArray($url, $title);
        $result[] = (string) $this->renderer->renderPlain($render_array);
      } else {
        $result[] = $title;
      }
    }
    
    return $result;
  }


  /**
   * Get formatted date field values.
   *
   * @param NodeInterface $node
   *   The node (needed for year_only checkbox check).
   * @param string $field_name
   *   Field machine name.
   * @param array|null $field_values
   *   Optional: raw field values (if already retrieved).
   *
   * @return array|null
   *   Array of formatted date strings, or NULL if empty.
   */
  protected function getDateFieldValue(NodeInterface $node, string $field_name): ?array {
    $dates = [];
    foreach ($node->get($field_name)->getValue() as $value) {
      if (empty($value)) {
        continue;
      }

      $date = new \DateTime($value['value']);
      
      $year_only_field = str_replace(['date_start', 'date_end'], ['start_year_only', 'end_year_only'], $field_name);
      $year_only = $node->hasField($year_only_field) && $node->get($year_only_field)->value;
      
      $dates[] = $year_only ? $date->format('Y') : $date->format('d/m/Y');
    }

    return $dates;
  }


    /**
   * Get string field values.
   *
   * @param array $field_values
   *   Raw field values from getValue().
   *
   * @return array|null
   *   Array of string values, or NULL if empty.
   */
  protected function getStringFieldValue(NodeInterface $node, string $field_name): ?array {

    // Multivalue string fields: verzamel alle waarden
    $values = [];
    foreach ($node->get($field_name)->getValue() as $value) {
      if (empty($value)) {
        continue;
      }
      $values[] = $value['value'];
    }

    return $values;
  }


  /**
   * Format lemma key data based on element type.
   *
   * @param array $values
   *   Associative array of field values, keyed by field name.
   * @param string $element_key
   *   Element key (e.g., 'birth_death', 'period', 'location').
   * @param string $display_type
   *   Display type ('full' or 'teaser').
   *
   * @return string
   *   Formatted string.
   */
  protected function formatLemmaKeyData(array $values, string $element_key, string $display_type = 'full'): string {
    if (empty($values)) {
      return '';
    } 
    
    switch ($element_key) {
      case 'birth_death':
        $formatted = $this->lifeDataFormatter($values);
        break;
      case 'period':
        $formatted = $this->periodDataFormatter($values);
        break;
      case 'birth':
      case 'death':
      case 'location':
        $formatted = $this->twoFieldsKeyDataFormatter($values);
        break;
      default:
        $formatted = $this->defaultDataFormatter($values);  
        break;
    }
    
    return $formatted ? $formatted : '';
  }


  /**
   * Default formatter: render first value.
   *
   * @param array $values
   *   Values array.
   *
   * @return string
   *   Rendered value.
   */
  protected function defaultDataFormatter(array $values): string {
     return $this->renderValue(reset($values));
  }


  /**
   * Format two fields as "first (second)".
   *
   * @param array $values
   *   Values array.
   *
   * @return string
   *   Formatted string.
   */
  protected function twoFieldsKeyDataFormatter(array $values): string {
    $values = array_values($values);
    if (count($values) > 1){
      return $this->renderValue($values[0]) . ' (' . $this->renderValue($values[1]) . ')';
    }
    return $this->defaultDataFormatter($values);
  }


  /**
   * Format period data as "start → end".
   *
   * @param array $values
   *   Associative array with 'field_date_start' and 'field_date_end' keys.
   *
   * @return string
   *   Formatted period string.
   */
  protected function periodDataFormatter(array $values): string {
    return trim($this->renderValue($values['field_date_start']) . ' → ' . $this->renderValue($values['field_date_end']));
  }


  /**
   * Format life data as "birth_date (birth_place) → death_date (death_place)".
   *
   * @param array $values
   *   Associative array with date and municipality fields.
   *
   * @return string
   *   Formatted life data string.
   */
  protected function lifeDataFormatter(array $values): string {
    $birth = $this->twoFieldsKeyDataFormatter(array_filter([$values['field_date_start'], $values['field_municipality']]));
    $death = $this->twoFieldsKeyDataFormatter(array_filter([$values['field_date_end'], $values['field_end_municipality']]));
    return  trim($birth . ' → ' . $death);
  }


  /**
   * Render a value (handles arrays, render arrays, Markup, strings).
   *
   * @param mixed $value
   *   Value to render.
   *
   * @return string
   *   Rendered string.
   */
  protected function renderValue($value): string {
    if (empty($value)) {
      return '';
    }

    // Array of values (multi-value field or multiple entity refs)
    if (is_array($value)) {    
      // Array of multiple values - render each and join
      return implode(', ', array_map([$this, 'renderValue'], $value));
    }

    // Markup object
    if ($value instanceof Markup) {
      return (string) $value;
    }

    // Plain string
    return (string) $value;
  }


  /**
   * Map field name to facet query parameter ID.
   *
   * @param string $field_name
   *   Field machine name.
   *
   * @return string|null
   *   Facet query ID or NULL if not mapped.
   */
  public function getFacetQueryIdForField(string $field_name): ?string {
    return match ($field_name) {
      'field_discipline' => 'discipline',
      'field_specialisation' => 'specialisation',
      'field_typology' => 'type',
      'field_country' => 'country',
      'field_municipality' => 'place',
      'field_gender' => 'gender',
      default => NULL
    };
  }


  /**
   * Get element-to-field mapping configuration.
   *
   * @param string $element
   *   Element key.
   *
   * @return array|null
   *   Configuration array with 'fields', 'label_key', 'icon', or NULL.
   */
  public function getElementToFieldMapping(string $element){
    $mapping = [
      'discipline' => [
        'fields' => ['field_discipline'],
        'label_key' => 'lemma_key_discipline',
        'icon' => ''
      ],
      'specialisation' => [
        'fields' => ['field_specialisation'],
        'label_key' => 'lemma_key_specialisation',
        'icon' => ''
      ],
      'typology' => [
        'fields' => ['field_typology'],
        'label_key' => 'lemma_key_typology',
        'icon' => ''
      ],
      'period' => [
        'fields' => ['field_date_start', 'field_date_end'],
        'label_key' => 'lemma_key_period',
        'icon' => ''
      ],
      'location' => [
        'fields' => ['field_country', 'field_municipality'],
        'label_key' => 'lemma_key_location',
        'icon' => 'marker'
      ],
      'birth_death' => [
        'fields' => ['field_date_start', 'field_municipality', 'field_date_end', 'field_end_municipality'],
        'label_key' => 'lemma_key_birth_death',
        'icon' => ''
      ],
      'birth' => [
        'fields' => ['field_date_start', 'field_municipality'],
        'icon' => 'birthdate'
      ],
      'death' => [
        'fields' => ['field_date_end', 'field_end_municipality'],
        'icon' => 'death'
      ],
      'inventor' => [
        'fields' => ['field_inventor'],
        'label_key' => 'lemma_key_inventor',
        'icon' => 'light'
      ],
      'creator' => [
        'fields' => ['field_creator'],
        'label_key' => 'lemma_key_creator',
        'icon' => 'pen'
      ],
      'gender' => [
        'fields' => ['field_gender'],
        'label_key' => 'lemma_key_gender',
        'icon' => ''
      ],
      'alt_names' => [
        'fields' => ['field_alternative_name'],
        'label_key' => 'lemma_key_alt_names',
        'icon' => ''
      ],
    ];
    return empty($mapping[$element]) ? NULL : $mapping[$element];
  }


  /**
   * Get lemma type to elements mapping for display type.
   *
   * @param string $node_type
   *   Node bundle name.
   * @param string $display_type
   *   Display type ('full' or 'teaser').
   *
   * @return array
   *   Array of element keys for this node type and display.
   */
  public function getLemmaTypeToElementMapping(string $node_type, string $display_type){
    $mapping = [
      '_all' => [
        'full' => ['discipline', 'specialisation', 'typology', 'alt_names'],
        'teaser' => [],
      ],
      'concept' => [
        'full' => ['inventor', 'period'],
        'teaser' => ['inventor'],
      ],
      'document' => [
        'full' => ['creator', 'period'],
        'teaser' => ['creator'],
      ],
      'institution' => [
        'full' => ['location', 'period'],
        'teaser' => ['location'],
      ],
      'instrument' => [
        'full' => ['inventor', 'period'],
        'teaser' => ['inventor'],
      ],
      'person' => [
        'full' => ['gender', 'birth_death'],
        'teaser' => ['birth', 'death'],
      ],
      'place' => [
        'full' => ['location', 'period'],
        'teaser' => ['location'],
      ],
      'story' => [
        'full' => ['period'],
        'teaser' => [],
      ],
    ];

    return array_merge(
      empty($mapping['_all'][$display_type]) ? [] : $mapping['_all'][$display_type],
      empty($mapping[$node_type][$display_type]) ? [] : $mapping[$node_type][$display_type]
    );
  }
}