<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Url;
use Drupal\Core\Language\LanguageManagerInterface;

class UrlProvider {

  protected array $routeAbbreviationMapping = [
      'contact' => 'contact.site_page',
      // ...
    ];
  protected LanguageManagerInterface $languageManager;

  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }


  public function getTranslatedPageUrl(string $route_abbr, array $route_parameters = [], array $options = []): ?Url {
    if (isset($this->routeAbbreviationMapping[$route_abbr])) {
      $route_name = $this->routeAbbreviationMapping[$route_abbr];
    } else {
      $route_name =  $route_abbr;
    }

    return $this->getTranslatedUrlFromRoute($route_name, $route_parameters, $options);
  }


  public function getTranslatedUrlFromRoute (string $route_name, array $route_parameters = [], array $options = []): ?Url{
    if (!isset($options['language']) || !$options['language'] instanceof LanguageInterface) {
      $options['language'] = $this->languageManager->getCurrentLanguage();
    }
    return Url::fromRoute($route_name, $route_parameters, $options) ?? NULL;
  }
}