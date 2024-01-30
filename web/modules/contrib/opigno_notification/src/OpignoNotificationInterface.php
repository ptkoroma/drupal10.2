<?php

namespace Drupal\opigno_notification;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a opigno_notification entity.
 *
 * @ingroup opigno_notification
 */
interface OpignoNotificationInterface extends ContentEntityInterface {

  /**
   * Gets the notification created timestamp.
   *
   * @return int
   *   The created timestamp for the notification.
   */
  public function getCreatedTime(): int;

  /**
   * Gets the notification receiver.
   *
   * @return int|null
   *   The user id for the notification receiver, or NULL if not found.
   */
  public function getUser(): ?int;

  /**
   * Sets the notification receiver.
   *
   * @param int $value
   *   The notification receiver.
   *
   * @return \Drupal\opigno_notification\OpignoNotificationInterface
   *   The called notification entity.
   */
  public function setUser(int $value): OpignoNotificationInterface;

  /**
   * Gets the notification message.
   *
   * @return string
   *   The message of the notification.
   */
  public function getMessage(): string;

  /**
   * Sets the notification message.
   *
   * @param string $value
   *   The notification message.
   *
   * @return \Drupal\opigno_notification\OpignoNotificationInterface
   *   The called notification entity.
   */
  public function setMessage(string $value): OpignoNotificationInterface;

  /**
   * Gets the notification status.
   *
   * @return bool
   *   The status of the notification.
   */
  public function getHasRead(): bool;

  /**
   * Sets the notification status.
   *
   * @param bool $value
   *   The notification status.
   *
   * @return \Drupal\opigno_notification\OpignoNotificationInterface
   *   The called notification entity.
   */
  public function setHasRead(bool $value): OpignoNotificationInterface;

  /**
   * Get the notification url.
   *
   * @return string
   *   The notification url.
   */
  public function getUrl(): string;

  /**
   * Sets the notification URL.
   *
   * @param string $value
   *   The notification URL.
   *
   * @return \Drupal\opigno_notification\OpignoNotificationInterface
   *   The called notification entity.
   */
  public function setUrl(string $value): OpignoNotificationInterface;

}
