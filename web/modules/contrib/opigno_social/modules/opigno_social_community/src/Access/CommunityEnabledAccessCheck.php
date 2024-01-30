<?php

namespace Drupal\opigno_social_community\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\opigno_social_community\Entity\Community;

/**
 * Access check based on the communities feature enabling checkbox.
 *
 * @package Drupal\opigno_social_community\Access
 */
class CommunityEnabledAccessCheck implements AccessInterface {

  /**
   * Whether the communities feature enabled or not.
   *
   * @var bool
   */
  protected bool $isEnabled;

  /**
   * CommunityFeaturesAccessCheck constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->isEnabled = (bool) $config_factory->get(Community::ADMIN_CONFIG_NAME)->get('enable_communities') ?? FALSE;
  }

  /**
   * Checks the access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(): AccessResultInterface {
    return AccessResult::allowedIf($this->isEnabled)->addCacheTags(['config:' . Community::ADMIN_CONFIG_NAME]);
  }

}
