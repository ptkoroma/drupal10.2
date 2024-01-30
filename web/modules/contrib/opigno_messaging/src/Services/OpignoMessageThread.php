<?php

namespace Drupal\opigno_messaging\Services;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\opigno_statistics\Services\UserStatisticsManager;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * The private messages manager service.
 *
 * @package Drupal\opigno_messaging
 */
class OpignoMessageThread {

  use StringTranslationTrait;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The private message thread entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $threadStorage = NULL;

  /**
   * The mail service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailService;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * OpignoMessageThread constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   */
  public function __construct(
    AccountInterface $account,
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager,
    MailManagerInterface $mail_manager,
    ConfigFactoryInterface $config_factory,
    UserDataInterface $user_data,
    Connection $database
  ) {
    $this->account = $account;
    $this->dateFormatter = $date_formatter;
    $this->mailService = $mail_manager;
    $this->config = $config_factory;
    $this->userData = $user_data;
    $this->database = $database;

    try {
      $this->threadStorage = $entity_type_manager->getStorage('private_message_thread');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_messaging_exception', $e);
    }
  }

  /**
   * Gets message tread IDs of current user.
   *
   * @param int $uid
   *   User ID. Leave empty to get the data for the current user.
   *
   * @return array
   *   The list of user thread IDs.
   */
  public function getUserThreads(int $uid = 0): array {
    if (!$uid) {
      $uid = (int) $this->account->id();
    }

    $query = $this->database->select('private_message_thread__members', 'pmm');
    $query->join('pm_thread_history', 'pth', 'pmm.entity_id = pth.thread_id');
    $query->join('private_message_threads', 'pmt', 'pmt.id = pth.thread_id');
    $query->fields('pmm', ['entity_id'])
      ->condition('pmm.members_target_id', $uid);

    // Get the list of threads that were not deleted by the user.
    $not_deleted = $query->andConditionGroup()
      ->condition('pth.delete_timestamp', 0)
      ->condition('pth.uid', $uid);
    // Threads that were updated after they were deleted by the user.
    $updated_later = $query->andConditionGroup()
      ->condition('pth.uid', $uid)
      ->where('pmt.updated > pth.delete_timestamp');

    // Threads the user sent messages to.
    $user_threads = $this->database->select('pm_thread_history', 'h')
      ->fields('h', ['thread_id'])
      ->condition('h.uid', $uid)
      ->execute()
      ->fetchCol();

    // Get the list of threads the user is member of, but hasn't sent any
    // message to yet.
    $user_threads = $user_threads ?: [0];
    $msg_not_exists = $query->andConditionGroup()
      ->condition('pmm.members_target_id', $uid)
      ->condition('pmm.entity_id', $user_threads, 'NOT IN');

    $or_condition = $query->orConditionGroup()
      ->condition($not_deleted)
      ->condition($updated_later)
      ->condition($msg_not_exists);

    $query->condition($or_condition);
    $result = $query
      ->orderBy('pmt.updated', 'DESC')
      ->execute()
      ->fetchCol();

    return is_array($result) ? $result : [];
  }

