<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\bestor_content_helper\Service\UrlProvider;
use Drupal\Core\Render\Markup;


/* Service to ease the communication with the custom entity type BestorSiteSetting*/
class SiteSettingManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected UrlProvider $urlProvider;


  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    UrlProvider $urlProvider
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->urlProvider = $urlProvider;
  }


  public function getBestorSiteSetting(string $setting_id, ?string $langcode = NULL) {
    $setting = $this->entityTypeManager->getStorage('bestor_site_setting')->load($setting_id) ?? NULL;

    if ($setting) {
      $value = $setting->getValue($langcode);
    }
    
    return $value ? $value : '';
  }


  public function getSearchTagline(): ?Markup {
    $prefix = $this->getBestorSiteSetting('searchbanner_subtitle_prefix');
    $linktext = $this->getBestorSiteSetting('searchbanner_subtitle_link');
    $suffix = $this->getBestorSiteSetting('searchbanner_subtitle_suffix');

    if ($linktext) {
      $url = $this->urlProvider->getTranslatedUrlFromRoute('view.database.page_1')->toString();
      $linktext = '<a href="' . $url . '">' . $linktext . '</a>';
    }

    return Markup::create(implode(' ', array_filter([$prefix, $linktext, $suffix])));
  }
}