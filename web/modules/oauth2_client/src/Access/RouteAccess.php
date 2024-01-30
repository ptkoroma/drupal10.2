<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginAccessInterface;
use Drupal\oauth2_client\PluginManager\Oauth2ClientPluginManager;

/**
 * Custom access checking on oauth2_client routes.
 *
 * @package Drupal\oauth2_client\Access
 */
class RouteAccess implements AccessInterface {

  /**
   * The OAuth2 Client plugin manager.
   */
  protected Oauth2ClientPluginManager $pluginManager;

  /**
   * RouteAccess constructor.
   *
   * @param \Drupal\oauth2_client\PluginManager\Oauth2ClientPluginManager $pluginManager
   *   Injected service.
   */
  public function __construct(Oauth2ClientPluginManager $pluginManager) {
    $this->pluginManager = $pluginManager;
  }

  /**
   * Checks access to designated routes.
   *
   * Any route uses this access checker delegates the access check to the
   * relevant plugin, if that plugin desires to customize access.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $routeMatch, AccountInterface $account): AccessResultInterface {
    if ($routeMatch->getRouteName() !== 'oauth2_client.code') {
      return AccessResult::neutral('This access check is for `oauth2_client.code` only.');
    }
    $pluginId = $routeMatch->getParameter('plugin');
    $plugin = $this->pluginManager->createInstance($pluginId);
    if ($plugin instanceof Oauth2ClientPluginAccessInterface) {
      return $plugin->codeRouteAccess($account);
    }
    return AccessResult::allowedIfHasPermission($account, 'administer oauth2 clients');
  }

}
