<?php

namespace Drupal\opigno_social;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserAccessControlHandler as UserAccessControlHandlerBase;

/**
 * Override the default User access control handler.
 *
 * @package Drupal\opigno_social
 */
class UserAccessControlHandler extends UserAccessControlHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    // The "email" field is available only for users who have the administer
    // permission, but it should be possible to use it in the view filter.
    if ($field_definition->getName() === 'mail' && $operation === 'view') {
      return AccessResult::allowedIf($account->isAuthenticated());
    }

    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

}
