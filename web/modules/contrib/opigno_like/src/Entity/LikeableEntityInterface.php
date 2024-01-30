<?php

namespace Drupal\opigno_like\Entity;

use Drupal\user\UserInterface;

/**
 * Defines the base interface for entities that can be liked.
 *
 * @package Drupal\opigno_like\Entity
 */
interface LikeableEntityInterface {

  /**
   * Sends the notification to say that the entity has been liked.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user who liked the entity.
   */
  public function sendLikeNotification(UserInterface $user): void;

}
