<?php

namespace Drupal\bestor_content_helper\TwigExtension;

use Drupal\bestor_content_helper\Service\SiteSettingManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Drupal\Core\Render\Markup;
use Drupal\bestor_content_helper\Service\FacetResultsProvider;
use Drupal\bestor_content_helper\Service\NodeContentAnalyzer;
use Drupal\bestor_content_helper\Service\CurrentPageAnalyzer;
use Drupal\bestor_content_helper\Service\StandardNodeFieldProcessor;
use Drupal\bestor_content_helper\Service\MediaProcessor;
use Drupal\bestor_content_helper\Service\UrlProvider;
use Drupal\filter\Render\FilteredMarkup;
use Drupal\Core\Url;

/**
 * Twig extension for custom translations.
 */
class CustomTranslationExtension extends AbstractExtension {

  protected LanguageManagerInterface $languageManager;
  protected SiteSettingManager $siteSettingManager;
  protected FacetResultsProvider $facetResultsProvider;
  protected CurrentPageAnalyzer $pageAnalyzer;
  protected NodeContentAnalyzer $nodeContentAnalyzer;
  protected StandardNodeFieldProcessor $standardFieldProcessor;
  protected MediaProcessor $mediaProcessor;
  protected UrlProvider $urlProvider;

  /**
   * Constructor.
   */
  public function __construct(
    LanguageManagerInterface $languageManager, 
    SiteSettingManager $siteSettingManager,
    FacetResultsProvider $facetResultsProvider,
    CurrentPageAnalyzer $pageAnalyzer,
    NodeContentAnalyzer $nodeContentAnalyzer,
    StandardNodeFieldProcessor $standardFieldProcessor,
    MediaProcessor $mediaProcessor,
    UrlProvider $urlProvider
  ) {
    $this->languageManager = $languageManager;
    $this->siteSettingManager = $siteSettingManager;
    $this->facetResultsProvider = $facetResultsProvider;
    $this->pageAnalyzer = $pageAnalyzer;
    $this->nodeContentAnalyzer = $nodeContentAnalyzer;
    $this->standardFieldProcessor = $standardFieldProcessor;
    $this->mediaProcessor = $mediaProcessor;
    $this->urlProvider = $urlProvider;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('bestor', [$this, 'bestor'], [
        'is_safe' => ['html'],
      ]),
    ];
  }

  /**
   * Main bestor function - routes to different functionality.
   */
  public function bestor(string $type, ...$args): Markup|FilteredMarkup|Url|array|string|null {
    return match($type) {
      'facet_buttons' => $this->facetResultsProvider->getSearchBannerFacetButtons(...$args),
      'media_info' => $this->mediaProcessor->getEntityMediaInfo(...$args),
      'media_count' => $this->mediaProcessor->getMediaItemCount(...$args),
      'reading_time' => $this->nodeContentAnalyzer->getFormattedReadingTime(...$args),
      'page_variant' => $this->pageAnalyzer->getPageVariant(...$args),
      'field_values' => $this->standardFieldProcessor->getFieldValues(...$args),
      'lemma_key_data' => $this->standardFieldProcessor->getLemmaKeyData(...$args),
      'search_tagline' =>  $this->siteSettingManager->getSearchTagline(...$args),
      'site_setting' => $this->siteSettingManager->getBestorSiteSetting(...$args),
      'translated_url' => $this->urlProvider->getTranslatedPageUrl(...$args),
      'citation' => $this->standardFieldProcessor->getCitation(...$args),
      default => NULL,
    };
  }
}