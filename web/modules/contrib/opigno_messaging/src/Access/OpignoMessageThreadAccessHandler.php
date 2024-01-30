<?php

namespace Drupal\opigno_messaging\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\Access\PrivateMessageThreadAccessControlHandler;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Overrides default access control handler for private message thread entities.
 *
 * @package Drupal\opigno_messaging\Access
 */
class OpignoMessageThreadAccessHandler extends PrivateMessageThreadAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!$entity instanceof PrivateMessageThreadInterface) {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Override an access for the thread viewing. By default the user can't
    // access a thread if there are no messages.
    if ($operation === 'view') {
      return AccessResult::allowedIf($account->hasPermission('use private messaging system')
        && $entity->isMember($account->id())
      );
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