  /**
   * Get the messages thread data: image, title, date, text.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   The message thread to get the data for.
   *
   * @return array
   *   The array that contains the thread display data.
   */
  public function getThreadDisplayData(PrivateMessageThreadInterface $thread): array {
    $members = $thread->getMembers();
    $count = count($members);
    // Get the last thread message.
    $messages = $thread->getMessages();
    $message = end($messages);
    $data = [];

    // Get the message date and shortened text.
    if ($message instanceof PrivateMessage) {
      $data['date'] = $this->getMessageFormattedDate($message);
      $length = 20;
      $text = strip_tags($message->getMessage());
      // Remove the unneeded characters from the text.
      $text = trim(html_entity_decode($text, ENT_NOQUOTES), " \n\r\t\v\0\xC2\xA0");
      $text = mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '...' : $text;
      $data['text'] = Markup::create($text);
    }

    // The case when the user was deleted.
    if ($count < 2) {
      return $data + [
        'title' => $this->t('Deleted user'),
        'image' => UserStatisticsManager::getDefaultUserPicture(),
      ];
    }

    // Get the user name and image for 1-to-1 message thread.
    if ($count === 2) {
      foreach ($members as $member) {
        if ($member instanceof UserInterface && (int) $member->id() !== (int) $this->account->id()) {
          return $data + [
            'title' => $member->getDisplayName(),
            'image' => UserStatisticsManager::getUserPicture($member),
          ];
        }
      }
    }

    // Get the data for the group message thread.
    $title = $thread->get('field_pm_subject')->getString() ?: $this->t('Discussion');
    if (!$thread->get('field_image')->isEmpty()) {
      $image = $thread->get('field_image')->view([
        'label' => 'hidden',
        'type' => 'image',
        'settings' => [
          'image_style' => 'user_compact_image',
        ],
      ]);
    }
    else {
      $path = \Drupal::service('extension.list.theme')->getPath('aristotle') . '/src/images/content/group_profile.svg';
      $image = [
        '#theme' => 'image',
        '#uri' => file_exists($path) ? \Drupal::service('file_url_generator')->transformRelative(base_path() . $path) : '',
        '#alt' => $title,
        '#title' => $title,
      ];
    }

    $message_author = $message->getOwner();
    if ($message_author instanceof UserInterface) {
      $data['text'] = $message_author->getDisplayName();
    }

    return $data + [
      'title' => $title,
      'image' => $image,
      'unread_count' => $this->getThreadUnreadMessagesCount($thread),
    ];
  }

  /**
   * Get the amount of unread messages in the given thread.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   The thread to get unread messages amount for.
   *
   * @return int
   *   The number of unread messages in the given thread.
   */
  public function getThreadUnreadMessagesCount(PrivateMessageThreadInterface $thread): int {
    $last_access = $thread->getLastAccessTimestamp($this->account);
    $query = $this->database->select('private_messages', 'pm');
    $query->join('private_message_thread__private_messages', 't', 't.private_messages_target_id = pm.id');
    $query->fields('pm', ['id'])
      ->condition('t.entity_id', (int) $thread->id())
      ->condition('pm.owner', (int) $this->account->id(), '!=')
      ->condition('pm.created', $last_access, '>');

    $count = $query->countQuery()
      ->execute()
      ->fetchField();

    return (int) $count;
  }

  /**
   * Get the formatted date of the message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessage $message
   *   The private message entity to get the date for.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The message formatted date.
   */
  public function getMessageFormattedDate(PrivateMessage $message) {
    $timestamp = $message->getCreatedTime();
    switch ($timestamp) {
      // If the message was created today, the format should be: "today - h:m".
      case $timestamp >= strtotime('today'):
        $time = $this->dateFormatter->format($timestamp, 'hour_minute');
        $created = $this->t('today - @time', ['@time' => $time]);
        break;

      // If the message was sent yesterday, the format should be:
      // "yesterday - h:m".
      case $timestamp >= strtotime('yesterday'):
        $time = $this->dateFormatter->format($timestamp, 'hour_minute');
        $created = $this->t('yesterday - @time', ['@time' => $time]);
        break;

      // For older messages display the full date with the time.
      default:
        $created = $this->dateFormatter->format($timestamp, 'date_short_with_time');
    }

    return $created;
  }

