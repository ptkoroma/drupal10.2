<?php

namespace Drupal\opigno_social\Services;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\opigno_learning_path\Services\UserAccessManager;
use Drupal\opigno_messaging\Services\OpignoMessageThread;
use Drupal\opigno_social\Entity\UserInvitationInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;

/**
 * The user invitation manager service.
 *
 * @package Drupal\opigno_social\Services
 */
class UserConnectionManager {

  use StringTranslationTrait;

  /**
   * The cache tag prefix for the user connections.
   */
  const USER_CONNECTIONS_CACHE_TAG_PREFIX = 'opigno_user_connections_';

  /**
   * User invitees cache prefix.
   *
   * The cache ID prefix for the list of users who have sent the connection to
   * the current one.
   */
  const USER_INVITEES_CACHE_PREFIX = 'opigno_user_invitees_';

  /**
   * The cache ID prefix for the list of users were invited by the current one.
   */
  const INVITED_BY_USER_CACHE_PREFIX = 'opigno_invited_by_user_';

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * User the invitation entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $invitationStorage = NULL;

  /**
   * User entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $userStorage = NULL;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user ID.
   *
   * @var int
   */
  public $currentUid;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  public $user;

  /**
   * Whether the social features enabled or not.
   *
   * @var bool
   */
  protected $socialsEnabled;

  /**
   * Opigno user access manager.
   *
   * @var \Drupal\opigno_learning_path\Services\UserAccessManager
   */
  protected $userAccessManager;

  /**
   * Opigno private messaging manager service.
   *
   * @var \Drupal\opigno_messaging\Services\OpignoMessageThread
   */
  protected $messageService;

