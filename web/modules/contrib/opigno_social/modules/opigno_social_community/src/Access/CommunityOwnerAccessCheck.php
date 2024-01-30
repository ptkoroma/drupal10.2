<?php

namespace Drupal\opigno_social_community\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social_community\Entity\Community;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines the access check based on the community ownership.
 *
 * @package Drupal\opigno_social_community\Access
 */
class CommunityOwnerAccessCheck extends CommunityMemberAccessCheck {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, AccountInterface $account): AccessResultInterface {
    return $this->checkAccess($account);
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(AccountInterface $account, bool $as_bool = FALSE): AccessResultInterface|bool {
    $community = $this->getCommunityFromRequest();
    $result = AccessResult::allowedIf(
      $this->communitiesEnabled
      && $community instanceof CommunityInterface
      && ($community->getOwnerId() === (int) $account->id() || $account->hasPermission('view any opigno_community'))
    );

    return $as_bool ? $result->isAllowed() : $result->addCacheTags(['config:' . Community::ADMIN_CONFIG_NAME]);
  }

}
