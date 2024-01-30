<?php

namespace Drupal\opigno_social_community\Entity;

use Drupal\opigno_social\Entity\OpignoPostInterface;

/**
 * Defines the Opigno community post entity interface.
 *
 * @package Drupal\opigno_social_community\Entity
 */
interface CommunityPostInterface extends OpignoPostInterface {

  /**
   * Gets the ID of the community entity the post is related to.
   *
   * @return int
   *   The ID of the community entity the post is related to.
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
   * @return \Drupal\opigno_social_community\Entity\CommunityPostInterface
   *   The called Community invitation entity.
   */
  public function setCommunity(CommunityInterface|int $community): CommunityPostInterface;

}
