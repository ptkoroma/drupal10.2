<?php

namespace Drupal\opigno_social_community\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social_community\Entity\Community;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Route;

/**
 * Defines the access check based on the community membership.
 *
 * @package Drupal\opigno_social_community\Access
 */
class CommunityMemberAccessCheck implements AccessInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The community entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $communityStorage;

  /**
   * The route access service.
   *
   * @var \Drupal\Core\Routing\AccessAwareRouterInterface
   */
  protected AccessAwareRouterInterface $router;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Whether the communities feature enabled or not.
   *
   * @var bool
   */
  protected bool $communitiesEnabled;

  /**
   * CommunityMemberAccessCheck constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Routing\AccessAwareRouterInterface $router
   *   The route access service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
    AccessAwareRouterInterface $router,
    RequestStack $request_stack,
    ConfigFactoryInterface $config
  ) {
    $this->routeMatch = $route_match;
    $this->communityStorage = $entity_type_manager->getStorage('opigno_community');
    $this->router = $router;
    $this->request = $request_stack->getCurrentRequest();
    $this->communitiesEnabled = (bool) $config->get(Community::ADMIN_CONFIG_NAME)->get('enable_communities') ?? FALSE;
  }

  /**
   * Allows access only for the community manager.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check the access for.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check the access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account): AccessResultInterface {
    return $this->checkAccess($account);
  }

  /**
   * The access check based on the community membership.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check the access for.
   * @param bool $as_bool
   *   If TRUE, the result will be returned as a boolean, otherwise - as an
   *   AccessResult object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|bool
   *   The access result.
   */
  public function checkAccess(AccountInterface $account, bool $as_bool = FALSE): AccessResultInterface|bool {
    $community = $this->getCommunityFromRequest();
    $result = AccessResult::allowedIf(
      $this->communitiesEnabled
      && $community instanceof CommunityInterface
      && ($community->isMember($account) || $account->hasPermission('view any opigno_community'))
    );

    return $as_bool ? $result->isAllowed() : $result->addCacheTags(['config:' . Community::ADMIN_CONFIG_NAME]);
  }

  /**
   * Gets the community entity from the route parameter or request.
   *
   * @return \Drupal\opigno_social_community\Entity\CommunityInterface|null
   *   The community entity from the request.
   */
  protected function getCommunityFromRequest(): ?CommunityInterface {
    // Try to get the community from the route.
    $cid = $this->routeMatch->getRawParameter('opigno_community');
    if ($cid) {
      $community = $this->communityStorage->load($cid);
      return $community instanceof CommunityInterface ? $community : NULL;
    }

    // For ajax requests try to get the community from the referer request.
    $community = NULL;
    if ($this->request->isXmlHttpRequest()) {
      $referer = $this->request->server->get('HTTP_REFERER');
      try {
        $route_info = $this->router->match($referer);
        $community = $route_info['opigno_community'] ?? NULL;
      }
      catch (AccessDeniedHttpException $e) {
        watchdog_exception('opigno_social_community_exception', $e);
      }
    }

    return $community instanceof CommunityInterface ? $community : NULL;
  }

}
