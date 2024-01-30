<?php

namespace Drupal\opigno_social\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Url;
use Drupal\opigno_social\Entity\UserInvitationInterface;
use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * User connections controller.
 *
 * @package \Drupal\opigno_social\Controller
 */
class UserConnectionsController extends ControllerBase {

  /**
   * User connection manager service.
   *
   * @var \Drupal\opigno_social\Services\UserConnectionManager
   */
  protected $connectionManager;

  /**
   * User entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $userStorage = NULL;

  /**
   * User the invitation entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $invitationStorage = NULL;

  /**
   * UserInvitationController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\opigno_social\Services\UserConnectionManager $connection_manager
   *   The user connection manager service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    UserConnectionManager $connection_manager
  ) {
    $this->connectionManager = $connection_manager;
    try {
      $this->userStorage = $entity_type_manager->getStorage('user');
      $this->invitationStorage = $entity_type_manager->getStorage('user_invitation');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('opigno_user_connection.manager')
    );
  }

  /**
   * The user invitation send action.
   *
   * @param int $invitee
   *   The invitee user ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response object.
   */
  public function send(int $invitee): AjaxResponse {
    $uid = $this->connectionManager->currentUid;
    if (!$this->connectionManager->invitationCanBeSent($invitee)) {
      return new AjaxResponse(NULL, 400);
    }

    // Create the new User Invitation entity.
    try {
      $invitation = $this->invitationStorage->create([
        'uid' => $uid,
        'invitee' => $invitee,
        'status' => FALSE,
      ]);
      $invitation->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_exception', $e);
      $invitation = NULL;
    }

    if (!$invitation instanceof UserInvitationInterface) {
      return new AjaxResponse(NULL, 400);
    }

    // Invalidate an appropriate cache tags.
    Cache::invalidateTags([
      UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $uid,
      UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $invitee,
    ]);

    $response = new AjaxResponse();
    $links = $this->connectionManager->getUserConnectionLinks((int) $invitee);
    $response->addCommand(new ReplaceCommand("#opigno-social-connection-links-$invitee", $links));

    return $response;
  }

  /**
   * Accept the user invitation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param int $owner
   *   Invitation owner user ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response object.
   */
  public function accept(Request $request, int $owner = 0): AjaxResponse {
    $invitation = $this->connectionManager->invitationCanBeAccepted($owner);
    if (!$invitation instanceof UserInvitationInterface) {
      return new AjaxResponse(NULL, 400);
    }

    // Update the invitation status.
    $invitation->setAccepted();
    try {
      $invitation->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_exception', $e);
      return new AjaxResponse(NULL, 400);
    }

    // Invalidate an appropriate cache tags.
    Cache::invalidateTags([
      UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $owner,
      UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $invitation->getInviteeId(),
    ]);

    $response = new AjaxResponse();
    $this->reloadPage($response, $request);

    // Create the notification for the invitation owner.
    $msg = $this->t('@user accepted your invitation', [
      '@user' => $this->connectionManager->user->getDisplayName(),
    ]);
    $url = Url::fromRoute('entity.user.canonical', ['user' => $this->connectionManager->currentUid])->toString();
    $url = $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url;

    try {
      opigno_set_message($owner, $msg, $url);
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }

    return $response;
  }

  /**
   * Decline the user invitation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param int $owner
   *   Invitation owner user ID.
   * @param int $invitee
   *   Invitee user ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response object.
   */
  public function decline(Request $request, int $owner = 0, int $invitee = 0): AjaxResponse {
    $invitation = $this->connectionManager->invitationCanBeDeclined($owner, $invitee);
    if (!$invitation instanceof UserInvitationInterface || !$this->invitationStorage instanceof EntityStorageInterface) {
      return new AjaxResponse(NULL, 400);
    }

    // Remove the invitation if it was declined by the invitee.
    try {
      $invitation->delete();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_exception', $e);
      return new AjaxResponse(NULL, 400);
    }

    // Invalidate an appropriate cache tags.
    Cache::invalidateTags([
      UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $owner,
      UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $invitee,
    ]);

    $response = new AjaxResponse();
    $this->reloadPage($response, $request);

    return $response;
  }

  /**
   * Add the reload command to the current request.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response object.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  private function reloadPage(AjaxResponse $response, Request $request): void {
    // Get the current url form AJAX request to reload the page.
    if (!$request->isXmlHttpRequest()) {
      return;
    }

    $url = $request->server->get('HTTP_REFERER');
    $response->addCommand(new RedirectCommand($url));
  }

  /**
   * Manage user connections page callback.
   *
   * @return array
   *   Render array to display the "Manage connections" page content.
   */
  public function manageConnections(): array {
    return [
      '#theme' => 'opigno_manage_connections_page',
      '#suggested' => Views::getView('user_invitations')->executeDisplay('suggested'),
      '#network' => Views::getView('user_invitations')->executeDisplay('network'),
      '#pending' => Views::getView('user_connections')->executeDisplay('pending'),
      '#cache' => [
        'tags' => [UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $this->connectionManager->currentUid],
        'contexts' => ['user'],
      ],
    ];
  }

}
