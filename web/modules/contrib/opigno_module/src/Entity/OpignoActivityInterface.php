<?php

namespace Drupal\opigno_module\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Activity entities.
 *
 * @ingroup opigno_module
 */
interface OpignoActivityInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Activity type.
   *
   * @return string
   *   The Activity type.
   */
  public function getType();

  /**
   * Gets the Activity name.
   *
   * @return string
   *   Name of the Activity.
   */
  public function getName();

  /**
   * Gets the current revision vid.
   *
   * @return string
   *   Vid.
   */
  public function getCurrentRevision();

  /**
   * Sets the Activity name.
   *
   * @param string $name
   *   The Activity name.
   *
   * @return \Drupal\opigno_module\Entity\OpignoActivityInterface
   *   The called Activity entity.
   */
  public function setName($name);

  /**
   * Gets the Activity creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Activity.
   */
  public function getCreatedTime();

  /**
   * Sets the Activity creation timestamp.
   *
   * @param int $timestamp
   *   The Activity creation timestamp.
   *
   * @return \Drupal\opigno_module\Entity\OpignoActivityInterface
   *   The called Activity entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Activity published status indicator.
   *
   * Unpublished Activity are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Activity is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Activity.
   *
   * @param bool $published
   *   TRUE to set this Activity to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\opigno_module\Entity\OpignoActivityInterface
   *   The called Activity entity.
   */
  public function setPublished($published);

  /**
   * Gets a list of Answer revision IDs for a specific Answer.
   *
   * @return int[]
   *   Activity revision IDs (in ascending order).
   */
  public function revisionIds(): array;

  /**
   * Get list of activity modules.
   *
   * @return array
   *   The list of modules activity is assigned to.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getModules(): array;

  /**
   * Get if evaluation Method Manual.
   *
   * @return bool
   *   TRUE if the evaluation method is "manual", FALSE otherwise.
   */
  public function evaluationMethodManual(): bool;

}
