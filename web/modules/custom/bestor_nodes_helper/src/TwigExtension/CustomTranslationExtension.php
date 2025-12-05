<?php

namespace Drupal\bestor_nodes_helper\TwigExtension;

use Drupal\bestor_nodes_helper\Service\CustomTranslations;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for custom translations.
 */
class CustomTranslationExtension extends AbstractExtension {

  /**
   * The custom translations service.
   */
  protected CustomTranslations $customTranslations;

  /**
   * Constructor.
   */
  public function __construct(CustomTranslations $custom_translations) {
    $this->customTranslations = $custom_translations;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('bestor', [$this, 'translate']),
    ];
  }

  /**
   * Get custom translation.
   */
  public function translate(string $key, ?string $langcode = NULL): string {
    return $this->customTranslations->get($key, $langcode);
  }

}