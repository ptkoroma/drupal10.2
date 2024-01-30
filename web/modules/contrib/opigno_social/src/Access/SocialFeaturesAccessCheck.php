<?php

namespace Drupal\opigno_social\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Access check based on the selected social settings.
 *
 * @package Drupal\opigno_social\Access
 */
class SocialFeaturesAccessCheck implements AccessInterface {

  /**
   * Whether the social features enabled or not.
   *
   * @var bool
   */
  protected $socialsEnabled;

  /**
   * SocialFeaturesAccessCheck constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->socialsEnabled = (bool) $config_factory->get('opigno_class.socialsettings')->get('enable_social_features') ?? FALSE;
  }

  /**
   * Checks the access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check the access to.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in user's account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account) {
    return AccessResult::allowedIf($this->socialsEnabled)->addCacheTags(['config:opigno_class.socialsettings']);
  }

}
