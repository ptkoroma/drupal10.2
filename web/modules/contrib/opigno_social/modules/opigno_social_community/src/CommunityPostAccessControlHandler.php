<?php

namespace Drupal\opigno_social_community;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social\OpignoPostAccessControlHandler;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Entity\CommunityPostInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines access control handler for Opigno community post entity.
 *
 * @package Drupal\opigno_social_community
 */
class CommunityPostAccessControlHandler extends OpignoPostAccessControlHandler {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(RouteMatchInterface $route_match, ...$default) {
    parent::__construct(...$default);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('current_route_match'),
      $container->get('opigno_user_connection.manager'),
      $entity_type
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!$entity instanceof CommunityPostInterface || $operation === 'edit') {
      return parent::checkAccess($entity, $operation, $account);
    }

    $uid = (int) $account->id();
    $community = $entity->getCommunity();
    if (!$community instanceof CommunityInterface) {
      return AccessResult::forbidden();
    }

    // Admin and community owner can perform any operations, except for editing.
    if ($uid === 1 || $community->getOwnerId() === $uid) {
      return AccessResult::allowed();
    }

    $is_community_member = $community->isMember($uid);
    $is_post_author = $entity->getAuthorId() === $uid;

    switch ($operation) {
      // Everyone can see posts if the community is public; for private
      // communities posts are visible only for the community members.
      case 'view':
      case 'view_label':
        $result = AccessResult::allowedIf(
          $community->isPublic()
          || $is_community_member
          || $is_post_author
        );
        break;

      case 'delete':
        $result = AccessResult::allowedIf(
          $is_post_author
          || $account->hasPermission('remove any opigno_community_post entity')
        );
        break;

      default:
        $result = parent::checkAccess($entity, $operation, $account);
    }

    return $result;
  }

}
