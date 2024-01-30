<?php

namespace Drupal\opigno_mobile_app;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageThread;

/**
 * Common helper methods for build data for private messages.
 */
class PrivateMessagesHandler {

  /**
   * Get unread private messages list for current user.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThread $thread
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *
   * @return array $unread_messages
   *   Array unread private messages.
   */
  public static function getUnreadMessagesForThread(PrivateMessageThread $thread, AccountProxyInterface $account) {
    $last_access_time = $thread->getLastAccessTimestamp($account);
    $messages = $thread->getMessages();
    // Filter unread messages.
    $unread_messages = array_filter($messages, function ($message) use ($last_access_time, $account) {
      /* @var \Drupal\private_message\Entity\PrivateMessage $message */
      $is_new = $message->getCreatedTime() > $last_access_time;
      $is_not_owner = $message->getOwnerId() != $account->id();
      return $is_new && $is_not_owner;
    });
    return $unread_messages ?: [];
  }

  /**
   * Get Private Message Thread from Private Message entity.
   *
   * @param \Drupal\private_message\Entity\PrivateMessage $message
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThread $thread
   *   Private Message Thread object.
   */
  public static function getThreadFromMessage(PrivateMessage $message) {
    $db_connection = \Drupal::service('database');
    $query = $db_connection->select('private_message_thread__private_messages', 'pm');
    $query->condition('pm.private_messages_target_id', $message->id());
    $query->fields('pm', ['entity_id']);
    $result = $query->execute()->fetchAssoc();
    // Load PrivateMessageThread.
    $thread_id = reset($result);
    $thread = PrivateMessageThread::load($thread_id);

    return $thread;
  }

}
