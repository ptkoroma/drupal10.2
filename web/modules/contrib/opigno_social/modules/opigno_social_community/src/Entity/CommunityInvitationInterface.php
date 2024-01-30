<?php

namespace Drupal\opigno_social_community\Entity;

use Drupal\opigno_social\Entity\UserInvitationInterface;

/**
 * Defines the Opigno community invitation entity interface.
 *
 * @package Drupal\opigno_social_community\Entity
 */
interface CommunityInvitationInterface extends UserInvitationInterface {

  /**
   * Gets the ID of the community entity the user has been invited to.
   *
   * @return int
   *   The ID of the community entity the user has been invited to.
   */
  public function getCommunityId(): int;

  /**
   * Gets the related community entity.
   *
   * @return \Drupal\opigno_social_community\Entity\CommunityInterface|null
   *   The related community entity.
   */
  public function getCommunity(): ?CommunityInterface;

  /**
   * Sets the related community entity.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface|int $community
   *   The ID of the related community entity.
   *
   * @return \Drupal\opigno_social_community\Entity\CommunityInvitationInterface
   *   The called Community invitation entity.
   */
  public function setCommunity(CommunityInterface|int $community): CommunityInvitationInterface;

  /**
   * Defines if the invitation is a join request.
   *
   * @return bool
   *   Whether the invitation is a join request or not.
   */
  public function isJoinRequest(): bool;

}
