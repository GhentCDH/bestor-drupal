<?php

namespace Drupal\customize_admin_menu_per_role\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AdminConfigRedirectSubscriber implements EventSubscriberInterface {

  protected $currentUser;
  protected $pathMatcher;
  protected $urlGenerator;

  public function __construct(AccountInterface $current_user, PathMatcherInterface $path_matcher, UrlGeneratorInterface $url_generator) {
    $this->currentUser = $current_user;
    $this->pathMatcher = $path_matcher;
    $this->urlGenerator = $url_generator;
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 31],
    ];
  }

  public function onKernelRequest(RequestEvent $event) {
    $request = $event->getRequest();
    $path = $request->getPathInfo();

    if ($path === '/admin') {
      if (!\Drupal::currentUser()->hasPermission('administer site configuration')) {
        if (\Drupal::currentUser()->hasPermission('access webmanager dashboard')) {
          $url = $this->urlGenerator->generateFromRoute('customize_admin_menu_per_role.webmanager_dashboard');
          $response = new RedirectResponse($url);
          $event->setResponse($response);
        } elseif (\Drupal::currentUser()->hasPermission('access redactor dashboard')) {
          $url = $this->urlGenerator->generateFromRoute('customize_admin_menu_per_role.redactor_dashboard');
          $response = new RedirectResponse($url);
          $event->setResponse($response);
        }
      }
    }
  }
}
