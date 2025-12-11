<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\bestor_content_helper\Service\FacetResultsProvider;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;

/**
 * Service for analyzing and formatting node content.
 * 
 * Provides utilities for extracting key data from lemma nodes,
 * calculating reading times, and formatting field values.
 */
class NodeContentAnalyzer {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected RendererInterface $renderer;
  protected FacetResultsProvider $facetResultsProvider;


  /**
   * Constructor.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param RendererInterface $renderer
   *   The renderer interface.
   * @param FacetResultsProvider $facetResultsProvider
   *   The facet results provider service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RendererInterface $renderer,
    FacetResultsProvider $facetResultsProvider
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
    $this->facetResultsProvider = $facetResultsProvider;
  }


  /**
   * Count words in node body or description field.
   *
   * @param NodeInterface $node
   *   The node to analyze (should be translation-specific).
   *
   * @return int
   *   Word count.
   */
  public function countContentWords(NodeInterface $node): int {
    // $node = The translation of a node instance.
    $node_fields = $node->getFields();
    if(empty($node_fields) || (empty($node_fields['field_description']) && $node_fields['body'])){
      return 0;
    }
    $words = '';
    if (isset($node_fields['field_description']) && $node->field_description->getFieldDefinition()->getType() === 'entity_reference_revisions') {
      if ($node_fields['field_description']->referencedEntities() > 0) {
        foreach ($node_fields['field_description']->referencedEntities() as $paragraph) {
          if ($paragraph->field_formatted_text->value) {
            $paragraph_text = strip_tags($paragraph->field_formatted_text->value);
            $words = $words ? $words . ' ' . $paragraph_text : $paragraph_text;
          }
        }
      }
    } else if (isset($node_fields['body']) && $node->body->getFieldDefinition()->getType() === 'text_with_summary') {
      $words = strip_tags($node->body->value);
    }
    return str_word_count($words);
  }


  /**
   * Calculate reading time in minutes.
   *
   * @param NodeInterface $node
   *   The node to analyze.
   *
   * @return int
   *   Reading time in minutes.
   */
  protected function getReadingTime(NodeInterface $node): int {
    return ceil($this->countContentWords($node) / 250);
  }


  /**
   * Get formatted reading time string.
   *
   * @param NodeInterface $node
   *   The node to analyze.
   *
   * @return string
   *   Formatted reading time (e.g., "5 min." or "< 1 min.").
   */
  public function getFormattedReadingTime(NodeInterface $node){
    $int = $this->getReadingTime($node);
    if(empty($int)){
      $int = '< 1';
    }
    return $int . ' min.'; 

  }


  /**
   * Check if bundle is a lemma type.
   *
   * @param string $bundleName
   *   The node bundle name.
   *
   * @return bool
   *   TRUE if lemma, FALSE otherwise.
   */
  public function isLemma(string $bundleName): bool {
    $lemmas = ['concept', 'document', 'institution', 'instrument', 'person', 'place', 'story'];
    return in_array($bundleName, $lemmas);
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

      $fld_vals = $this->getFieldValues($node, $el_def['fields'], $search_view_id, $search_view_display);
      if ($fld_vals){
        $return_values[$el_key] = [
          'value' => $this->formatLemmaKeyData($fld_vals, $el_key, $display_type),
          'label_key' => $el_def['label_key'],
          'icon' => $el_def['icon']
        ];
      }
    }
    
