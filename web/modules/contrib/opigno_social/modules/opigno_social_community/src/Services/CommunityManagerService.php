<?php

namespace Drupal\opigno_social_community\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\opigno_social_community\Entity\Community;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\user\UserInterface;

/**
 * Defines the community manager service.
 *
 * @package Drupal\opigno_social_community\Services
 */
class CommunityManagerService {

  use StringTranslationTrait;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The community entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $communityStorage;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The administer community configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * CommunityManagerService constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
    ConfigFactoryInterface $config,
    Connection $database
  ) {
    $this->account = $account;
    $this->communityStorage = $entity_type_manager->getStorage('opigno_community');
    $this->routeMatch = $route_match;
    $this->config = $config->get(Community::ADMIN_CONFIG_NAME);
    $this->database = $database;
  }

  /**
   * Gets the communities the user is member of.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The user to get communities for. The current user will be taken by
   *   default.
   * @param bool $load
   *   Should the communities be loaded or not. If FALSE, only the list of
   *   community IDs will be returned.
   *
   * @return array
   *   The list of the communities (or IDs) the given user is member of.
   */
  public function getJoinedCommunities(?AccountInterface $user = NULL, bool $load = FALSE): array {
    $user = $user ?? $this->account;
    $communities = $this->communityStorage->getQuery()
      ->condition('members', $user->id())
      ->execute();
    if (!$communities || !$load) {
      return $communities;
    }

    return $this->communityStorage->loadMultiple($communities);
  }

  /**
   * Prepares the render array to display the community member links sidebar.
   *
   * @return array
   *   The render array to display the community member links sidebar.
   */
  public function getCommunityMemberLinksSidebar(): array {
    $cid = $this->routeMatch->getRawParameter('opigno_community');
    $current_route = $this->routeMatch->getRouteName();
    $available_routes = [
      'view.communities.members' => [
        'link_name' => $this->t('Members'),
        'title' => $this->t('Members list'),
      ],
      'view.community_invitations.community_sent' => [
        'link_name' => $this->t('Invitations sent'),
        'title' => $this->t('Connections'),
      ],
    ];

    // Add the "Pending request" tab for the restricted communities.
    if ($cid
      && ($community = $this->communityStorage->load($cid))
      && $community instanceof CommunityInterface
      && $community->getVisibility() === Community::VISIBILITY_RESTRICTED
    ) {
      $available_routes['view.community_invitations.pending_approval'] = [
        'link_name' => $this->t('Pending request'),
        'title' => $this->t('Members list'),
      ];
    }

    if (!in_array($current_route, array_keys($available_routes))) {
      return [];
    }

    $links = [];
    foreach ($available_routes as $route => $data) {
      $url = Url::fromRoute($route, ['opigno_community' => $cid]);
      if ($url->access($this->account)) {
        $links[] = [
          'link' => Link::fromTextAndUrl($data['link_name'], $url)->toRenderable(),
          'active' => $current_route === $route,
        ];
      }
    }

    return [
      '#theme' => 'opigno_community_manage_members_links_block',
      '#title' => $available_routes[$current_route]['title'] ?? $this->t('Manage'),
      '#links' => $links,
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];
  }

  /**
   * Checks if the given user has a privileged access to any community.
   *
   * @param string $operation
   *   The operation to check the access to execute. One of the following:
   *   create, update, delete, invite_member.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The user to check the extra access for. The current user will be taken
   *   by default.
   *
   * @return bool
   *   TRUE if the given user is permitted to execute the selected operation on
   *   any community, FALSE otherwise.
   */
  public function isUserCommunityPrivileged(string $operation, ?AccountInterface $user): bool {
    $privileged = $this->config->get('allow_' . $operation) ?? [];
    if (!$privileged) {
      return FALSE;
    }
    $uid = $user instanceof AccountInterface ? $user->id() : $this->account->id();

    return in_array($uid, $privileged);
  }

  /**
   * Gets the render array to display the "Create a community" link.
   *
   * @return array
   *   The render array to display the "Create a community" link.
   */
  public function getCreateCommunityLink(): array {
    $url = Url::fromRoute('opigno_social_community.ajax_create_community_form', [], [
      'attributes' => [
        'class' => ['btn', 'btn-rounded', 'use-ajax'],
      ],
    ]);

    return $url->access($this->account)
      ? Link::fromTextAndUrl($this->t('Create a community'), $url)->toRenderable()
      : [];
  }

  /**
   * Gets the communities the user is already invited to.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The user to get communities for. The current user will be taken by
   *   default.
   * @param bool $load
   *   Should the communities be loaded or not. If FALSE, only the list of
   *   community IDs will be returned.
   *
   * @return array
   *   The list of the communities (or IDs) the given user is invited to.
   */
  public function getInvitedToCommunities(?AccountInterface $user = NULL, bool $load = FALSE): array {
    $uid = $user instanceof AccountInterface ? $user->id() : $this->account->id();
    $communities = $this->database->select('opigno_community_invitation', 'oci')
      ->fields('oci', ['community'])
      ->condition('is_join_request', FALSE)
      ->condition('invitee', $uid)
      ->distinct()
      ->execute()
      ->fetchCol();

    if (!$communities || !$load) {
      return $communities;
    }

    return $this->communityStorage->loadMultiple($communities);
  }

  /**
   * Gets the list of the possible community invitees.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to get the list of possible invitees for.
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $community
   *   The community to get the list of invitees for.
   *
   * @return array
   *   The list of the possible community invitees.
   */
  public static function getAvailableCommunityInvitees(UserInterface $user, CommunityInterface $community): array {
    $show_all = $user->hasPermission('message anyone regardless of groups');
    $available = opigno_messaging_get_all_recipients($show_all);
    if ($community->isNew()) {
      return $available;
    }

    $invited = $community->getAllInvitees();
    $members = $community->getMembers();

    return array_diff_key($available, $invited, $members);
  }

}
