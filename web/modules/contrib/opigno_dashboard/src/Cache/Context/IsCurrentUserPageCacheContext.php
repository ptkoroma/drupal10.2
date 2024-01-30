<?php

namespace Drupal\opigno_dashboard\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a cache context for whether the URL is the current user page.
 *
 * Cache context ID: 'url.path.is_current_user_page'.
 */
class IsCurrentUserPageCacheContext implements CacheContextInterface {

  /**
   * If the current page is a user page.
   *
   * @var bool
   */
  protected $isUserPage;

  /**
   * The current user ID.
   *
   * @var int
   */
  protected $uid;

  /**
   * IsCurrentUserPageCacheContext constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The rote match service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   */
  public function __construct(RouteMatchInterface $route_match, AccountInterface $account) {
    $this->uid = (int) $account->id();
    $this->isUserPage = $route_match->getRouteName() === 'entity.user.canonical' && (int) $route_match->getRawParameter('user') === $this->uid;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Is current user page');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return 'is_current_user_page_.' . (int) $this->isUserPage;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    $metadata = new CacheableMetadata();
    $metadata->addCacheTags(['user:' . $this->uid]);
    return $metadata;
  }

}
