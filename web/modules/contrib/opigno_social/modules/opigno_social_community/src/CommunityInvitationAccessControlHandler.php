<?php

namespace Drupal\opigno_social_community;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social_community\Entity\Community;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Entity\CommunityInvitationInterface;
use Drupal\opigno_social_community\Services\CommunityManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Opigno community invitation entity access handler.
 *
 * @package Drupal\opigno_social_community
 */
class CommunityInvitationAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Whether the community functionality enabled on the site or not.
   *
   * @var bool
   */
  protected bool $communitiesEnabled;

  /**
   * The Opigno community manager service.
   *
   * @var \Drupal\opigno_social_community\Services\CommunityManagerService
   */
  protected CommunityManagerService $communityManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config,
    CommunityManagerService $community_manager,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->communitiesEnabled = (bool) $config->get(Community::ADMIN_CONFIG_NAME)->get('enable_communities') ?? FALSE;
    $this->communityManager = $community_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('config.factory'),
      $container->get('opigno_social_community.manager'),
      $entity_type
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!$entity instanceof CommunityInvitationInterface) {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Community features should be enabled.
    if (!$this->communitiesEnabled) {
      return AccessResult::forbidden('Community features disabled.');
    }

    $community = $entity->getCommunity();
    if (!$community instanceof CommunityInterface) {
      return AccessResult::forbidden('The related community does not exist.');
    }

    $uid = (int) $account->id();
    $is_invitor = $entity->getOwnerId() === $uid;
    $is_invitee = $entity->getInviteeId() === $uid;
    $is_community_owner = $community->getOwnerId() === $uid;
    $is_privileged = $this->communityManager->isUserCommunityPrivileged('cancel_invitation', $account);
    $is_join_request = $entity->isJoinRequest();

    switch ($operation) {
      case 'view':
        // Community invitations can be viewed only by community owners or
        // invitor/invitee.
        $access = AccessResult::allowedIf($is_community_owner || $is_invitee || $is_invitor || $is_privileged);
        break;

      case 'update':
        // The only property that can be updated in community invitation is
        // status. It can be changed only by the invitee user (for invitations)
        // and by the community owner (for join requests).
        $access = AccessResult::allowedIf(
          (!$is_join_request && $is_invitee)
          || ($is_join_request && $is_community_owner)
        );
        break;

      case 'delete':
        $access = AccessResult::allowedIf(
          ($is_invitor && $account->hasPermission('delete own opigno_community_invitation'))
          || ($is_community_owner && $account->hasPermission('delete any invitation to own opigno_community'))
          || $account->hasPermission('delete any opigno_community_invitation')
          || $is_privileged
        );
        break;

      default:
        $access = AccessResult::neutral();
    }

    return $access;
  }

}
