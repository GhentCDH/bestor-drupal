<?php

namespace Drupal\bestor_content_helper\Service;

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
      'homepage_banner_title' => [
        'description' => 'Heading text displayed above the search box in the homepage banner.',
        'en' => 'Search in our database',
        'fr' => 'Recherchez dans notre base de données',
        'nl' => 'Zoek in onze database',
      ],
      'homepage_banner_sub_title_prefix' => [
        'description' => 'Prefix text displayed below the search box in the homepage banner, preceding the clickable link.',
        'en' => 'Use the',
        'fr' => 'Utilisez la',
        'nl' => 'Maak gebruik van de',
      ],
      'homepage_banner_sub_title_linktext' => [
        'description' => 'Clickable link text shown below the search box in the homepage banner.',
        'en' => 'advanced search function with numerous parameters',
        'fr' => 'fonction de recherche avancée avec de nombreux paramètres',
        'nl' => 'uitgebreide zoekfunctie met talloze parameters',
      ],
      'homepage_banner_sub_title_suffix' => [
        'description' => 'Suffix text displayed after the clickable link below the search box in the homepage banner.',
        'en' => 'to find a specific keyword.',
        'fr' => 'pour trouver un mot-clé spécifique.',
        'nl' => 'om een specfiek trefwoord te vinden.',
      ],
    'default_author' => [
        'description' => 'Which author should be displayed if none is entered',
        'en' => 'Bestor editors',
        'fr' => 'Rédaction Bestor',
        'nl' => 'Bestor redactie',
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
      $config = $this->configFactory->get('bestor_content_helper.translations');
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


  public function generateBannerSubtitleHtml(string $langcode = null): string{
    $url_lang_prefix = '';
    if(!empty($langcode) && $langcode !== $this->languageManager->getDefaultLanguage()->getId()){
      $url_lang_prefix = '/' . $langcode;
    }
    $prefix = $this->get('homepage_banner_sub_title_prefix', $langcode);
    $linktext = $this->get('homepage_banner_sub_title_linktext', $langcode);
    $suffix = $this->get('homepage_banner_sub_title_suffix', $langcode);
    return $prefix . ' <a href="' . $url_lang_prefix . '/database">' . $linktext . '</a> ' . $suffix;
  }
}