  /**
   * Get the private message thread for the given members.
   *
   * @param array $uids
   *   The list of member IDs.
   * @param bool $create
   *   Whether the new thread should be created in case if it doesn't exist for
   *   the given members or not.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThreadInterface|null
   *   The private message thread for the given members.
   */
  public function getThreadForMembers(array $uids, bool $create = TRUE): ?PrivateMessageThreadInterface {
    if (!$this->threadStorage instanceof EntityStorageInterface) {
      return NULL;
    }

    // Check if the thread with the given members already exists.
    $threads = $this->threadStorage->loadByProperties(['members' => $uids]);
    if ($threads) {
      $count = count($uids);
      foreach ($threads as $thread) {
        if (!$thread instanceof PrivateMessageThreadInterface) {
          continue;
        }

        $members = $thread->getMembersId();
        if ($count === count($members) && !array_diff($uids, $members)) {
          return $thread;
        }
      }
    }

    if (!$create) {
      return NULL;
    }

    // Create a new thread with the given members.
    $thread = $this->threadStorage->create([
      'members' => $uids,
    ]);

    try {
      $thread->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_messaging_exception', $e);
      $thread = NULL;
    }

    return $thread instanceof PrivateMessageThreadInterface ? $thread : NULL;
  }

  /**
   * Send the email to all thread members when the new message is created.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   The PM thread.
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $message
   *   The message.
   */
  public function sendEmailToThreadMembers(PrivateMessageThreadInterface $thread, PrivateMessageInterface $message): void {
    $members = $thread->getMembers();
    if (!$members) {
      return;
    }

    $pm_config = $this->config->get('private_message.settings');
    $message_notification_mail_map = $this->config->getEditable('private_message.mail')->get('message_notification');
    $site_name = $this->config->get('system.site')->get('name') ?? 'Opigno';
    $params = [
      'private_message' => $message,
      'private_message_thread' => $thread,
      'subject' => str_replace('[site:name]', $site_name, $message_notification_mail_map['subject']),
    ];

    $thread_url = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $thread->id()])->setAbsolute();
    $thread_link = Link::fromTextAndUrl($thread_url->toString(), $thread_url)->toString();

    // Send email notifications for members.
    foreach ($members as $member) {
      if (!$member instanceof UserInterface || (int) $member->id() === (int) $this->account->id()) {
        continue;
      }

      $params['member'] = $member;
      $send = $this->userData->get('private_message', $member->id(), 'email_notification');
      $send = is_numeric($send) ? (bool) $send : ($pm_config->get('enable_email_notifications') && $pm_config->get('send_by_default'));

      if (!$send) {
        continue;
      }

      $user_name = $member->getDisplayName();
      $author_name = $message->getOwner()->getDisplayName();
      $message = $message->getMessage();

      $params['message'] = str_replace(
        [
          '[site:name]',
          '[user:display-name]',
          '[private_message:author-name]',
          '[private_message:message]',
          '[private_message_thread:url]',
        ],
        [
          '<strong>' . $site_name . '</strong>',
          '<strong>' . $user_name . '</strong>',
          '<strong>' . $author_name . '</strong>',
          '<strong>' . $message . '</strong>',
          '<strong>' . $thread_link . '</strong>',
        ],
        $message_notification_mail_map['body']);

      $this->mailService->mail('opigno_messaging', 'message_notification', $member->getEmail(), $member->getPreferredLangcode(), $params);
    }
  }

  /**
   * Prepare the render array to display the thread actions.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   The PM thread to get actions for.
   *
   * @return array
   *   Render array to display the thread actions.
   */
  public function getThreadActions(PrivateMessageThreadInterface $thread): array {
    $actions = [];
    $tid = (int) $thread->id();
    $links = [
      'opigno_messaging.confirm_delete_form' => $this->t('Delete'),
      'opigno_messaging.get_edit_thread_form' => $this->t('Edit'),
    ];

    $params = ['private_message_thread' => $tid];
    $options = [
      'attributes' => [
        'class' => ['dropdown-item-text', 'use-ajax'],
      ],
    ];

    // Generate the list of actions available for the given user.
    foreach ($links as $route => $title) {
      $url = Url::fromRoute($route, $params, $options);
      if ($url->access()) {
        $actions[] = Link::fromTextAndUrl($title, $url);
      }
    }

    return [
      '#theme' => 'opigno_pm_thread_actions',
      '#actions' => $actions,
      '#cache' => [
        'contexts' => Cache::mergeContexts($thread->getCacheContexts(), ['user']),
        'tags' => $thread->getCacheTags(),
      ],
    ];
  }

}
