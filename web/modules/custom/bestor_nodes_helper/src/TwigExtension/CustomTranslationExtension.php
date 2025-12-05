<?php

namespace Drupal\bestor_nodes_helper\TwigExtension;

use Drupal\bestor_nodes_helper\Service\CustomTranslations;
use Drupal\Core\Language\LanguageManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Drupal\Core\Render\Markup;

/**
 * Twig extension for custom translations.
 */
class CustomTranslationExtension extends AbstractExtension {

  protected LanguageManagerInterface $languageManager;
  protected CustomTranslations $customTranslations;

  /**
   * Constructor.
   */
  public function __construct(LanguageManagerInterface $languageManager, CustomTranslations $customTranslations) {
    $this->languageManager = $languageManager;
    $this->customTranslations = $customTranslations;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('bestor', [$this, 'translate'], [
        'is_safe' => ['html'],
      ]),
    ];
  }


  /**
   * Get custom translation.
   */
  public function translate(string $key, ?string $langcode = NULL): Markup {
    if($langcode == NULL){
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    }
    if($key === 'banner_subtitle'){
        return Markup::create($this->customTranslations->generateBannerSubtitleHtml($langcode));
    }
    return Markup::create($this->customTranslations->get($key, $langcode));
  }
}