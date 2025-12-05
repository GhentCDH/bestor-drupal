<?php

namespace Drupal\bestor_nodes_helper\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Custom translations service.
 * 
 * ADD NEW TRANSLATION KEYS IN getDefinitions() METHOD BELOW.
 */
class CustomTranslations {

  protected ConfigFactoryInterface $configFactory;
  protected LanguageManagerInterface $languageManager;
  protected ?array $translations = NULL;

  /**
   * Constructor.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $language_manager
  ) {
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
  }

  /**
   * ============================================
   * ADD NEW TRANSLATION KEYS HERE!
   * ============================================
   * 
   * Structure:
   * 'key' => [
   *   'description' => 'What this key is for (shown in admin)',
   *   'en' => 'English text',
   *   'fr' => 'French text',
   *   'nl' => 'Dutch text',
   * ]
   */
  public function getDefinitions(): array {
    return [
      'published_label' => [
        'description' => 'Label for publication date',
        'en' => 'published at',
        'fr' => 'publié à',
        'nl' => 'gepubliceerd op',
      ],
      'readmore_label' => [
        'description' => 'Read more link text',
        'en' => 'read more',
        'fr' => 'voir plus',
        'nl' => 'lees meer',
      ],
      'moreinfo_label' => [
        'description' => 'More info link text',
        'en' => 'more info',
        'fr' => 'plus d\'infos',
        'nl' => 'meer info',
      ],
      'author_label' => [
        'description' => 'Author label',
        'en' => 'author',
        'fr' => 'auteur',
        'nl' => 'auteur',
      ],
      'reading_time_label' => [
        'description' => 'Reading time label',
        'en' => 'reading time',
        'fr' => 'temps de lecture',
        'nl' => 'leestijd',
      ],
      'context_label' => [
        'description' => 'Context section label',
        'en' => 'In context',
        'fr' => 'Contexte',
        'nl' => 'In context',
      ],
      'place_label' => [
        'description' => 'Place/location label',
        'en' => 'Place',
        'fr' => 'Lieu',
        'nl' => 'Plaats',
      ],
      'date_label' => [
        'description' => 'Date label',
        'en' => 'Date',
        'fr' => 'Date',
        'nl' => 'Datum',
      ],
      'source_label' => [
        'description' => 'Source label',
        'en' => 'Source',
        'fr' => 'Source',
        'nl' => 'Bron',
      ],
      'related_label' => [
        'description' => 'Related content label',
        'en' => 'Related',
        'fr' => 'Articles liés',
        'nl' => 'Gerelateerd',
      ],
    ];
  }


  /**
   * Get supported language codes.
   */
  public function getSupportedLanguages(): array {
    return ['en', 'fr', 'nl'];
  }


  /**
   * Get available keys with descriptions.
   */
  public function getAvailableKeys(): array {
    $keys = [];
    foreach ($this->getDefinitions() as $key => $definition) {
      $keys[$key] = $definition['description'];
    }
    return $keys;
  }


  /**
   * Get default translations for all keys.
   */
  public function getDefaultTranslations(): array {
    $translations = [];
    $languages = $this->getSupportedLanguages();
    
    foreach ($this->getDefinitions() as $key => $definition) {
      $translations[$key] = [];
      foreach ($languages as $langcode) {
        $translations[$key][$langcode] = $definition[$langcode];
      }
    }
    
    return $translations;
  }


  /**
   * Get a translation by key.
   *
   * @param string $key
   *   The translation key.
   * @param string|null $langcode
   *   Language code (defaults to current language).
   *
   * @return string
   *   The translated string or the key if not found.
   */
  public function get(string $key, ?string $langcode = NULL): string {
    if ($langcode === NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }

    $translations = $this->getTranslations();
    
    return $translations[$key][$langcode] ?? $key;
  }


  /**
   * Get all translations (defaults merged with custom overrides).
   */
  public function getTranslations(): array {
    if ($this->translations === NULL) {
      $defaults = $this->getDefaultTranslations();
      $config = $this->configFactory->get('bestor_nodes_helper.translations');
      $custom = $config->get('custom_translations') ?? [];
      
      // Merge custom with defaults
      $this->translations = [];
      foreach ($defaults as $key => $langs) {
        $this->translations[$key] = $custom[$key] ?? $langs;
      }
    }
    
    return $this->translations;
  }


  /**
   * Clear cached translations.
   */
  public function clearCache(): void {
    $this->translations = NULL;
  }
}