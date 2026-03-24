<?php

namespace Drupal\sapi_item_translation_availability\Plugin\views\filter;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\Plugin\views\filter\SearchApiOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters nodes by translation availability with optional language fallback.
 *
 * Provides two options:
 * - Current language only: only shows nodes with a published translation in
 *   the current interface language.
 * - Current language with fallback: also shows nodes that have a published
 *   translation in another language, which will redirect to the
 *   translation-unavailable page.
 *
 * @ViewsFilter("sapi_translation_availability")
 */
class TranslationAvailabilityFilter extends SearchApiOptions {

  const CURRENT_LANGUAGE = 'current_language';
  const CURRENT_LANGUAGE_FALLBACK = 'current_language_fallback';

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
  public function getValueOptions(): array {
    if (isset($this->valueOptions)) {
      return $this->valueOptions;
    }

    $this->valueOptions = [
      self::CURRENT_LANGUAGE => $this->t('Current language'),
      self::CURRENT_LANGUAGE_FALLBACK => $this->t('Current language with fallback'),
    ];

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
  $langcode = $this->languageManager->getCurrentLanguage()->getId();
  $default_langcode = $this->languageManager->getDefaultLanguage()->getId();
  $query = $this->query;

  if (isset($this->value[self::CURRENT_LANGUAGE_FALLBACK])) {
    $group = $query->createConditionGroup('OR');

    // Toon huidige taalversie.
    $group->addCondition('search_api_language', $langcode, '=');

    // Toon default taalversie enkel als fallback nodig is.
    // NodeLanguageFallbackSubscriber vangt de klik op en redirects.
    if ($langcode !== $default_langcode) {
      $fallback_group = $query->createConditionGroup('AND');
      $fallback_group->addCondition('search_api_language', $default_langcode, '=');
      $fallback_group->addCondition('available_translations', $langcode, '<>');
      $group->addConditionGroup($fallback_group);
    }

    $query->addConditionGroup($group);
    return;
  }

  $query->addCondition('search_api_language', $langcode, '=');
}

}