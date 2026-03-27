<?php

namespace Drupal\bestor_content_helper\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Displays an informational page when a translation is not available in the
 * current language, listing all available language versions.
 */
class TranslationUnavailableController extends ControllerBase {

  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('language_manager'),
    );
  }

  /**
   * Renders the "translation not available" page.
   *
   * @param int $nid
   *   The node that is not available in the current language.
   *
   * @return array
   *   A render array.
   */
  public function page(int $nid): array|RedirectResponse {
    $node = $this->entityTypeManager()->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $current_language = $this->languageManager->getCurrentLanguage();
    $current_langcode =  $current_language->getId();

    if ($node->hasTranslation($current_langcode) && $node->getTranslation($current_langcode)->isPublished()) {
      $url = $node->getTranslation($current_langcode)
        ->toUrl('canonical')
        ->setOption('language',  $current_language)
        ->toString();
      return new RedirectResponse($url, 302);
    }

    // Build links to all available published translations.
    $language_links = [];
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      $translation = $node->getTranslation($langcode);
      if (!$translation->isPublished()) {
        continue;
      }

      $language_links[$langcode] = [
        'title' => $translation->getTitle(),
        'language_name' => $language->getName(),
        'url' => $translation->toUrl('canonical')
          ->setOption('language', $language)
          ->toString(),
      ];
    }

    return [
      '#theme' => 'bestor_translation_unavailable',
      '#node' => $node,
      '#current_language' => $current_language->getName(),
      '#language_links' => $language_links,
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => ['languages:language_interface'],
      ],
    ];
  }


  /**
   * Title callback — returns the node title for the page title.
   */
  public function title(int $nid): string {
    $node = $this->entityTypeManager()->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface) {
      return '';
    }

    // Build the list of available published translations to determine the title.
    $has_translation = FALSE;
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      if ($node->getTranslation($langcode)->isPublished()) {
        $has_translation = TRUE;
        break;
      }
    }

    return $has_translation
      ? $this->t('Content available in other language')->render()
      : $this->t('Content not available')->render();
  }
}