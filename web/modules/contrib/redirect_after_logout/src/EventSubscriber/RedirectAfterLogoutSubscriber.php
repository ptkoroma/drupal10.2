<?php

namespace Drupal\redirect_after_logout\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * RedirectAfterLogoutSubscriber event subscriber.
 *
 * @package Drupal\redirect_after_logout\EventSubscriber
 */
class RedirectAfterLogoutSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The request stack.
   *
   * @var \Drupal\Core\Http\RequestStack
   */
  protected $requestStack;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param RedirectDestinationInterface $redirectDestination
   *   The redirect destination.
   * @param \Drupal\Core\Http\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\RequestContext $requestContext
   *   The request context.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(ConfigFactory $configFactory,
                              AccountProxyInterface $currentUser,
                              MessengerInterface $messenger,
                              RedirectDestinationInterface $redirectDestination,
                              RequestStack $request_stack,
                              RequestContext $requestContext,
                              Token $token) {
    $this->configFactory = $configFactory;
    $this->currentUser = $currentUser;
    $this->messenger = $messenger;
    $this->redirectDestination = $redirectDestination;
    $this->requestStack = $request_stack;
    $this->requestContext = $requestContext;
    $this->token = $token;
  }

  /**
   * Check redirection.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Event.
   */
  public function checkRedirection(ResponseEvent $event) {
    $destination = &drupal_static('redirect_after_logout_user_logout');
    $response = $event->getResponse();
    if (!$response instanceof RedirectResponse || !(bool) $destination) {
      return;
    }

    if ($destination === '<front>') {
      $destination = Url::fromRoute($destination);
    }
    elseif (UrlHelper::isExternal($destination)) {
      $destination = Url::fromUri($destination);
    }
    else {
      $destination = Url::fromUri('internal:' . $destination);
    }

    $config = $this->configFactory->get('redirect_after_logout.settings');
    $logout_message = $config->get('message');
    $base_url = $this->requestContext->getCompleteBaseUrl();
    if ($logout_message === '' || ($destination->isExternal() && !UrlHelper::externalIsLocal($destination->toString(), $base_url))) {
      $destination = $destination->toString();
    }
    else {
      $destination = $destination
        ->setOption('query', ['logout-message' => 1])
        ->toString();
    }

    $response = new RedirectResponse($destination);
    $response->send();
  }

  /**
   * Check redirection.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Event.
   */
  public function showMessage(RequestEvent $event) {
    $parameter_bag = $this->requestStack->getCurrentRequest()->query;
    // Set logout message.
    if ((bool) $parameter_bag->get('logout-message') && $this->currentUser->isAnonymous()) {
      $config = $this->configFactory->get('redirect_after_logout.settings');
      $logout_message = nl2br($config->get('message'));
      $this->messenger->addMessage(['#markup' => Xss::filter($this->token->replace($logout_message), ['br'])], $config->get('message_type'));
    }
    elseif ((bool) $parameter_bag->get('logout-message') && !$this->currentUser->isAnonymous()) {
      $destination = $this->redirectDestination->getAsArray();
      $current_url = Url::fromRoute('<current>');
      $path = $current_url->getInternalPath();
      $path_args = explode('/', $path);
      if ($path_args !== FALSE) {
        $destination = implode('/', $path_args);
      }
      $response = new RedirectResponse($destination);
      $response->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[KernelEvents::RESPONSE][] = ['checkRedirection'];
    $events[KernelEvents::REQUEST][] = ['showMessage', 50];
    return $events;
  }

}
