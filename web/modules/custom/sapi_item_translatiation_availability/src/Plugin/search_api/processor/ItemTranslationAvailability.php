<?php

namespace Drupal\sapi_item_translation_availability\Plugin\search_api\processor;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\IndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds boolean fields indicating whether a node has a published translation
 * in each configured language.
 *
 * Adds one field per language: `has_translation_[langcode]` (boolean).
 * These fields can be used in Views filters to implement language fallback:
 * show the current language, or show records from other languages only when
 * the current language translation is missing.
 *
 * @SearchApiProcessor(
 *   id = "item_translation_availability",
 *   label = @Translation("Item translation availability"),
 *   description = @Translation("Adds per-language boolean availability fields for nodes."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = false,
 * )
 */
class ItemTranslationAvailability extends ProcessorPluginBase {

  protected LanguageManagerInterface $languageManager;


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }


  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getPluginId() === 'entity:node') {
        return TRUE;
      }
    }
    return FALSE;
  }


  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if (!$datasource || $datasource->getPluginId() !== 'entity:node') {
      return [];
    }

    $properties = [];

    $properties['available_translations'] = new ProcessorProperty([
      'label' => $this->t('Available translations'),
      'description' => $this->t('Language codes for which this node has a published translation.'),
      'type' => 'string',
      'processor_id' => $this->getPluginId(),
      'is_list' => TRUE,
    ]);

    return $properties;
  }


  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()?->getValue();

    if (!$entity instanceof NodeInterface) {
      return;
    }

    $fields = [];
    foreach ($item->getFields() as $field) {
      $fields[$field->getPropertyPath()] = $field;
    }

    if (!isset($fields['available_translations'])) {
      return;
    }

    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      if ($entity->hasTranslation($langcode) && $entity->getTranslation($langcode)->isPublished()) {
        $fields['available_translations']->addValue($langcode);
      }
    }
  }

}