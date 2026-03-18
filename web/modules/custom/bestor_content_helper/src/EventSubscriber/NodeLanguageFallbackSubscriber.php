<?php

namespace Drupal\bestor_content_helper\EventSubscriber;

use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects to the "translation unavailable" page when a node is not published
 * in the current interface language.
 *
 * Uses KernelEvents::EXCEPTION because access checking happens inside the
 * router at priority 33, before KernelEvents::REQUEST subscribers at lower
 * priorities can intercept the request.
 */
class NodeLanguageFallbackSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected LanguageManagerInterface $languageManager,
  ) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 0]];
  }

  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();

    // Only handle access denied exceptions.
    if (!$exception instanceof CacheableAccessDeniedHttpException) {
      return;
    }

    $request = $event->getRequest();
    $node = $request->attributes->get('node');

    if (!$node instanceof NodeInterface) {
      return;
    }

    // Avoid redirect loop on the unavailable page itself.
    if ($request->attributes->get('_route') === 'bestor_content_helper.translation_unavailable') {
      return;
    }

    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Only intercept if the node lacks a published translation in the current
    // language — not for genuine permission issues on published content.
    if ($node->hasTranslation($langcode) && $node->getTranslation($langcode)->isPublished()) {
      return;
    }

    $url = Url::fromRoute('bestor_content_helper.translation_unavailable', ['nid' => $node->id()])
      ->setOption('language', $this->languageManager->getCurrentLanguage())
      ->toString();

    $event->setResponse(new TrustedRedirectResponse($url, 302));
  }

}