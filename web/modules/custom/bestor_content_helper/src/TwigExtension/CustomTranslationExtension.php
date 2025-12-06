<?php

namespace Drupal\bestor_content_helper\TwigExtension;

use Drupal\bestor_content_helper\Service\CustomTranslations;
use Drupal\Core\Language\LanguageManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Drupal\Core\Render\Markup;
use Drupal\bestor_content_helper\Service\FacetResultsProvider;

/**
 * Twig extension for custom translations.
 */
class CustomTranslationExtension extends AbstractExtension {

  protected LanguageManagerInterface $languageManager;
  protected CustomTranslations $customTranslations;
  protected FacetResultsProvider $facetResultsProvider;

  /**
   * Constructor.
   */
  public function __construct(
    LanguageManagerInterface $languageManager, 
    CustomTranslations $customTranslations,
    FacetResultsProvider $facetResultsProvider
  ) {
    $this->languageManager = $languageManager;
    $this->customTranslations = $customTranslations;
    $this->facetResultsProvider = $facetResultsProvider;
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
  public function bestor(string $type, ...$args): Markup|array {
    return match($type) {
      'facet_links' => $this->getFacetLinks(...$args),
      default => $this->translate($type, ...$args),
    };
  }

  /**
   * Get custom translation.
   */
  protected function translate(string $key, ?string $langcode = NULL): Markup {
    if ($langcode == NULL) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }
    if ($key === 'banner_subtitle') {
      return Markup::create($this->customTranslations->generateBannerSubtitleHtml($langcode));
    }
    return Markup::create($this->customTranslations->get($key, $langcode));
  }

  /**
   * Get facet results as links render array.
   */
  protected function getFacetLinks(string $view_name, string $filter_id, string $facet_url_id, string $display_id = 'default'): array {
   dpm($this->facetResultsProvider->getFacetResultLinks($view_name, $filter_id, $facet_url_id, $display_id));
    return $this->facetResultsProvider->getFacetResultLinks($view_name, $filter_id, $facet_url_id, $display_id);
  }
}