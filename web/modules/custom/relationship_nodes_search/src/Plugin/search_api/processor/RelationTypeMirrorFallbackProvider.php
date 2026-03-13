<?php

namespace Drupal\relationship_nodes_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes\RelationBundle\BundleInfoService;
use Drupal\relationship_nodes\RelationBundle\Settings\BundleSettingsManager;
use Drupal\relationship_nodes\RelationData\TermHelper\MirrorProvider;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a relation type label field per typed relation bundle.
 *
 * Provides one field per typed relation bundle containing the mirror label
 * of the relation type term. If no mirror label is configured, the term
 * name itself is used as fallback.
 *
 * @SearchApiProcessor(
 *   id = "relation_type_mirror_fallback_provider",
 *   label = @Translation("Relation Type Mirror Fallback Provider"),
 *   description = @Translation("Adds a relation type label field per typed relation bundle. Uses the mirror label if configured, otherwise falls back to the term name."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = 0,
 *   }
 * )
 */
class RelationTypeMirrorFallbackProvider extends ProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Prefix for the relation type label property IDs.
   */
  const FIELD_PREFIX = 'mirror_w_fallback_';

  protected BundleSettingsManager $settingsManager;
  protected BundleInfoService $bundleInfoService;
  protected FieldNameResolver $fieldResolver;
  protected MirrorProvider $mirrorProvider;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    BundleSettingsManager $settings_manager,
    BundleInfoService $bundle_info_service,
    FieldNameResolver $field_resolver,
    MirrorProvider $mirror_provider,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settingsManager = $settings_manager;
    $this->bundleInfoService = $bundle_info_service;
    $this->fieldResolver = $field_resolver;
    $this->mirrorProvider = $mirror_provider;
    $this->entityTypeManager = $entity_type_manager;
  }

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
      $container->get('relationship_nodes.bundle_settings_manager'),
      $container->get('relationship_nodes.bundle_info_service'),
      $container->get('relationship_nodes.field_name_resolver'),
      $container->get('relationship_nodes.mirror_provider'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if (!$datasource || $datasource->getEntityTypeId() !== 'node') {
      return [];
    }

    $properties = [];

    foreach ($datasource->getBundles() as $bundle => $label) {
      $bundle_info = $this->settingsManager->getBundleInfo($bundle);
      if (!$bundle_info || !$bundle_info->isTypedRelation()) {
        continue;
      }

      // Sanitize bundle name: replace __ with _ to avoid Views field ID issues.
      $safe_bundle = preg_replace('/__+/', '_', $bundle);
      $field_id = self::FIELD_PREFIX . '_' . $safe_bundle;

      $properties[$field_id] = new ProcessorProperty([
        'label' => $this->t('Relation type label (@bundle)', [
          '@bundle' => $bundle,
        ]),
        'description' => $this->t('The mirror label of the relation type term for @bundle, or the term name if no mirror label is configured.', [
          '@bundle' => $bundle,
        ]),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException $e) {
      return;
    }

    if (!($entity instanceof EntityInterface)) {
      return;
    }

    $bundle = $entity->bundle();
    $bundle_info = $this->settingsManager->getBundleInfo($bundle);
    if (!$bundle_info || !$bundle_info->isTypedRelation()) {
      return;
    }

    $safe_bundle = preg_replace('/__+/', '_', $bundle);
    $field_id = self::FIELD_PREFIX . '_' . $safe_bundle;

    $fields = $this->getFieldsHelper()->filterForPropertyPath(
      $item->getFields(),
      $item->getDatasourceId(),
      $field_id
    );

    if (empty($fields)) {
      return;
    }

    $relation_field = $this->fieldResolver->getRelationTypeField();

    try {
      $term_id = $entity->get($relation_field)->target_id ?? NULL;
    }
    catch (\Exception $e) {
      return;
    }

    if (!$term_id) {
      return;
    }

    $label = $this->resolveLabel((string) $term_id);

    if (!$label) {
      return;
    }

    foreach ($fields as $field) {
      $field->addValue($label);
    }
  }

  /**
   * Returns the mirror label for the given term, or the term name as fallback.
   *
   * If the relation type term has a mirror label configured, that label is
   * returned. If not, the term name itself is returned instead.
   */
  protected function resolveLabel(string $term_id): ?string {
    $mirror_label = $this->mirrorProvider->getMirrorLabelFromId($term_id);
    if ($mirror_label !== NULL) {
      return $mirror_label;
    }

    $term = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load((int) $term_id);

    if (!$term instanceof TermInterface) {
      return NULL;
    }

    return $term->label() ?: NULL;
  }
}