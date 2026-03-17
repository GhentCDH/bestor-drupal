<?php

namespace Drupal\relationship_nodes_search\Plugin\facets\processor;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\relationship_nodes\RelationData\TermHelper\MirrorProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transforms entity IDs to their mirror label.
 *
 * Works like the built-in "Transform entity ID to label" processor, but
 * resolves the mirror label of the taxonomy term instead of the plain label.
 * Falls back to the plain term label if no mirror label is configured.
 *
 * @FacetsProcessor(
 *   id = "translate_entity_mirror_label",
 *   label = @Translation("Transform entity ID to mirror label"),
 *   description = @Translation("Displays the mirror label of the relation type term instead of its ID. Falls back to the term label if no mirror label is configured. If also 'Transform entity ID to label' is enabled, this mirror processor overrules the original one."),
 *   stages = {
 *     "build" = 25
 *   }
 * )
 */
class TranslateEntityMirrorProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  protected MirrorProvider $mirrorProvider;
  protected LanguageManagerInterface $languageManager;


  /**
   * Constructs a TranslateEntityMirrorProcessor object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MirrorProvider $mirror_provider,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mirrorProvider = $mirror_provider;
    $this->languageManager = $language_manager;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('relationship_nodes.mirror_provider'),
      $container->get('language_manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    foreach ($results as $result) {
      $term_id = (string) $result->getRawValue();

      if (!is_numeric($term_id)) {
        continue;
      }

      $mirror_label = $this->mirrorProvider->getMirrorLabelFromId($term_id, $langcode);

      // Only override if a mirror label is explicitly configured.
      // If not, the existing display value (set by translate_entity or the
      // raw ID) is left untouched.
      if ($mirror_label !== NULL) {
        $result->setDisplayValue($mirror_label);
      }
    }

    return $results;
  }

}