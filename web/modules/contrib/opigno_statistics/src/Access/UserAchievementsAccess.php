<?php

namespace Drupal\opigno_statistics\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_learning_path\Services\UserAccessManager;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Routing\Route;

/**
 * Check the access to the achievements page.
 *
 * @package Drupal\opigno_statistics\Access
 */
class UserAchievementsAccess implements ContainerAwareInterface, AccessInterface {

  use DependencySerializationTrait;
  use ContainerAwareTrait;

  /**
   * The Opigno user access manager service.
   *
   * @var \Drupal\opigno_learning_path\Services\UserAccessManager
   */
  protected $accessManager;

  /**
   * Checks the access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check the access to.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in user's account.
   * @param \Drupal\user\UserInterface $user
   *   The user to check the access to.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, UserInterface $user) {
    $access = FALSE;
    $access_manager = $this->getAccessManager();
    if ($access_manager instanceof UserAccessManager) {
      $access = $access_manager->canAccessUserStatistics($user);
    }
    return AccessResult::allowedIf($access)->addCacheContexts([
      'user.permissions',
      'user.group_permissions',
    ]);
  }

  /**
   * Access manager getter.
   */
  protected function getAccessManager() {
    if (!$this->accessManager) {
      $this->accessManager = $this->container->get('opigno_learning_path.user_access_manager');
    }
    return $this->accessManager;
  }

}
