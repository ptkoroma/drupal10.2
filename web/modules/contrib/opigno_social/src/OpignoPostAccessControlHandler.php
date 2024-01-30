<?php

namespace Drupal\opigno_social;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\EntityAccessControlHandler;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social\Services\UserConnectionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access control handler for the Opigno post entities.
 *
 * @package Drupal\opigno_social
 */
class OpignoPostAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The list of user connections.
   *
   * @var array
   */
  protected $userNetwork;

  /**
   * {@inheritdoc}
   */
  public function __construct(UserConnectionManager $connections_manager, ...$default) {
    parent::__construct(...$default);
    $this->userNetwork = $connections_manager->getUserNetwork();
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('opigno_user_connection.manager'),
      $entity_type
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!$entity instanceof OpignoPostInterface) {
      return AccessResult::neutral();
    }

    $author_id = $entity->getAuthorId();
    $uid = (int) $account->id();

    switch ($operation) {
      case 'view':
      case 'view_label':
        // User should be able to see/comment only posts from people who are in
        // their network OR their own posts.
        return AccessResult::allowedIf(in_array($author_id, $this->userNetwork) || $author_id === $uid);

      case 'edit':
        // There should be no possibility to edit the post/comment.
        return AccessResult::forbidden();

      case 'delete':
        return AccessResult::allowedIf($author_id === $uid || $account->hasPermission('remove any post entities'));

      default:
        return AccessResult::neutral();
    }
  }

}
