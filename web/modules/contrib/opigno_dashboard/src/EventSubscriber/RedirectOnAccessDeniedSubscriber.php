<?php

namespace Drupal\opigno_dashboard\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines the redirect on 403 event subscriber.
 *
 * @package Drupal\opigno_dashboard\EventSubscriber
 */
class RedirectOnAccessDeniedSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a new ResponseSubscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Path\CurrentPathStack $path
   *   The current path service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack service.
   */
  public function __construct(
    AccountInterface $current_user,
    RouteMatchInterface $route_match,
    CurrentPathStack $path,
    RequestStack $request
  ) {
    $this->user = $current_user;
    $this->routeMatch = $route_match;
    $this->currentPath = $path;
    $this->request = $request->getCurrentRequest();
  }

  /**
   * The Kernel request callback.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The Kernel event.
   */
  public function onKernelRequest(RequestEvent $event): void {
    $is_anonymous = $this->user->isAnonymous();
    // Add the route name as an extra class to body.
    $route = $this->routeMatch->getRouteName();
    if (!$is_anonymous || in_array($route, [
      'user.login',
      'user.register',
      'user.pass',
      'view.frontpage.page_1',
      'view.opigno_training_catalog.training_catalogue',
      'system.403',
    ])) {
      return;
    }

    $request = $event->getRequest();
    $access_result = AccessResult::neutral();
    if (!$access_result->isAllowed()) {
      if ($access_result instanceof CacheableDependencyInterface && $request->isMethodCacheable()) {
        throw new CacheableAccessDeniedHttpException($access_result, $access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : NULL);
      }
      else {
        throw new AccessDeniedHttpException($access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : NULL);
      }
    }
  }

  /**
   * Redirect if 403 and node an event.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The route building event.
   */
  public function redirectOn403(ResponseEvent $event) {
    $route_name = $this->routeMatch->getRouteName();
    $status_code = $event->getResponse()->getStatusCode();
    $is_anonymous = $this->user->isAnonymous();

    // Do not redirect if there is REST request.
    if ($route_name && str_contains($route_name, 'rest.')) {
      return;
    }

    // Do not redirect if there is a token authorization.
    $auth_header = $event->getRequest()->headers->get('Authorization') ?? '';
    if ($is_anonymous && preg_match('/^Bearer (.*)/', $auth_header)) {
      return;
    }

    if ($is_anonymous && $status_code === 403) {
      $current_path = $this->currentPath->getPath();

      // Filter out ajax requests from opigno_social from redirect.
      if (!str_contains($current_path, '/ajax/')) {
        $response = new RedirectResponse($this->request
          ->getBasePath() . "/user/login/?prev_path={$current_path}");

        $event->setResponse($response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['redirectOn403'];
    return $events;
  }

}