    return empty($return_values) ? NULL : $return_values;
  }


  /**
   * Get field values - auto-detects field type and formats accordingly.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string|array $field_names
   *   Single field name or array of field names.
   * @param string|null $view_id
   *   Optional: view ID for facet links (entity references only).
   * @param string|null $view_display
   *   Optional: view display ID for facet links.
   *
   * @return array|null
   *   Field values. For single field: flat array. For multiple: keyed by field name.
   */
  public function getFieldValues(
    NodeInterface $node, 
    string|array $field_names,
    ?string $view_id = NULL, 
    ?string $view_display = NULL
  ): ?array {
    // Convert single string to array
    $is_single = is_string($field_names);
    $field_names = (array) $field_names;
    
    $values = [];
    foreach($field_names as $fld_nm){
      if (!$node->hasField($fld_nm) || $node->get($fld_nm)->isEmpty()) {
        continue;
      }

      $fld_def = $node->get($fld_nm)->getFieldDefinition();
      $fld_vals = $node->get($fld_nm)->getValue();

      $value = match($fld_def->getType()) {
        'entity_reference' => $this->getEntityReferenceValue(
          $fld_vals, 
          $fld_def->getSettings()['target_type'],
          $view_id, 
          $view_display, 
          $this->getFacetQueryIdForField($fld_nm)
        ),
        'datetime' => $this->getDateFieldValue($node, $fld_nm, $fld_vals),
        'string', 'list_string' => $this->getStringFieldValue($fld_vals),
        'boolean' => !empty($fld_vals[0]['value']),
        default => $fld_vals[0]['value'] ?? NULL,
      };
      
      if($value !== NULL){
        $values[$fld_nm] = $value;
      }
    }
    
    // Return flat array for single field, associative for multiple
    if ($is_single) {
      return $values ? reset($values) : NULL;
    }
    
    return $values ?: NULL;
  }


  /**
   * Get entity reference field values.
   *
   * @param array $field_values
   *   Raw field values from getValue().
   * @param string $target_type
   *   Target entity type ('node' or 'taxonomy_term').
   * @param string|null $view_id
   *   Optional: view ID for facet links.
   * @param string|null $view_display
   *   Optional: view display ID for facet links.
   * @param string|null $facet_query_id
   *   Optional: facet query parameter ID.
   *
   * @return array|null
   *   Array of entity titles or render arrays, or NULL if invalid type.
   */
  protected function getEntityReferenceValue(
    array $field_values, 
    string $target_type, 
    ?string $view_id = NULL, 
    ?string $view_display = NULL, 
    ?string $facet_query_id = NULL
  ): ?array {
    if(!in_array($target_type, ['node', 'taxonomy_term'])){
      return NULL;
    }

    $ids = [];
    foreach($field_values as $field_value) {
      if(!empty($field_value['target_id'])){
       $ids[] = $field_value['target_id'];
      }
    }

    if (empty($ids)){
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage($target_type);
    $ents = $storage->loadMultiple($ids);
    $result = [];
    foreach($ents as $id => $ent) {
      $title = $target_type === 'taxonomy_term' ? $ent->getName() : $ent->getTitle();
      if($view_id !== NULL && $view_display !== NULL && $facet_query_id !== NULL) {
        $url = $this->facetResultsProvider->getEnableFacetUrl($view_id, $view_display, $facet_query_id, $id);
        if($url instanceof Url){
          $result[] = $this->facetResultsProvider->getEnableFacetLinkRenderArray($url, $title);
          continue;
        }
      }
      $result[] = $title;
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
  protected function getDateFieldValue(NodeInterface $node, string $field_name, ?array $field_values = NULL): ?array {
    if($field_values === NULL) {
      if (!$node->hasField($field_name) || empty($node->get($field_name)->getValue())) {
        return NULL;
      }
      $field_values = $node->get($field_name)->getValue();
    }

    $dates = [];
    foreach ($field_values as $value) {
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
  protected function getStringFieldValue(array $field_values): ?array {

    // Multivalue string fields: verzamel alle waarden
    $values = [];
  foreach ($field_values as $value) {
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
      // Check if it's a render array (has #type or #markup)
      if (isset($value['#type']) || isset($value['#markup'])) {
        // Single render array - render it
        return (string) $this->renderer->renderPlain($value);
      }
      
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
   * Convert entity reference field to comma-separated string.
   *
   * @param NodeInterface $node
   *   The node.
   * @param string $field_name
   *   Field machine name.
   * @param string $target_entity_type
   *   Target entity type.
   *
   * @return string|null
   *   Comma-separated entity titles, or NULL.
   */
  public function entityRefFieldToResultString(NodeInterface $node, string $field_name, string $target_entity_type): ?string{
    $array = $this->getFieldValues($node, $field_name);
    if(empty($array)){
      return NULL;
    }
    return implode(', ', $array);
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