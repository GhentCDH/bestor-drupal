<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\bestor_content_helper\Service\CustomTranslations;
use Drupal\bestor_content_helper\Service\CurrentPageAnalyzer;
use Drupal\filter\Render\FilteredMarkup;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Service for analyzing and formatting node content.
 *
 * Provides utilities for extracting key data from lemma nodes,
 * calculating reading times, and formatting field values.
 */
class StandardNodeFieldProcessor {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected LanguageManagerInterface $languageManager;
  protected RendererInterface $renderer;
  protected FacetResultsProvider $facetResultsProvider;
  protected CustomTranslations $customTranslations;
  protected CurrentPageAnalyzer $pageAnalyzer;


  /**
   * Constructs a StandardNodeFieldProcessor object.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param LanguageManagerInterface $languageManager;
   *   The lanugage manager
   * @param RendererInterface $renderer
   *   The renderer service.
   * @param FacetResultsProvider $facetResultsProvider
   *   The facet results provider service.
   * @param CustomTranslations $customTranslations
   *   The custom translations service.
   * @param CurrentPageAnalyzer $pageAnalyzer
   *   The current page analyzer.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    RendererInterface $renderer,
    FacetResultsProvider $facetResultsProvider,
    CustomTranslations $customTranslations,
    CurrentPageAnalyzer $pageAnalyzer,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->renderer = $renderer;
    $this->facetResultsProvider = $facetResultsProvider;
    $this->customTranslations = $customTranslations;
    $this->pageAnalyzer = $pageAnalyzer;
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
  public function getElementToFieldMapping(string $element): ?array {
    $mapping = [
      'discipline' => [
        'fields' => ['field_discipline'],
        'label_key' => '',
        'icon' => '',
      ],
      'specialisation' => [
        'fields' => ['field_specialisation'],
        'label_key' => '',
        'icon' => '',
      ],
      'typology' => [
        'fields' => ['field_typology'],
        'label_key' => '',
        'icon' => '',
      ],
      'period' => [
        'fields' => ['field_date_start', 'field_date_end'],
        'label_key' => 'lemma_key_period',
        'icon' => '',
      ],
      'location' => [
        'fields' => ['field_municipality', 'field_country'],
        'label_key' => 'lemma_key_location',
        'icon' => 'marker',
      ],
      'birth_death' => [
        'fields' => ['field_date_start', 'field_municipality', 'field_date_end', 'field_end_municipality'],
        'label_key' => 'lemma_key_birth_death',
        'icon' => '',
      ],
      'birth' => [
        'fields' => ['field_date_start', 'field_municipality'],
        'label_key' => '',
        'icon' => 'birthdate',
      ],
      'death' => [
        'fields' => ['field_date_end', 'field_end_municipality'],
        'label_key' => '',
        'icon' => 'death',
      ],
      'inventor' => [
        'fields' => ['field_inventor'],
        'label_key' => '',
        'icon' => 'light',
      ],
      'creator' => [
        'fields' => ['field_creator'],
        'label_key' => '',
        'icon' => 'pen',
      ],
      'gender' => [
        'fields' => ['field_gender'],
        'label_key' => '',
        'icon' => '',
      ],
      'alt_names' => [
        'fields' => ['field_alternative_name'],
        'label_key' => '',
        'icon' => '',
      ],
      'linked_data' => [
        'fields' => ['field_wikidata_entry'],
        'label_key' => 'lemma_linked_data',
        'icon' => '',
      ],
    ];

    return $mapping[$element] ?? NULL;
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
  public function getLemmaTypeToElementMapping(string $node_type, string $display_type): array {
    $mapping = [
      '_all' => [
        'full' => ['discipline', 'specialisation', 'typology', 'alt_names', 'linked_data'],
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
      $mapping['_all'][$display_type] ?? [],
      $mapping[$node_type][$display_type] ?? []
    );
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
      'field_author' => 'author',
      default => NULL,
    };
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
   *   Structure: ['element_key' => ['value' => string, 'label' => string, 'icon' => string]]
   */
  public function getLemmaKeyData(
    NodeInterface $node,
    string $display_type = 'full',
    ?string $search_view_id = NULL,
    ?string $search_view_display = NULL
  ): ?array {
    $node_type = $node->getType();
    $lemma_elements = $this->getLemmaTypeToElementMapping($node_type, $display_type);

    if (!$lemma_elements) {
      return NULL;
    }

    $return_values = [];
    foreach ($lemma_elements as $el_key) {
      $el_def = $this->getElementToFieldMapping($el_key);

      if (empty($el_def['fields'])) {
        continue;
      }

      $fld_vals = $this->getFieldValues($node, $el_def['fields'], [
        'link_type' => $search_view_id ? 'facet' : NULL,
        'view_id' => $search_view_id,
        'view_display' => $search_view_display,
      ]);

      if ($fld_vals) {
        $return_values[$el_key] = [
          'value' => $this->formatLemmaKeyData($fld_vals, $el_key, $display_type),
          'label' => $this->getElementLabel($node, $el_def),
          'icon' => $el_def['icon'],
        ];
      }
    }

    return empty($return_values) ? NULL : $return_values;
  }


