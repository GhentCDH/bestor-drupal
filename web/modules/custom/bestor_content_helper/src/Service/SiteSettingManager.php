<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Markup;


/* Service to ease the communication with the custom entity type BestorSiteSetting*/
class SiteSettingManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected LanguageManagerInterface $languageManager;


  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $language_manager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $language_manager;
  }


  public function getBestorSiteSetting(string $setting_id, ?string $langcode = NULL) {
    $setting = $this->entityTypeManager->getStorage('bestor_site_setting')->load($setting_id) ?? NULL;

    if ($setting) {
      $value = $setting->getValue($langcode);
    }
    
    return $value ? $value : '';
  }


  public function getSearchTagline(string $langcode = null): ?Markup{
    $url_lang_prefix = '';
    if(!empty($langcode) && $langcode !== $this->languageManager->getDefaultLanguage()->getId()){
      $url_lang_prefix = '/' . $langcode;
    }

    $prefix = $this->getBestorSiteSetting('searchbanner_subtitle_prefix', $langcode);
    $linktext = $this->getBestorSiteSetting('searchbanner_subtitle_link', $langcode);
    $suffix = $this->getBestorSiteSetting('searchbanner_subtitle_suffix', $langcode);

    if($linktext) {
      $linktext = '<a href="' . $url_lang_prefix . '/database">' . $linktext . '</a>';
    }
    
    return Markup::create(implode(' ', [$prefix, $linktext, $suffix])) ?? NULL;
  }
}