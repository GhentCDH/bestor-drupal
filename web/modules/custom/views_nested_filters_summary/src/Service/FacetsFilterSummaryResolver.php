<?php

namespace Drupal\views_nested_filters_summary\Service;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Resolves facets_filter values for views_filters_summary integration.
 *
 * Handles both URL styles used by Drupal Facets:
 *   - Array style:  field[41]=1  (standard Facets URL processor)
 *   - Scalar style: field=41     (query string / simple style)
 *
 * Resolves entity labels using the correct entity type per facet field, and
 * applies mirror labels for relation type facets when the
 * translate_entity_mirror_label processor is configured.
 */
class FacetsFilterSummaryResolver {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    // Optional: only available when relationship_nodes is installed.
    private readonly mixed $mirrorProvider,
  ) {}


  /**
   * Populates $filter->value for facets_filter plugins in a view.
   *
   * views_filters_summary skips filters whose $value is empty. Facets filters
   * manage their state via the URL processor, so we inject the active values
   * here before the summary is built.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view being rendered.
   */
  public function populateFacetValues(ViewExecutable $view): void {
    foreach ($view->filter as $filter) {
      if ($filter->getPluginId() !== 'facets_filter') {
        continue;
      }
      $identifier = $filter->options['expose']['identifier'] ?? NULL;
      if (!$identifier) {
        continue;
      }
      $values = $this->normalizeFacetValues(
        \Drupal::request()->query->all()[$identifier] ?? NULL
      );
      if (empty($values)) {
        continue;
      }
      $filter->value = $values;
      $exposed = $view->getExposedInput();
      if (!isset($exposed[$identifier])) {
        $exposed[$identifier] = $values;
        $view->setExposedInput($exposed);
      }
    }
  }


  /**
   * Builds the views_filters_summary info for a facets_filter plugin.
   *
   * @param array $info
   *   The filter info array, passed by reference.
   * @param \Drupal\views\Plugin\views\filter\FilterPluginBase $filter
   *   The Views filter plugin.
   */
  public function buildSummaryInfo(array &$info, FilterPluginBase $filter): void {
    if ($filter->getPluginId() !== 'facets_filter') {
      return;
    }
    $identifier = $filter->options['expose']['identifier'] ?? NULL;
    if (!$identifier) {
      return;
    }
    $values = $this->normalizeFacetValues(
      \Drupal::request()->query->all()[$identifier] ?? NULL
    );
    if (empty($values)) {
      $info['value'] = NULL;
      return;
    }

    $entityTypeId = $this->resolveFacetEntityType($filter);
    $useMirror    = $this->facetUsesMirror($filter);

    $items = [];
    foreach ($values as $value) {
      $label = $this->resolveLabel($identifier, $value, $entityTypeId, $useMirror);
      if ($label !== NULL) {
        $items[] = [
          'id'    => $value,
          'raw'   => $value,
          'value' => $label,
        ];
      }
    }

    $info['value'] = $items ?: NULL;
  }


  /**
   * Normalizes a facets URL parameter to a flat array of string values.
   *
   * @param mixed $raw
   *   Raw value from the request query.
   *
   * @return array
   *   Flat array of string values, empty if nothing active.
   */
  private function normalizeFacetValues(mixed $raw): array {
    if (empty($raw)) {
      return [];
    }
    // Array style: field[41]=1 — values are the keys.
    if (is_array($raw)) {
      return array_keys($raw);
    }
    // Scalar style: field=41 — value is the param itself.
    return [(string) $raw];
  }


  /**
   * Determines the target entity type ID for a facets_filter field.
   *
   * Inspects the Search API index field via the View's query plugin to find
   * the entity type the facet values represent. Falls back to a field ID
   * heuristic, then to 'taxonomy_term'.
   *
   * @param \Drupal\views\Plugin\views\filter\FilterPluginBase $filter
   *   The Views filter plugin.
   *
   * @return string
   *   Entity type ID (e.g. 'node', 'taxonomy_term').
   */
  private function resolveFacetEntityType(FilterPluginBase $filter): string {
    try {
      $index = $filter->view
        ?->getDisplay()
        ?->getPlugin('query')
        ?->getIndex();

      $field = $index?->getField($filter->field ?? '');
      if ($field) {
        $definition = $field->getDataDefinition();
        while ($definition) {
          if (method_exists($definition, 'getConstraints')) {
            $constraints = $definition->getConstraints();
            if (!empty($constraints['Bundle']['bundleOf'])) {
              return $constraints['Bundle']['bundleOf'];
            }
          }
          $definition = method_exists($definition, 'getItemDefinition')
            ? $definition->getItemDefinition()
            : NULL;
        }
      }
    }
    catch (\Exception) {
      // Fall through to heuristic.
    }

    // Heuristic: field names containing 'related_entity' are node references.
    if (str_contains($filter->field ?? '', 'related_entity')) {
      return 'node';
    }

    return 'taxonomy_term';
  }


  /**
   * Checks whether a facets_filter should use mirror labels.
   *
   * Returns TRUE when the translate_entity_mirror_label processor is
   * configured on the facet, indicating relation type labels should be
   * resolved via the MirrorProvider.
   *
   * @param \Drupal\views\Plugin\views\filter\FilterPluginBase $filter
   *   The Views filter plugin.
   *
   * @return bool
   *   TRUE if mirror labels should be used.
   */
  private function facetUsesMirror(FilterPluginBase $filter): bool {
    $processor_configs = $filter->options['facet']['processor_configs'] ?? [];
    return isset($processor_configs['translate_entity_mirror_label']);
  }


  /**
   * Resolves a human-readable label for a single facet value.
   *
   * Resolution order:
   * 1. identifier === 'category' → node bundle label.
   * 2. Numeric + mirror enabled + MirrorProvider available → mirror label.
   * 3. Numeric → load entity from correct storage, return translated label.
   * 4. Fallback → return value as-is (e.g. startchar letters).
   *
   * @param string $identifier
   *   The Views exposed filter identifier.
   * @param string $value
   *   The raw facet value.
   * @param string $entityTypeId
   *   Entity type to load numeric IDs from.
   * @param bool $useMirror
   *   Whether to attempt mirror label resolution.
   *
   * @return string|null
   *   Human-readable label, or NULL if unresolvable.
   */
  private function resolveLabel(string $identifier, string $value, string $entityTypeId, bool $useMirror): ?string {
    // Node bundle label (e.g. category facet).
    if ($identifier === 'category') {
      $bundles = $this->bundleInfo->getBundleInfo('node');
      if (isset($bundles[$value]['label'])) {
        return (string) $bundles[$value]['label'];
      }
    }

    if (is_numeric($value)) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();

      // Mirror label for relation type facets.
      if ($useMirror && $this->mirrorProvider !== NULL) {
        $mirror = $this->mirrorProvider->getMirrorLabelFromId($value, $langcode);
        if ($mirror !== NULL) {
          return $mirror;
        }
      }

      // Generic entity label from the correct storage.
      try {
        $entity = $this->entityTypeManager
          ->getStorage($entityTypeId)
          ->load((int) $value);
        if ($entity) {
          if (method_exists($entity, 'hasTranslation') && $entity->hasTranslation($langcode)) {
            $entity = $entity->getTranslation($langcode);
          }
          return $entity->label();
        }
      }
      catch (\Exception) {
        // Fall through.
      }
    }

    // Non-numeric or unresolvable — return as-is.
    return $value;
  }

}