<?php

namespace Drupal\opigno_social\Entity;

use Drupal\user\UserInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for User Invitation entities defining.
 *
 * @ingroup opigno_social
 */
interface UserInvitationInterface extends ContentEntityInterface {

  /**
   * Get the invitation owner user ID.
   *
   * @return int
   *   The invitation owner user ID.
   */
  public function getOwnerId(): int;

  /**
   * Get invitation owner user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The invitation owner user.
   */
  public function getOwner(): ?UserInterface;

  /**
   * Set the invitation owner user ID.
   *
   * @param int $uid
   *   The invitation owner user ID.
   *
   * @return \Drupal\opigno_social\Entity\UserInvitationInterface
   *   Updated user invitation entity.
   */
  public function setOwnerId(int $uid): UserInvitationInterface;

  /**
   * Get the invitee user ID.
   *
   * @return int
   *   The invitee user ID.
   */
  public function getInviteeId(): int;

  /**
   * Get the invitee user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The invitee user.
   */
  public function getInvitee(): ?UserInterface;

  /**
   * Set the invitee user ID.
   *
   * @param int $uid
   *   The invitee user ID.
   *
   * @return \Drupal\opigno_social\Entity\UserInvitationInterface
   *   Updated user invitation entity.
   */
  public function setInviteeId(int $uid): UserInvitationInterface;

  /**
   * Get the invitation status.
   *
   * @return bool
   *   The invitation status.
   */
  public function isAccepted(): bool;

  /**
   * Set the invitation status.
   *
   * @param bool $status
   *   The invitation status to be set.
   *
   * @return \Drupal\opigno_social\Entity\UserInvitationInterface
   *   Updated user invitation entity.
   */
  public function setAccepted(bool $status = TRUE): UserInvitationInterface;

}
