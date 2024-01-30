<?php

namespace Drupal\opigno_module\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Module entities.
 *
 * @ingroup opigno_module
 */
interface OpignoModuleInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Module name.
   *
   * @return string
   *   Name of the Module.
   */
  public function getName();

  /**
   * Sets the Module name.
   *
   * @param string $name
   *   The Module name.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModuleInterface
   *   The called Module entity.
   */
  public function setName($name);

  /**
   * Gets the Module creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Module.
   */
  public function getCreatedTime();

  /**
   * Sets the Module creation timestamp.
   *
   * @param int $timestamp
   *   The Module creation timestamp.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModuleInterface
   *   The called Module entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Module published status indicator.
   *
   * Unpublished Module are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Module is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Module.
   *
   * @param bool $published
   *   TRUE to set this Module to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModuleInterface
   *   The called Module entity.
   */
  public function setPublished($published);

  /**
   * Get the max activities score for the module.
   *
   * @return int
   *   The max activities score for the module.
   */
  public function getMaxActivitiesScore(): int;

  /**
   * Gets the badge image URL if image is set.
   *
   * @return string|null
   *   The badge image URL if image is set, NULL otherwise.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function getBadgeUrl(): ?string;

  /**
   * Get activities related to specific module.
   *
   * @param bool $full
   *   Should activities be loaded or not.
   *
   * @return array|null
   *   The list of activities related to the given module.
   */
  public function getModuleActivities(bool $full = FALSE): ?array;

  /**
   * Gets the active module attempt entity if user didn't finish module.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   User entity object.
   * @param string|null $activity_link_type
   *   The activity link type.
   * @param string|int|null $group_id
   *   The module-related group ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The active module attempt entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function getModuleActiveAttempt(
    AccountInterface $user,
    ?string $activity_link_type = NULL,
    $group_id = NULL
  ): ?EntityInterface;

  /**
   * Gets the latest user module attempt.
   *
   * @param int $uid
   *   The user ID to get the module attempt for.
   * @param int|string|null $gid
   *   The LP group ID to get the attempt for.
   * @param bool $load
   *   Should the module attempt be loaded or not.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface|int|null
   *   The latest user module attempt.
   */
  public function getLastModuleAttempt(int $uid, $gid, bool $load = FALSE);

  /**
   * Get loaded module statuses for specified user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to get the module attempts for.
   * @param int|string|null $range
   *   The attempts range.
   * @param int|string|null $latest_cert_date
   *   The latest certificate date.
   * @param bool $finished
   *   If TRUE, only finished attempts will be returned.
   * @param int|string|null $group_id
   *   The group ID the module relates to.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]|\Drupal\opigno_module\Entity\UserModuleStatus[][]|mixed
   *   The list of loaded attempts.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getModuleAttempts(
    AccountInterface $user,
    $range = NULL,
    $latest_cert_date = NULL,
    bool $finished = FALSE,
    $group_id = NULL
  );

  /**
   * Gets the active training attempt if user didn't finish training.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   User entity object.
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The active LP training attempt.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTrainingActiveAttempt(AccountInterface $user, Group $group): ?EntityInterface;

  /**
   * Gets the last training attempt.
   *
   * @param int $uid
   *   The user ID to get the LP attempt for.
   * @param int|string|null $gid
   *   The LP ID to get the attempt for.
   * @param bool $load
   *   Should the LP status entity be loaded or not.
   *
   * @return \Drupal\opigno_learning_path\LPStatusInterface|int|null
   *   The loaded LP attempt entity or its ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public static function getLastTrainingAttempt(int $uid, $gid, bool $load = FALSE);

  /**
   * Gets the number of allowed attempts for the module.
   *
   * @return int
   *   The number of allowed attempts for the module.
   *   If 0, the attempts number is unlimited for the module.
   */
  public function getAllowedAttemptsNumber(): int;

  /**
   * Gets the number of already taken attempts.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account to get the number of attempts for. The current user will
   *   be taken by default.
   * @param int|null $range
   *   The range, see getModuleAttempts() for more info.
   * @param int|null $latest_cert_date
   *   The latest certification date, see getModuleAttempts() for more info.
   * @param bool $finished
   *   If TRUE only finished attempts will be returned, see getModuleAttempts().
   * @param int|null $gid
   *   The related group ID, see getModuleAttempts().
   *
   * @return int
   *   The number of taken attempts.
   *
   * @throws \Exception
   */
  public function getTakenAttemptsNumber(
    ?AccountInterface $account = NULL,
    ?int $range = NULL,
    ?int $latest_cert_date = NULL,
    bool $finished = FALSE,
    ?int $gid = NULL
  ): int;

  /**
   * Checks if the user can create new attempt or not.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account to check the ability to create a new attempt.
   *   The current user will be taken by default.
   * @param int|null $range
   *   The range, see getModuleAttempts() for more info.
   * @param int|null $latest_cert_date
   *   The latest certification date, see getModuleAttempts() for more info.
   * @param bool $finished
   *   If TRUE only finished attempts will be returned, see getModuleAttempts().
   * @param int|null $gid
   *   The related group ID, see getModuleAttempts().
   *
   * @return bool
   *   Whether the user can create new attempt or not.
   *
   * @throws \Exception
   */
  public function canCreateNewAttempt(
    ?AccountInterface $account = NULL,
    ?int $range = NULL,
    ?int $latest_cert_date = NULL,
    bool $finished = FALSE,
    ?int $gid = NULL
  ): bool;

}
