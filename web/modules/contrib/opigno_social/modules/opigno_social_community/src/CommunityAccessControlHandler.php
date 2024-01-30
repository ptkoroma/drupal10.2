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
use Drupal\opigno_social_community\Services\CommunityManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Opigno community entity access handler.
 *
 * @package Drupal\opigno_social_community
 */
class CommunityAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

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
    if (!$entity instanceof CommunityInterface) {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Community features should be enabled.
    if (!$this->communitiesEnabled) {
      return AccessResult::forbidden('Community features disabled.');
    }

    $uid = (int) $account->id();
    // Don't restrict access for superadmin and users who're permitted to
    // execute the given operation on any community.
    if (($uid === 1 && $operation !== 'invite_member')
      || $this->communityManager->isUserCommunityPrivileged($operation, $account)
    ) {
      return AccessResult::allowed();
    }

    $is_owner = $entity->getOwnerId() === $uid;
    $is_member = $entity->isMember($uid);
    $available_to_view = [
      Community::VISIBILITY_PUBLIC,
      Community::VISIBILITY_RESTRICTED,
    ];

    switch ($operation) {
      case 'view':
        $access = AccessResult::allowedIf(
          $is_owner
          || $is_member
          || $entity->isUserInvited($uid)
          || (in_array($entity->getVisibility(), $available_to_view) && $account->hasPermission('view opigno_community'))
          || $account->hasPermission('view any opigno_community')
        );
        break;

      case 'update':
      case 'delete':
        $access = AccessResult::allowedIf(
          $account->hasPermission($operation . ' opigno_community')
          || ($is_owner && $account->hasPermission($operation . ' own opigno_community'))
        );
        break;

      case 'invite_member':
        $member_invitation_permission = "invite to membership {$entity->getVisibility()} opigno_community";
        $access = AccessResult::allowedIf(
          $account->hasPermission('invite to any opigno_community')
          || $is_owner && $account->hasPermission('invite to own opigno_community')
          || ($is_member && $account->hasPermission($member_invitation_permission))
        );
        break;

      case 'delete_member':
      case 'pin_post':
        // Only owner can delete the members.
        $access = AccessResult::allowedIf($is_owner);
        break;

      case 'view_members':
        // Only user and other members can see the list of members.
        $access = AccessResult::allowedIf($is_member);
        break;

      case 'view_feed':
        // The feed can be viewed only for members (for private and restricted
        // communities) and for everyone if the community is public.
        $access = AccessResult::allowedIf($entity->isPublic() || $is_member);
        break;

      default:
        $access = AccessResult::neutral();
    }

    return $access;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $type = $context['entity_type_id'] ?? '';
    if ($type !== 'opigno_community') {
      return parent::checkCreateAccess($account, $context, $entity_bundle);
    }

    // Community features should be enabled.
    if (!$this->communitiesEnabled) {
      return AccessResult::forbidden('Community features disabled.');
    }

    return AccessResult::allowedIf(
      $account->hasPermission('create opigno_community')
      || (int) $account->id() === 1
      || $this->communityManager->isUserCommunityPrivileged('create', $account)
    );
  }

}
