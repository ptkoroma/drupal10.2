<?php

namespace Drupal\opigno_notification\Services;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\opigno_notification\Entity\OpignoNotification;
use Drupal\opigno_notification\OpignoNotificationInterface;
use Drupal\user\UserInterface;

/**
 * Opigno notification manager service.
 *
 * @package Drupal\opigno_notification\Services
 */
class OpignoNotificationManager {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $user = NULL;

  /**
   * OpignoNotificationManager constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(AccountInterface $account, EntityTypeManagerInterface $entity_type_manager) {
    $uid = (int) $account->id();

    try {
      $this->user = $entity_type_manager->getStorage('user')->load($uid);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_notification_exception', $e);
    }
  }

  /**
   * Get user unread notifications (ILT + LM + standard ones).
   *
   * @return array
   *   The list of user unread notifications (ILT + LM + standard ones).
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUserHeaderNotifications(): array {
    if (!$this->user instanceof UserInterface) {
      return [];
    }

    // Get upcoming ILT + Live meetings + standard notifications.
    $notifications = _opigno_ilt_upcoming($this->user);
    $notifications = array_merge($notifications, _opigno_moxtra_upcoming_live_meetings($this->user));

    return array_merge($notifications, OpignoNotification::getUnreadNotifications(NULL, 3));
  }

  /**
   * Prepare the render array to display the list of user notifications.
   *
   * @param array $entities
   *   The list of notification entities to be rendered. If empty, notifications
   *   will be gathered automatically.
   *
   * @return array
   *   Render array to display the list of user notifications.
   */
  public function renderUserHeaderNotifications(array $entities = []): array {
    $notifications = [];
    $entities = $entities ?: $this->getUserHeaderNotifications();
    if (!$entities) {
      return $notifications;
    }

    // Count standard notifications.
    $notifications_count = 0;
    $options = [
      'attributes' => [
        'class' => ['notification-item-text'],
      ],
    ];

    foreach ($entities as $entity) {
      if (!$entity instanceof EntityInterface) {
        continue;
      }

      $entity_type = $entity->getEntityTypeId();
      if ($entity instanceof OpignoNotificationInterface) {
        $url = Url::fromUserInput($entity->getUrl(), $options);
        $title = $entity->getMessage();
        $notifications_count++;
      }
      else {
        $title = $entity->getTitle();
        $url = Url::fromRoute("entity.$entity_type.canonical", [$entity_type => (int) $entity->id()], $options);
      }

      $notifications[] = Link::fromTextAndUrl($title, $url);
    }

    return [
      '#theme' => 'opigno_notifications_header_dropdown',
      '#notifications' => $notifications,
      '#notifications_count' => $notifications_count,
    ];
  }

}
