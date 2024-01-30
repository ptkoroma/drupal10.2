<?php

namespace Drupal\opigno_notification\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\opigno_notification\OpignoNotificationInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\user\UserInterface;

/**
 * Defines the opigno_notification entity.
 *
 * @ingroup opigno_notification
 *
 * @ContentEntityType(
 *   id = "opigno_notification",
 *   label = @Translation("Opigno Notification"),
 *   base_table = "opigno_notification",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\opigno_notification\Entity\Controller\OpignoNotificationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\opigno_notification\OpignoNotificationAccessControlHandler",
 *   },
 * )
 */
class OpignoNotification extends ContentEntityBase implements OpignoNotificationInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the OpignoNotification entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the OpignoNotification entity.'))
      ->setReadOnly(TRUE);

    $fields['created'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Creation time'))
      ->setDescription(t('The creation time of the notification.'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID of the notification receiver.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => 0,
        'target_type' => 'user',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ]);

    $fields['message'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Message'))
      ->setDescription(t('The message of the notification.'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 1,
      ]);

    $fields['has_read'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Has Read'))
      ->setDescription(t('The status of the notification.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => 0,
      ]);

    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Url'))
      ->setDescription(t('The url string for notification entity.'))
      ->setSettings([
        'max_length' => 50,
      ])
      ->setInitialValue('/notifications')
      ->setDefaultValue('/notifications');

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);

    $values += [
      'created' => \Drupal::time()->getRequestTime(),
      'has_read' => FALSE,
    ];
  }

  /**
   * Returns unread notifications count.
   *
   * @param \Drupal\user\Entity\User|null $account
   *   User for which notifications will be counted.
   *   Current user if not specified.
   *
   * @return int
   *   Unread notifications count.
   */
  public static function unreadCount($account = NULL): int {
    if ($account === NULL) {
      $account = \Drupal::currentUser();
    }

    $query = \Drupal::entityQuery('opigno_notification');
    $query->condition('uid', $account->id());
    $query->condition('has_read', FALSE);
    $query->count();
    $result = $query->execute();

    return (int) $result;
  }

  /**
   * Returns unread notifications list.
   *
   * @param \Drupal\user\UserInterface|null $account
   *   User to get notifications for. Current user will be taken by default.
   * @param int $amount
   *   The number of notifications to be loaded.
   *
   * @return array
   *   Unread notifications list.
   */
  public static function getUnreadNotifications(UserInterface $account = NULL, int $amount = 0): array {
    if ($account === NULL) {
      $account = \Drupal::currentUser();
    }

    // Get IDs of unread notifications.
    $query = \Drupal::entityQuery('opigno_notification')
      ->condition('uid', (int) $account->id())
      ->condition('has_read', FALSE)
      ->sort('created', 'DESC');
    if ($amount) {
      $query->range(0, $amount);
    }
    $ids = $query->execute();

    // Load entities.
    $notifications = [];
    if (is_array($ids) && $ids) {
      try {
        $notifications = \Drupal::entityTypeManager()->getStorage('opigno_notification')->loadMultiple($ids);
      }
      catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
        watchdog_exception('opigno_notification_exception', $e);
      }
    }

    return $notifications;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(): ?int {
    $uid = $this->get('uid')->getString() ?? NULL;
    return $uid ? (int) $uid : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setUser(int $value): OpignoNotificationInterface {
    $this->set('uid', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): string {
    return $this->get('message')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(string $value): OpignoNotificationInterface {
    $this->set('message', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHasRead(): bool {
    return (bool) $this->get('has_read')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setHasRead(bool $value): OpignoNotificationInterface {
    $this->set('has_read', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): string {
    return $this->get('url')->getString() ?? '/notifications';
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl(string $value): OpignoNotificationInterface {
    // Get the real path from alias if the module is enabled.
    if (\Drupal::moduleHandler()->moduleExists('path_alias')) {
      // Get the clean alias without the language prefix.
      $langs = \Drupal::languageManager()->getLanguages();
      $aliases = [$value];
      foreach ($langs as $lang) {
        $lang_prefix = '/' . $lang->getId();
        if (mb_strpos($value, $lang_prefix) === 0) {
          $aliases[] = mb_substr($value, strlen($lang_prefix));
        }
      }

      // Get the path by alias.
      $path_alias = \Drupal::service('path_alias.manager');
      if ($path_alias instanceof AliasManagerInterface) {
        foreach ($aliases as $alias) {
          $path = $path_alias->getPathByAlias($alias);
          if ($path !== $value) {
            $value = $path;
            break;
          }
        }
      }
    }

    $this->set('url', $value);
    return $this;
  }

}