  /**
   * Get field values ready for display.
   *
   * @param ?NodeInterface $node
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
   * @return string|array|FilteredMarkup|Markup|NULL
   *   Formatted values ready for display.
   */
  public function getFieldValues(
    ?NodeInterface $node,
    string|array $field_names,
    array $options = []
  ): string|array|FilteredMarkup|Markup|NULL {
    // Defaults.
    $options += [
      'format' => 'array',
      'link_type' => NULL,
      'view_id' => NULL,
      'view_display' => NULL,
    ];

    if(empty($node)){
      $node = $this->pageAnalyzer->getCurrentNode();
      if(!$node instanceof NodeInterface){
        return NULL;
      }
    }
    dpm('test');
    $is_single = is_string($field_names);
    $field_names = (array) $field_names;

    $values = [];
    foreach ($field_names as $fld_nm) {
      if (!$node->hasField($fld_nm) || $node->get($fld_nm)->isEmpty()) {
        continue;
      }

      $fld_def = $node->get($fld_nm)->getFieldDefinition();
      $fld_type = $fld_def->getType();
      $value = match ($fld_type) {
        'entity_reference' => $this->getEntityReferenceValue(
          $node,
          $fld_def,
          $options['link_type'],
          $options
        ),
        'datetime' => $this->getDateFieldValue($node, $fld_nm),
        'link' => $this->getLinkFieldValue($node, $fld_nm),
        'string', 'list_string' => $this->getStringFieldValue($node, $fld_nm),
        'boolean' => !empty($node->get($fld_nm)->value),
        'text_long', 'text_with_summary' => $this->getTextFieldValue($node, $fld_nm),
        default => Markup::create($node->get($fld_nm)->value) ?? NULL,
      };

      if ($value !== NULL) {
        $values[$fld_nm] = $value;
      }
    }

    if (empty($values)) {
      return NULL;
    }

    // Single field: return flat.
    if ($is_single) {
      $result = reset($values);
    }
    else {
      $result = $values;
    }

    // Format as string if requested.
    if ($options['format'] === 'string' && is_array($result)) {
      return implode(', ', array_map([$this, 'flattenValue'], $result));
    }

    return $result;
  }


  /**
   * Get entity reference values as rendered strings.
   *
   * @param NodeInterface $node
   *   The node.
   * @param mixed $field_definition
   *   Field definition.
   * @param string|null $link_type
   *   Link type ('entity', 'facet', or NULL).
   * @param array $options
   *   Options array with view_id and view_display for facet links.
   *
   * @return array|null
   *   Array of rendered strings, or NULL if empty.
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
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    foreach ($ents as $id => $ent) {
      if ($ent->hasTranslation($langcode)) {
        $ent = $ent->getTranslation($langcode);
      }
      $title = $target_type === 'taxonomy_term'
        ? $ent->getName()
        : $ent->getTitle();

      // Determine URL.
      $url = NULL;

      if ($link_type === 'entity') {
        $url = $ent->toUrl();
      }
      elseif ($link_type === 'facet' && !empty($options['view_id']) && !empty($options['view_display'])) {
        $facet_id = $this->getFacetQueryIdForField($field_name);
        if ($facet_id) {
          $url = $this->facetResultsProvider->getEnableFacetUrl($options['view_id'], $options['view_display'], $facet_id, $id);
        }
      }

      if ($url instanceof Url) {
        $render_array = $this->facetResultsProvider->getEnableFacetLinkRenderArray($url, $title);
        $result[] =  Markup::create((string) $this->renderer->renderPlain($render_array));
      }
      else {
        $result[] = $title;
      }
    }

    return $result;
  }


  /**
   * Get formatted date field values.
   *
   * @param NodeInterface $node
   *   The node.
   * @param string $field_name
   *   Field machine name.
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

      $year_only_field = str_replace(['date_start', 'date_end'], ['date_start_year_only', 'date_end_year_only'], $field_name);
      $year_only = $node->hasField($year_only_field) && $node->get($year_only_field)->value;

      $dates[] = $year_only ? $date->format('Y') : $date->format('d/m/Y');
    }

    return $dates;
  }


  /**
   * Get text field value (text_long, text_with_summary).
   */
  protected function getTextFieldValue(NodeInterface $node, string $field_name): FilteredMarkup|Markup|null {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }
    
    $item = $node->get($field_name)->first();
    
    // Check of processed property bestaat (text_with_summary heeft dit)
    if (isset($item->processed)) {
      return $item->processed;
    }
    
    // Fallback: manual processing (voor text_long)
    $value = $item->getValue();
    if (empty($value['value'])) {
      return NULL;
    }
    
