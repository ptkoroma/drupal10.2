<?php

namespace Drupal\opigno_learning_path;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Provides an interface defining a User Learning Path Attempt Status entity.
 *
 * @ingroup opigno_learning_path
 */
interface LPStatusInterface extends ContentEntityInterface {

  /**
   * Gets the training ID.
   *
   * @return int
   *   The training ID.
   */
  public function getTrainingId();

  /**
   * Sets the training ID.
   *
   * @param int $id
   *   The training ID.
   *
   * @return \Drupal\opigno_learning_path\LPStatusInterface
   *   The called entity.
   */
  public function setTrainingId($id);

  /**
   * Gets the training entity.
   *
   * @return \Drupal\group\Entity\GroupInterface
   *   The training entity.
   */
  public function getTraining();

  /**
   * Sets the training entity.
   *
   * @param int $training
   *   The training entity.
   *
   * @return \Drupal\opigno_learning_path\LPStatusInterface
   *   The called entity.
   */
  public function setTraining($training);

  /**
   * Gets the user ID.
   *
   * @return int
   *   The user ID.
   */
  public function getUserId();

  /**
   * Sets the user ID.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return \Drupal\opigno_learning_path\LPStatusInterface
   *   The called entity.
   */
  public function setUserId($uid);

  /**
   * Gets the user entity.
   *
   * @return \Drupal\user\Entity\User
   *   The user entity.
   */
  public function getUser();

  /**
   * Sets the user entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   *
   * @return \Drupal\opigno_learning_path\LPStatusInterface
   *   The called entity.
   */
  public function setUser(AccountInterface $account);

  /**
   * Gets the training status.
   *
   * @return string|null
   *   Training status.
   */
  public function getStatus();

  /**
   * Sets the training status.
   *
   * @param string $status
   *   Training status.
   *
   * @return \Drupal\opigno_learning_path\LatestActivityInterface
   *   The called entity.
   */
  public function setStatus($status);

  /**
   * Returns the user score.
   *
   * @return int|null
   *   The user score, or NULL in case the user score field
   *   has not been set on the entity.
   */
  public function getScore();

  /**
   * Sets the user score.
   *
   * @param int $value
   *   The user score.
   *
   * @return $this
   */
  public function setScore($value);

  /**
   * Gets the training finished timestamp.
   *
   * @return int
   *   The timestamp.
   */
  public function getFinished();

  /**
   * Sets the training finished timestamp.
   *
   * @param int $timestamp
   *   The timestamp.
   *
   * @return \Drupal\opigno_learning_path\LatestActivityInterface
   *   The called entity.
   */
  public function setFinished($timestamp);

  /**
   * Checks if the training finished.
   *
   * @return bool
   *   Boolean, true if the training was finished, false otherwise.
   */
  public function isFinished(): bool;

  /**
   * Sets th "finalized" field value.
   *
   * @param bool $is_finalized
   *   Is the LP status entity finalized or not.
   *
   * @return \Drupal\opigno_learning_path\LPStatusInterface
   *   The called LP status entity.
   */
  public function setFinalized(bool $is_finalized): LPStatusInterface;

  /**
   * Gets the training started timestamp.
   *
   * @return int
   *   The timestamp.
   */
  public function getStarted();

  /**
   * Sets the training started timestamp.
   *
   * @param int $timestamp
   *   The timestamp.
   *
   * @return \Drupal\opigno_learning_path\LatestActivityInterface
   *   The called entity.
   */
  public function setStarted($timestamp);

  /**
   * Checks if the training finished.
   *
   * @return \Drupal\opigno_learning_path\LatestActivityInterface
   *   Boolean, true if the training was started, false otherwise.
   */
  public function isStarted();

  /**
   * Returns LP attempt.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Learning path group.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   User account.
   * @param bool $load
   *   Load entity.
   * @param bool $finished
   *   Load finished attempts.
   *
   * @return \Drupal\opigno_learning_path\Entity\LPStatus|bool
   *   LPResult object or FALSE if not found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getCurrentLpAttempt(GroupInterface $group, AccountInterface $user, bool $load = TRUE, bool $finished = FALSE);

  /**
   * Checks if the LP attempt has related unfinished module attempts or not.
   *
   * @return bool
   *   If the current LP attempt has related unfinished module attempts or not.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasUnfinishedModuleAttempts(): bool;

}
