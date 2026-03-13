<?php

namespace Drupal\relationship_nodes_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes\RelationBundle\BundleInfoService;
use Drupal\relationship_nodes\RelationBundle\Settings\BundleSettingsManager;
use Drupal\relationship_nodes\RelationData\TermHelper\MirrorProvider;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\SearchApiException;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enriches the relation type mirror field with a fallback label.
 *
 * If a mirror label exists for the relation type term, it is indexed.
 * If no mirror exists, the term name is used as fallback.
 * Only processes typed relation bundles that have a mirroring vocabulary.
 *
 * @SearchApiProcessor(
 *   id = "relation_type_mirror_fallback_provider",
 *   label = @Translation("Relation Type Mirror Fallback Provider"),
 *   description = @Translation("When relation type mirrors are indexed, the original relation type will be indexed as fallback if the mirror is empty."),
 *   stages = {
 *     "preprocess_index" = 0,
 *   }
 * )
 */
class RelationTypeMirrorFallbackProvider extends FieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

  protected LoggerChannelFactoryInterface $loggerFactory;
  protected BundleSettingsManager $settingsManager;
  protected BundleInfoService $bundleInfoService;
  protected FieldNameResolver $fieldResolver;
  protected MirrorProvider $mirrorProvider;
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Resolves the mirror field property path for a given bundle.
   *
   * Returns NULL if the bundle has no mirroring vocabulary.
   */
  protected function resolveMirrorPropertyPath(string $bundle): ?string {
    $relation_bundle_info = $this->bundleInfoService->getRelationBundleInfo($bundle);
    $vocab_name = $relation_bundle_info['vocabulary'] ?? NULL;
    if (!$vocab_name) {
      return NULL;
    }

    $vocab_bundle_info = $this->settingsManager->getBundleInfo($vocab_name);
    if (!$vocab_bundle_info || !$vocab_bundle_info->isMirroringVocab()) {
      return NULL;
    }

    $mirror_field = $this->fieldResolver->getMirrorFields($vocab_bundle_info->getMirrorType());
    return $this->fieldResolver->getRelationTypeField() . ':entity:' . $mirror_field;
  }

  /**
   * The current entity being processed, set per item in preprocessIndexItems.
   */
  protected ?object $currentEntity = NULL;

  /**
   * The expected property path for the mirror field of the current entity.
   *
   * E.g. "rn_relation_type:entity:rn_mirror_string"
   */
  protected ?string $currentMirrorPropertyPath = NULL;

  /**
   * Cache of resolved mirror property paths per bundle.
   *
   * Avoids repeated fieldManager::getFieldDefinitions() calls per item.
   */
  protected array $mirrorPropertyPathCache = [];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelFactoryInterface $logger_factory,
    BundleSettingsManager $settings_manager,
    BundleInfoService $bundle_info_service,
    FieldNameResolver $field_resolver,
    MirrorProvider $mirror_provider,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerFactory = $logger_factory;
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
      $container->get('logger.factory'),
      $container->get('relationship_nodes.bundle_settings_manager'),
      $container->get('relationship_nodes.bundle_info_service'),
      $container->get('relationship_nodes.field_name_resolver'),
      $container->get('relationship_nodes.mirror_provider'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Only process typed relation bundles with a mirroring vocabulary.
   */
  public function preprocessIndexItems(array $items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      $this->currentEntity = NULL;
      $this->currentMirrorPropertyPath = NULL;

      try {
        $entity = $item->getOriginalObject()->getValue();
      }
      catch (SearchApiException $e) {
        continue;
      }

      if (!$entity) {
        continue;
      }

      $bundle = $entity->bundle();

      // Only typed relation bundles.
      $bundle_info = $this->settingsManager->getBundleInfo($bundle);
      if (!$bundle_info || !$bundle_info->isTypedRelation()) {
        continue;
      }

      // Resolve mirror property path, using per-bundle cache.
      if (!array_key_exists($bundle, $this->mirrorPropertyPathCache)) {
        $this->mirrorPropertyPathCache[$bundle] = $this->resolveMirrorPropertyPath($bundle);
      }

      $this->currentMirrorPropertyPath = $this->mirrorPropertyPathCache[$bundle];
      if (!$this->currentMirrorPropertyPath) {
        continue;
      }

      $this->currentEntity = $entity;

      foreach ($item->getFields() as $name => $field) {
        if ($this->testField($name, $field)) {
          $this->processField($field);
        }
      }
    }

    $this->currentEntity = NULL;
    $this->currentMirrorPropertyPath = NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Only match the exact mirror property path for this entity.
   */
  protected function testField($name, FieldInterface $field): bool {
    if (!$this->currentMirrorPropertyPath) {
      return FALSE;
    }
    return $field->getPropertyPath() === $this->currentMirrorPropertyPath;
  }

  /**
   * {@inheritdoc}
   */
  protected function processField(FieldInterface $field) {
    // Only fill empty fields.
    if (!empty($field->getValues())) {
      return;
    }

    if (!$this->currentEntity) {
      return;
    }

    $relation_type_field = $this->fieldResolver->getRelationTypeField();

    try {
      $term_id = $this->currentEntity->get($relation_type_field)->target_id ?? NULL;
    }
    catch (\Exception $e) {
      return;
    }

    if (!$term_id) {
      return;
    }

    $label = $this->resolveLabel((string) $term_id);

    if ($label) {
      $field->setValues([$label]);
    }
  }

  /**
   * Resolves the label: mirror label if available, term name as fallback.
   */
  protected function resolveLabel(string $term_id): ?string {
    $mirror_label = $this->mirrorProvider->getMirrorLabelFromId($term_id);
    if ($mirror_label !== NULL) {
      return $mirror_label;
    }

    try {
      $term = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->load((int) $term_id);
    }
    catch (\Exception $e) {
      return NULL;
    }

    if (!$term instanceof TermInterface) {
      return NULL;
    }

    return $term->getName() ?: NULL;
  }

}