    $format = $value['format'] ?? 'basic_html';
    return Markup::create(check_markup($value['value'], $format));
  }


  /**
   * Get string field values.
   *
   * @param NodeInterface $node
   *   The node.
   * @param string $field_name
   *   Field machine name.
   *
   * @return array|null
   *   Array of string values, or NULL if empty.
   */
  protected function getStringFieldValue(NodeInterface $node, string $field_name): ?array {
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
   * Get link field values as render arrays.
   *
   * @param NodeInterface $node
   *   The node.
   * @param string $field_name
   *   Field machine name.
   *
   * @return array|null
   *   Array of link render arrays, or NULL if empty.
   */
  protected function getLinkFieldValue(NodeInterface $node, string $field_name): ?array {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    $values = [];
    $field = $node->get($field_name);
    
    foreach ($field->getValue() as $value) {
      if (empty($value['uri'])) {
        continue;
      }

      try {
        $url = Url::fromUri($value['uri']);
        $is_external = $url->isExternal();
        
        $link_text = !empty($value['title']) 
          ? $value['title']
          : $field->getFieldDefinition()->getLabel();
        
        // Build render array
        $link_array = [
          '#type' => 'link',
          '#title' => $link_text,
          '#url' => $url,
          '#attributes' => [
            'class' => ['field-link'],
          ],
        ];
        
        if ($is_external) {
          $link_array['#attributes']['target'] = '_blank';
          $link_array['#attributes']['rel'] = 'noopener noreferrer';
          $link_array['#suffix'] = Markup::create(
            ' <i data-component-id="jakarta:c-icon" class="c-icon no-media c-icon--link c-icon--link--before"></i>'
          );
        }
        
        // Render to Markup
        $rendered = $this->renderer->renderPlain($link_array);
        $values[] = Markup::create($rendered);
        
      } catch (\Exception $e) {
        \Drupal::logger('bestor_content_helper')->warning('Invalid URI in field @field: @uri', [
          '@field' => $field_name,
          '@uri' => $value['uri'],
        ]);
        continue;
      }
    }

    return empty($values) ? NULL : $values;
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
   * @return string|Markup
   *   Formatted markup or empty string.
   */
  protected function formatLemmaKeyData(array $values, string $element_key, string $display_type = 'full'): string|Markup {
    if (empty($values)) {
      return '';
    }

    $formatted = match ($element_key) {
      'birth_death' => $this->lifeDataFormatter($values),
      'period' => $this->periodDataFormatter($values),
      'birth', 'death', 'location' => $this->twoFieldsKeyDataFormatter($values),
      default => $this->defaultDataFormatter($values),
    };

    return $formatted ? Markup::create($formatted) : '';
  }


  /**
   * Default formatter: render first value.
   *
   * @param array $values
   *   Values array.
   *
   * @return string|Markup
   *   Formatted string or markup.
   */
  protected function defaultDataFormatter(array $values): string|Markup {
    return $this->renderValue(reset($values));
  }


  /**
   * Format two fields as "first (second)".
   *
   * @param array $values
   *   Values array.
   *
   * @return string|Markup
   *   Formatted string or markup.
   */
  protected function twoFieldsKeyDataFormatter(array $values): string|Markup {
    $values = array_values($values);
    if (count($values) > 1) {
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
   * @return string|Markup
   *   Formatted string or markup.
   */
  protected function periodDataFormatter(array $values): string|Markup {
    return trim($this->renderValue($values['field_date_start']) . ' → ' . $this->renderValue($values['field_date_end']));
  }


  /**
   * Format life data as "birth_date (birth_place) → death_date (death_place)".
   *
   * @param array $values
   *   Associative array with date and municipality fields.
   *
   * @return string|Markup
   *   Formatted life data string or markup.
   */
  protected function lifeDataFormatter(array $values): string|Markup {
    $birth = $this->twoFieldsKeyDataFormatter(array_filter([$values['field_date_start'], $values['field_municipality']]));
    $death = $this->twoFieldsKeyDataFormatter(array_filter([$values['field_date_end'], $values['field_end_municipality']]));
    return trim($birth . ' → ' . $death);
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
    // 1. Try translation key if present.
    if (!empty($element_definition['label_key'])) {
      $translation = $this->customTranslations->get($element_definition['label_key']);

      if ($translation !== $element_definition['label_key']) {
        return $translation;
      }
    }

    // 2. Fallback: use label of first field.
    if (!empty($element_definition['fields'])) {
      $first_field = reset($element_definition['fields']);

      if ($node->hasField($first_field)) {
        $field_definition = $node->get($first_field)->getFieldDefinition();
        return $field_definition->getLabel();
      }
    }

    // 3. Last resort: return element key.
    return $element_definition['label_key'] ?? '';
  }


  /**
   * Render a value (handles arrays, Markup, strings).
   *
   * @param mixed $value
   *   Value to render.
   *
   * @return string|Markup 
   *   Rendered string or Markup.
   */
  protected function renderValue($value) {
    if (empty($value)) {
      return '';
    }

    // Array of values.
    if (is_array($value)) {
      $rendered = implode(', ', array_map([$this, 'renderValue'], $value));
      foreach ($value as $item) {
        if ($item instanceof Markup) {
          return Markup::create($rendered);
        }
      }
      return $rendered;
    }
    if ($value instanceof Markup) {
      return $value;
    }

    return (string) $value;
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
}