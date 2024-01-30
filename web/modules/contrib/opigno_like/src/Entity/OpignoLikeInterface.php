<?php

namespace Drupal\opigno_like\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;

/**
 * Provides the interface to define the Opigno Like entity.
 *
 * @package Drupal\opigno_like\Entity
 */
interface OpignoLikeInterface extends ContentEntityInterface {

  /**
   * Get the like author ID.
   *
   * @return int
   *   The like author ID.
   */
  public function getUserId(): int;

  /**
   * Set the like author ID.
   *
   * @param int $uid
   *   The user ID to be set.
   *
   * @return \Drupal\opigno_like\Entity\OpignoLikeInterface
   *   The updated like entity.
   */
  public function setUserId(int $uid): OpignoLikeInterface;

  /**
   * Get the like author user.
   *
   * @return \Drupal\user\UserInterface|null
   *   The like author user.
   */
  public function getUser(): ?UserInterface;

  /**
   * Get the like creation timestamp.
   *
   * @return int
   *   The like creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Set the like creation timestamp.
   *
   * @param int $timestamp
   *   The like creation timestamp.
   *
   * @return \Drupal\opigno_like\Entity\OpignoLikeInterface
   *   The updated like entity.
   */
  public function setCreatedTime(int $timestamp): OpignoLikeInterface;

  /**
   * Get the liked entity ID.
   *
   * @return int
   *   The liked entity ID.
   */
  public function getLikedEntityId(): int;

  /**
   * Set the liked entity ID.
   *
   * @param int $eid
   *   The liked entity ID.
   *
   * @return \Drupal\opigno_like\Entity\OpignoLikeInterface
   *   The updated like entity.
   */
  public function setLikedEntityId(int $eid): OpignoLikeInterface;

  /**
   * Get the loaded target entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The loaded target entity.
   */
  public function getLikedEntity(): ?EntityInterface;

  /**
   * Get the liked entity type.
   *
   * @return string
   *   The liked entity type.
   */
  public function getLikedEntityType(): string;

  /**
   * Sets the liked entity type.
   *
   * @param string $type
   *   The liked entity type.
   *
   * @return \Drupal\opigno_like\Entity\OpignoLikeInterface
   *   The updated like entity.
   */
  public function setLikedEntityType(string $type): OpignoLikeInterface;

}
