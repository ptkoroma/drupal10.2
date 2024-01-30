<?php

namespace Drupal\opigno_notification\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\Router;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_notification\Entity\OpignoNotification;
use Drupal\opigno_notification\OpignoNotificationInterface;
use Drupal\opigno_notification\Services\OpignoNotificationManager;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the controller for OpignoNotification entity pages.
 *
 * @ingroup opigno_notification
 */
class OpignoNotificationController extends ControllerBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Opigno notifications manager service.
   *
   * @var \Drupal\opigno_notification\Services\OpignoNotificationManager
   */
  protected $notificationsManager;

  /**
   * The router service.
   *
   * @var \Drupal\Core\Routing\Router
   */
  protected $router;

  /**
   * The private messages manager service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  protected $pmService;

  /**
   * OpignoNotificationController constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\opigno_notification\Services\OpignoNotificationManager $notifications_manager
   *   Opigno notifications manager service.
   * @param \Drupal\Core\Routing\Router $router
   *   The router service.
   * @param \Drupal\private_message\Service\PrivateMessageServiceInterface $pm_service
   *   The private messages service.
   */
  public function __construct(
    RendererInterface $renderer,
    AccountInterface $account,
    OpignoNotificationManager $notifications_manager,
    Router $router,
    PrivateMessageServiceInterface $pm_service
  ) {
    $this->renderer = $renderer;
    $this->currentUser = $account;
    $this->notificationsManager = $notifications_manager;
    $this->router = $router;
    $this->pmService = $pm_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('opigno_notification.manager'),
      $container->get('router.no_access_checks'),
      $container->get('private_message.service')
    );
  }

  /**
   * Ajax callback. Marks all current user notifications as read.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function markReadAll(Request $request): AjaxResponse {
    $response = new AjaxResponse();
    $notifications = OpignoNotification::getUnreadNotifications();

    foreach ($notifications as $notification) {
      if (!$notification instanceof OpignoNotificationInterface) {
        continue;
      }

      $notification->setHasRead(TRUE);
      try {
        $notification->save();
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_notification_exception', $e);
      }
    }

    // Reload the page if the user is on Notifications listing page to refresh
    // the view.
    $route = '';
    $url = '';
    if ($request->isXmlHttpRequest()) {
      $url = $request->server->get('HTTP_REFERER');
      $route_info = $this->router->match($url);
      $route = $route_info['_route'] ?? '';
    }

    if ($route === 'view.opigno_notifications.page_all' && $url) {
      $response->addCommand(new RedirectCommand($url));
      return $response;
    }

    // Remove the unread marker in the header and close the dropdown.
    $selector = '.block-notifications__item--notifications .dropdown-menu';
    $response->addCommand(new InvokeCommand($selector, 'addClass', ['hidden']));
    $response->addCommand(new InvokeCommand('.block-notifications__item--notifications .marker', 'addClass', ['hidden']));
    $response->addCommand(new InvokeCommand('.block-notifications__item--notifications .dropdown', 'dropdown', ['toggle']));
    $response->addCommand(new InvokeCommand($selector, 'removeClass', ['show']));

    return $response;
  }

  /**
   * Ajax callback. Get messages and its count.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function getMessages(): AjaxResponse {
    $response = new AjaxResponse();
    $messages_count = $this->pmService->getUnreadThreadCount();
    $notif_count = count($this->notificationsManager->getUserHeaderNotifications());

    // Display the new messages marker if new messages exist.
    if ($messages_count) {
      $response->addCommand(new InvokeCommand('.block-notifications__item--messages .marker', 'removeClass', ['hidden']));
    }
    else {
      $response->addCommand(new InvokeCommand('.block-notifications__item--messages .marker', 'addClass', ['hidden']));
    }

    // Update notifications block.
    $notifications = $this->notificationsManager->renderUserHeaderNotifications();
    $response->addCommand(new HtmlCommand('.block-notifications__item--notifications .dropdown-menu', $notifications));
    if ($notif_count) {
      $response->addCommand(new InvokeCommand('.block-notifications__item--notifications .marker', 'removeClass', ['hidden']));
      $response->addCommand(new InvokeCommand('.block-notifications__item--notifications .dropdown-menu', 'removeClass', ['hidden']));
    }
    else {
      $response->addCommand(new InvokeCommand('.block-notifications__item--notifications .dropdown-menu', 'addClass', ['hidden']));
      $response->addCommand(new InvokeCommand('.block-notifications__item--notifications .marker', 'addClass', ['hidden']));
    }

    return $response;
  }

}
