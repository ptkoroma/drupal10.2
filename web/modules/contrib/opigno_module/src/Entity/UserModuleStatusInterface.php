<?php

namespace Drupal\opigno_module\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining User module status entities.
 *
 * @ingroup opigno_module
 */
interface UserModuleStatusInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the User module status name.
   *
   * @return string
   *   Name of the User module status.
   */
  public function getName();

  /**
   * Sets the User module status name.
   *
   * @param string $name
   *   The User module status name.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface
   *   The called User module status entity.
   */
  public function setName($name);

  /**
   * Gets the User module status creation timestamp.
   *
   * @return int
   *   Creation timestamp of the User module status.
   */
  public function getCreatedTime();

  /**
   * Sets the User module status creation timestamp.
   *
   * @param int $timestamp
   *   The User module status creation timestamp.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface
   *   The called User module status entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Get the User module status finished timestamp.
   *
   * @return int
   *   The User module status finished timestamp.
   */
  public function getFinishedTime(): int;

  /**
   * Sets the entity finished timestamp.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface
   *   The called entity.
   */
  public function setFinished($timestamp): UserModuleStatusInterface;

  /**
   * Checks if the user module status finished or not.
   *
   * @return bool
   *   Whether the user module status finished or not.
   */
  public function isFinished(): bool;

  /**
   * Get the time (in seconds) that the user spent to complete the module.
   *
   * @return int
   *   The time (in seconds) that the user spent to complete the module.
   */
  public function getCompletionTime(): int;

  /**
   * Returns the User module status published status indicator.
   *
   * Unpublished User module status are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the User module status is published.
   */
  public function isPublished(): bool;

  /**
   * Sets the published status of a User module status.
   *
   * @param bool $published
   *   TRUE to set this User module status to published,
   *   FALSE to set it to unpublished.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface
   *   The called User module status entity.
   */
  public function setPublished(bool $published): UserModuleStatusInterface;

  /**
   * Sets the module attempt score.
   *
   * @param string|int $value
   *   The module attempt score to be set.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface
   *   The called User module status entity.
   */
  public function setScore(string|int $value): UserModuleStatusInterface;

  /**
   * Gets the score the user earned for the module attempt.
   *
   * @return int
   *   The module attempt score.
   */
  public function getScore(): int;

  /**
   * Calculates the attempt score.
   *
   * @return int
   *   The calculated attempt score.
   */
  public function calculateScore(): int;

  /**
   * Calculates the possible attempt max score.
   *
   * @return int
   *   The possible attempt max score.
   */
  public function calculateMaxScore(): int;

  /**
   * Calculates module best score.
   *
   * @return int
   *   Score in percent.
   */
  public function calculateBestScore(): int;

  /**
   * Gets the related module entity.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModuleInterface|null
   *   The related module entity.
   */
  public function getModule(): ?OpignoModuleInterface;

  /**
   * Gets the related learning path.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The related learning path entity.
   */
  public function getLearningPath(): ?GroupInterface;

  /**
   * Gets the LP status entity the current module attempt belongs to.
   *
   * @param bool $load
   *   Should the result entity be loaded or not.
   *
   * @return \Drupal\opigno_learning_path\LPStatusInterface|int|null
   *   The related LP status entity if it should be loaded, its ID otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getLearningPathAttempt(bool $load = FALSE);

  /**
   * Sets the related LP status entity.
   *
   * @param int $lp_status
   *   The LP status entity ID.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface
   *   The called User module status entity.
   */
  public function setLearningPathAttempt(int $lp_status): UserModuleStatusInterface;

  /**
   * Checks if the module status relates to the current LP attempt.
   *
   * @param int $uid
   *   The user ID to check the attempt for.
   * @param int $gid
   *   The group ID to check the attempt for.
   *
   * @return bool
   *   If the module status relates to the current LP attempt or not.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function isInCurrentLpAttempt(int $uid, int $gid): bool;

}
