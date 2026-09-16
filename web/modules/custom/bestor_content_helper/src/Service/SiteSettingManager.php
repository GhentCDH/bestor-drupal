<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\bestor_content_helper\Service\UrlProvider;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;


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
    $raw = $this->getBestorSiteSetting('searchbanner_tagline');
    if (!$raw) {
      return NULL;
    }

    $search_url = $this->urlProvider->getTranslatedUrlFromRoute('view.database.page_1')->toString();
    $analysis_url = $this->urlProvider->getTranslatedUrlFromRoute('view.database_advanced_search.page_6')->toString();
    $urls = [$search_url, $analysis_url];

    $text = Html::escape($raw);
    $linked = $this->parseBracketLinks($text, $urls);
    return Markup::create($linked);
  }
 

  private function parseBracketLinks(string $text, array $urls): string {
    // look for first '['.
    $open1 = strpos($text, '[');
    if ($open1 === FALSE) {
      return $text;
    }

    // look for closing ']'.
    $close1 = strpos($text, ']', $open1 + 1);
    if ($close1 === FALSE) {
      return $text;
    }

    $before = substr($text, 0, $open1);
    $linktext1 = substr($text, $open1 + 1, $close1 - $open1 - 1);
    $result = $before . '<a href="' . $urls[0] . '">' . $linktext1 . '</a>';
    $pos = $close1 + 1;

    // look for second '[' after first closing.
    $open2 = strpos($text, '[', $pos);
    if ($open2 === FALSE) {
      return $result . substr($text, $pos);
    }

    $close2 = strpos($text, ']', $open2 + 1);
    if ($close2 === FALSE) {
      return $result . substr($text, $pos);
    }

    $between = substr($text, $pos, $open2 - $pos);
    $linktext2 = substr($text, $open2 + 1, $close2 - $open2 - 1);
    $result .= $between . '<a href="' . $urls[1] . '">' . $linktext2 . '</a>';

    // anything after second closing will be displayed as is.
    return $result . substr($text, $close2 + 1);
  }
}