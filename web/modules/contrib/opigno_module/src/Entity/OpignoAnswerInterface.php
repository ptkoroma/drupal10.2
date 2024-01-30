<?php

namespace Drupal\opigno_module\Entity;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Answer entities.
 *
 * @ingroup opigno_module
 */
interface OpignoAnswerInterface extends EntityChangedInterface, EntityOwnerInterface, RevisionableInterface {

  /**
   * Gets the Answer type.
   *
   * @return string
   *   The Answer type.
   */
  public function getType();

  /**
   * Gets the Answer creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Answer.
   */
  public function getCreatedTime();

  /**
   * Sets the Answer creation timestamp.
   *
   * @param int $timestamp
   *   The Answer creation timestamp.
   *
   * @return \Drupal\opigno_module\Entity\OpignoAnswerInterface
   *   The called Answer entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the score the user earned for the answer.
   *
   * @return int
   *   The answer score.
   */
  public function getScore(): int;

  /**
   * Gets the related User module status entity.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface|null
   *   The related user module status entity.
   */
  public function getUserModuleStatus(): ?UserModuleStatusInterface;

  /**
   * Gets the related activity entity.
   *
   * @return \Drupal\opigno_module\Entity\OpignoActivityInterface|null
   *   The related activity entity.
   */
  public function getActivity(): ?OpignoActivityInterface;

  /**
   * Gets the related Opigno module entity.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModuleInterface|null
   *   The related Opigno module entity.
   */
  public function getModule(): ?OpignoModuleInterface;

  /**
   * Gets the related learning path entity.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The related learning path entity.
   */
  public function getLearningPath(): ?GroupInterface;

}