  /**
   * The path to Aristotle theme.
   *
   * @var string
   */
  protected string $aristotleThemePath;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * UserConnectionManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   CSRF token generator service.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\opigno_learning_path\Services\UserAccessManager $access_manager
   *   Opigno user access manager.
   * @param \Drupal\opigno_messaging\Services\OpignoMessageThread $pm_service
   *   The private messages manager service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_list
   *   Theme extension list service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    CacheBackendInterface $cache,
    CsrfTokenGenerator $csrf_token,
    Connection $database,
    ConfigFactoryInterface $config_factory,
    UserAccessManager $access_manager,
    OpignoMessageThread $pm_service,
    ThemeExtensionList $theme_list,
    FileUrlGeneratorInterface $file_url_generator
  ) {
    $this->user = $current_user;
    $this->currentUid = (int) $current_user->id();
    $this->cache = $cache;
    $this->csrfToken = $csrf_token;
    $this->database = $database;
    $this->socialsEnabled = (bool) $config_factory->get('opigno_class.socialsettings')->get('enable_social_features') ?? FALSE;
    $this->userAccessManager = $access_manager;
    $this->messageService = $pm_service;
    $this->aristotleThemePath = $theme_list->getPath('aristotle');
    $this->fileUrlGenerator = $file_url_generator;

    try {
      $this->invitationStorage = $entity_type_manager->getStorage('user_invitation');
      $this->userStorage = $entity_type_manager->getStorage('user');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }
  }

  /**
   * Check if the user with the given ID exists on site.
   *
   * @param string|int $uid
   *   User ID to be checked.
   *
   * @return bool
   *   Whether the user with the given ID exists or not.
   */
  public function isUserExists($uid): bool {
    if (!$uid || !$this->userStorage instanceof UserStorageInterface) {
      return FALSE;
    }
    $account = $this->userStorage->load($uid);

    return $account instanceof UserInterface && !$account->isAnonymous() && $account->isActive();
  }

  /**
   * Get the user invitation if it exists.
   *
   * @param string|int $owner
   *   The invitation owner user ID.
   * @param string|int $invitee
   *   The invitee user ID.
   *
   * @return \Drupal\opigno_social\Entity\UserInvitationInterface|null
   *   The user invitation.
   */
  public function getInvitation($owner, $invitee = ''): ?UserInvitationInterface {
    if (!$invitee) {
      $invitee = $this->currentUid;
    }

    // Do nothing if there are no users with given IDs or if user somehow tries
    // to send an invitation to themselves.
    if (!$this->invitationStorage instanceof EntityStorageInterface
      || !$this->isUserExists($invitee)
      || !$this->isUserExists($owner)
      || (int) $owner === (int) $invitee
    ) {
      return NULL;
    }

    $properties = [
      'uid' => $owner,
      'invitee' => $invitee,
    ];
    $invitations = $this->invitationStorage->loadByProperties($properties);
    $invitation = $invitations ? reset($invitations) : NULL;

    return $invitation instanceof UserInvitationInterface ? $invitation : NULL;
  }

  /**
   * Check if the invitation can be sent.
   *
   * @param string|int $invitee
   *   Invitee user ID.
   *
   * @return bool
   *   TRUE if the invitation from the current user can be sent to invitee,
   *   FALSE otherwise.
   */
  public function invitationCanBeSent($invitee): bool {
    $invitee = (int) $invitee;
    // The invitation can be sent if there are no existing invitations from the
    // current user to invitee (or vice versa).
    if (!$this->isUserExists($invitee)
      || $invitee === $this->currentUid
      || $this->getInvitation($this->currentUid, $invitee)
      || $this->getInvitation($invitee, $this->currentUid)
    ) {
      return FALSE;
    }

    // Get the list of possible connections depending on the selected social
    // setting.
    $users = opigno_messaging_get_all_recipients(FALSE);
    foreach ($users as $user) {
      if ($user instanceof UserInterface && (int) $user->id() === $invitee) {
        return $user->isActive();
      }
    }

    return FALSE;
  }

  /**
   * Check if the invitation can be accepted.
   *
   * @param string|int $owner
   *   Invitation owner user ID.
   *
   * @return \Drupal\opigno_social\Entity\UserInvitationInterface|bool
   *   User invitation entity if it can be accepted, FALSE otherwise.
   */
  public function invitationCanBeAccepted($owner) {
    $invitation = $this->getInvitation($owner);

    // Return the invitation entity if it can be accepted.
    // If there are no invitations or invitation has already been accepted,-
    // do nothing.
    return $invitation instanceof UserInvitationInterface && !$invitation->isAccepted() ? $invitation : FALSE;
  }

  /**
   * Check if the invitation can be declined.
   *
   * @param string|int $owner
   *   Invitation owner user ID.
   * @param string|int $invitee
   *   Invitee user ID.
   *
   * @return \Drupal\opigno_social\Entity\UserInvitationInterface|bool
   *   User invitation entity if it can be declined, FALSE otherwise.
   */
  public function invitationCanBeDeclined($owner, $invitee = '') {
    $invitation = $this->getInvitation($owner, $invitee);

    // Return invitation entity if it exists.
    // If there are no invitations or invitation wasn't accepted, do nothing.
    return $invitation instanceof UserInvitationInterface ? $invitation : FALSE;
  }

  /**
   * Protect the url with the CSRF token to make AJAX request secure.
   *
   * @param \Drupal\Core\Url $url
   *   The url to be protected.
   */
  public function protectUrl(Url $url): void {
    $internal = $url->getInternalPath();
    $url->setOption('query', ['token' => $this->csrfToken->get($internal)]);
  }

  /**
   * Get connection links available for the given user.
   *
   * @param int $uid
   *   The ID of user to return the connection links for.
   *
   * @return array
   *   Render array of connection links available for the given user.
   */
  public function getUserConnectionLinks(int $uid): array {
    $build = [
      '#theme' => 'opigno_social_connect_links',
      '#prefix' => '<div id="opigno-social-connection-links-' . $uid . '">',
      '#suffix' => '</div>',
      '#actions' => [],
      '#cache' => [
        'tags' => [
          static::USER_CONNECTIONS_CACHE_TAG_PREFIX . $this->currentUid,
          static::USER_CONNECTIONS_CACHE_TAG_PREFIX . $uid,
          'config:opigno_class.socialsettings',
          'user:' . $uid,
        ],
        'contexts' => ['user'],
      ],
    ];

    // If invitation wasn't sent to the user - there is only one link available.
    $classes = ['opigno-invitation-action', 'use-ajax', 'btn-connection'];
    if ($this->invitationCanBeSent($uid)) {
      $options = [
        'attributes' => [
          'class' => array_merge($classes, ['invite']),
        ],
      ];
      $url = Url::fromRoute('opigno_social.send_user_invitation', ['invitee' => $uid], $options);
      $this->protectUrl($url);
      $build['#actions']['send'] = Link::fromTextAndUrl($this->t('Invite', [], ['context' => 'Opigno: User connection']), $url);

      return $build;
    }

    // Generate an "Accept" link, if the invitation from the viewed user is
    // available.
    if ($this->invitationCanBeAccepted($uid)) {
      $options = [
        'attributes' => [
          'class' => array_merge($classes, ['accept']),
        ],
      ];
      $url = Url::fromRoute('opigno_social.accept_user_invitation', ['owner' => $uid], $options);
      $this->protectUrl($url);
      $build['#actions']['accept'] = Link::fromTextAndUrl($this->t('Accept', [], ['context' => 'Opigno: User connection']), $url);
    }

    // Add the "Decline" link.
    $this->addDeclineLink($build['#actions'], $uid, $classes);

    return $build;
  }

  /**
   * Add the "Decline" action to the list of existing connection links.
   *
   * @param array $links
   *   The list of user connection links.
   * @param int $uid
   *   The ID of user to return the "Decline connection" link for.
   * @param array $class
   *   The list of default classes that should be added to the link.
   */
  private function addDeclineLink(array &$links, int $uid, array $class): void {
    $data = $this->getDeclineInvitationDataByOneUser($uid);
    $invitation = $data['invitation'] ?? NULL;
    if (!$invitation instanceof UserInvitationInterface) {
      return;
    }

    $key = 'decline_accepted';
    $title = $this->t('Remove connection', [], ['context' => 'Opigno: User connection']);

    // The button should look different for the accepted, pending and sent
    // invitations.
    if ($invitation->isAccepted()) {
      // The dropdown links, classes should be different.
      $class = ['opigno-invitation-action', 'use-ajax', 'dropdown-item-text'];
      // Add the message link if the invitation is already accepted.
      $this->addMessageLink($links, $uid);
    }
    else {
      // The case when the invitation was sent by the current user, but hasn't
      // been accepted by the invitee yet.
      if ($data['from_current']) {
        $title = Markup::create($this->t('Invited', [], ['context' => 'Opigno: User connection']) . '<i class="fi fi-rr-cross-small"></i>');
        $class[] = 'invited';
        $key = 'decline_sent';
      }
      // Decline the pending invitation.
      else {
        $title = $this->t('Decline', [], ['context' => 'Opigno: User connection']);
        $class[] = 'decline';
        $key = 'decline_pending';
      }
    }

    $options = [
      'attributes' => ['class' => $class],
    ];
    $url = Url::fromRoute('opigno_social.decline_user_invitation', $data['params'], $options);
    $this->protectUrl($url);
    $links[$key] = Link::fromTextAndUrl($title, $url);
  }

  /**
   * Get the invitation data by one user if it can be declined.
   *
   * @param int $uid
   *   The user ID (invitation owner or invitee).
   *
   * @return array
   *   The invitation data.
   */
  public function getDeclineInvitationDataByOneUser(int $uid): array {
    // Check if the invitation from the viewed user exists and can be declined.
    $invitation = $this->invitationCanBeDeclined($uid);
    $params = [
      'owner' => $uid,
      'invitee' => $this->currentUid,
    ];
    $from_current = FALSE;

    // Check if the invitation to the viewed user exists and can be declined.
    if (!$invitation instanceof UserInvitationInterface) {
      $invitation = $this->invitationCanBeDeclined($this->currentUid, $uid);
      $params = [
        'owner' => $this->currentUid,
        'invitee' => $uid,
      ];
      $from_current = TRUE;
    }

    if (!$invitation instanceof UserInvitationInterface) {
      return [];
    }

    return [
      'invitation' => $invitation,
      'params' => $params,
      'from_current' => $from_current,
    ];
  }

  /**
   * Add the "send message" action to the list of existing connection links.
   *
   * @param array $links
   *   The list of user connection links.
   * @param int $uid
   *   The ID of user to return the link for.
   */
  private function addMessageLink(array &$links, int $uid): void {
    $url = $this->getMessageUrl($uid);
    $links['message'] = $url->toString();
  }

  /**
   * Gets the "send message" url.
   *
   * @param int $uid
   *   The user ID to send the message to.
   *
   * @return \Drupal\Core\Url
   *   The messaging url.
   */
  public function getMessageUrl(int $uid): Url {
    $members = [$this->currentUid, $uid];
    $thread = $this->messageService->getThreadForMembers($members, FALSE);
    // Add the link to the thread with the recipient if it already exists,
    // create a new thread otherwise.
    if ($thread instanceof PrivateMessageThreadInterface) {
      $url = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $thread->id()]);
    }
    else {
      $url = Url::fromRoute('opigno_messaging.redirect_to_new_thread', ['uid' => $uid]);
    }

    return $url;
  }

  /**
   * Get the list of user IDs who have sent the connection to the current user.
   *
   * @param string|bool $invitation_status
   *   The invitation status.
   * @param int $uid
   *   The user ID. Leave empty to get the data for the current user.
   *
   * @return array
   *   The list of user IDs who have sent the connection to the current user.
   */
  public function getUserInvitees($invitation_status = 'any', int $uid = 0): array {
    $uid = $uid ?: $this->currentUid;
    // Try to get the list from cache.
    $cid = static::USER_INVITEES_CACHE_PREFIX . $uid . "_$invitation_status";
    $cached = $this->cache->get($cid)->data ?? [];
    if ($cached && is_array($cached)) {
      return $cached;
    }

    $query = $this->database->select('opigno_user_invitations', 'oui');
    $query->join('users_field_data', 'ufd', 'ufd.uid = oui.uid');
    $query->fields('oui', ['uid'])
      ->condition('oui.invitee', $uid)
      ->condition('ufd.status', '1');
    // Check the invitation status, if needed.
    if (is_bool($invitation_status)) {
      $query->condition('oui.status', $invitation_status);
    }

    $result = $query->execute()->fetchCol();
    // Cache the result.
    $this->cache->set($cid, $result, Cache::PERMANENT, [static::USER_CONNECTIONS_CACHE_TAG_PREFIX . $uid]);

    return $result;
  }

  /**
   * Get the list of user IDs who were invited by the current user.
   *
   * @param string|bool $invitation_status
   *   The invitation status.
   * @param int $uid
   *   The user ID to get the list of users invited by. Leave empty to get the
   *   data for the current user.
   *
   * @return array
   *   The list of user IDs who were invited by the current user.
   */
  public function getInvitedByUser($invitation_status = 'any', int $uid = 0): array {
    $uid = $uid ?: $this->currentUid;
    // Try to get the list from cache.
    $cid = static::INVITED_BY_USER_CACHE_PREFIX . $uid . "_$invitation_status";
    $cached = $this->cache->get($cid)->data ?? [];
    if ($cached && is_array($cached)) {
      return $cached;
    }

    $query = $this->database->select('opigno_user_invitations', 'oui');
    $query->join('users_field_data', 'ufd', 'ufd.uid = oui.invitee');
    $query->fields('oui', ['invitee'])
      ->condition('oui.uid', $uid)
      ->condition('ufd.status', '1');
    // Check the invitation status, if needed.
    if (is_bool($invitation_status)) {
      $query->condition('oui.status', $invitation_status);
    }

    $result = $query->execute()->fetchCol();
    // Cache the result.
    $this->cache->set($cid, $result, Cache::PERMANENT, [static::USER_CONNECTIONS_CACHE_TAG_PREFIX . $uid]);

    return $result;
  }

  /**
   * Get the list of IDs of users who are in the current user's network.
   *
   * @param int $uid
   *   The user ID to get the network for. Leave empty to get the data for the
   *   current user.
   *
   * @return array
   *   The list of IDs of users who are in the current user's network.
   */
  public function getUserNetwork(int $uid = 0): array {
    // Get the list of accepted invitations that were created by the current
    // user.
    $sent = $this->getInvitedByUser(TRUE, $uid);
    // Get the list of invitations that were accepted by the current user.
    $received = $this->getUserInvitees(TRUE, $uid);

    return array_merge($sent, $received);
  }

  /**
   * Prepare the render array to display the user connections block.
   *
   * @param int $uid
   *   The user ID to show connections of.
   *
   * @return array
   *   The render array to display the user connections block.
   */
  public function renderUserConnectionsBlock(int $uid = 0): array {
    $build = [
      '#cache' => [
        'tags' => [
          'config:opigno_class.socialsettings',
          UserConnectionManager::USER_CONNECTIONS_CACHE_TAG_PREFIX . $uid,
        ],
        'contexts' => ['user'],
      ],
    ];

    if (!$this->socialsEnabled) {
      return $build;
    }

    $uid = $uid ?: $this->currentUid;
    $img_path = $this->aristotleThemePath . '/src/images/design/connections.svg';
    $connections = count($this->getUserNetwork($uid));

    return [
      '#theme' => 'opigno_statistics_user_achievement',
      '#count' => $connections,
      '#text' => $this->formatPlural($connections, 'Connection', 'Connections'),
      '#subtitle' => $uid === $this->currentUid ? $this->t('Manage connections') : '',
      '#img' => file_exists($img_path) ? $this->fileUrlGenerator->transformRelative(base_path() . $img_path) : '',
      '#url' => $uid === $this->currentUid ? Url::fromRoute('opigno_social.manage_connections')->toString() : '',
    ] + $build;
  }

}
