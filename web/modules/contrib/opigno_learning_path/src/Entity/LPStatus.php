<?php

namespace Drupal\opigno_learning_path\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_learning_path\LPStatusInterface;

/**
 * Defines the User Learning Path attempt status entity.
 *
 * @ingroup opigno_learning_path
 *
 * @ContentEntityType(
 *   id = "user_lp_status",
 *   label = @Translation("User Learning Path status"),
 *   handlers = {
 *     "views_data" = "Drupal\opigno_learning_path\Entity\LPStatusViewsData",
 *   },
 *   base_table = "user_lp_status",
 *   entity_keys = {
 *     "id" = "id",
 *     "gid" = "gid",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "started" = "started",
 *     "finished" = "finished",
 *   },
 * )
 */
class LPStatus extends ContentEntityBase implements LPStatusInterface {

  /**
   * Static cache of user attempts.
   *
   * @var mixed
   */
  protected $userAttempts = [];

  /**
   * Static cache of user active attempt.
   *
   * @var mixed
   */
  protected $userActiveAttempt = [];

  /**
   * {@inheritdoc}
   */
  public function getTrainingId() {
    return $this->get('gid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setTrainingId($id) {
    $this->set('gid', $id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTraining() {
    return $this->get('gid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setTraining($training) {
    $this->set('gid', $training->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setUser(AccountInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    $value = $this->get('status')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore() {
    return $this->get('score')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setScore($value) {
    $this->set('score', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFinished() {
    $value = $this->get('finished')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setFinished($timestamp) {
    $this->set('finished', $timestamp);
    $is_finalized = $timestamp > 0 && !$this->hasUnfinishedModuleAttempts();
    $this->setFinalized($is_finalized);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isFinished(): bool {
    return (bool) $this->get('finalized')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setFinalized(bool $is_finalized): LPStatusInterface {
    $this->set('finalized', $is_finalized);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStarted() {
    $value = $this->get('started')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return $value[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setStarted($timestamp) {
    $this->set('started', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isStarted() {
    return (bool) $this->started->value != 0;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCurrentLpAttempt(GroupInterface $group, AccountInterface $user, bool $load = TRUE, bool $finished = FALSE) {
    $results_storage = \Drupal::entityTypeManager()->getStorage('user_lp_status');
    $query = $results_storage->getQuery();
    $query
      ->condition('gid', $group->id())
      ->condition('uid', $user->id());
    if (!$finished) {
      $query->condition('finalized', 0);
    }
    $results = $query
      ->sort('id', 'DESC')
      ->execute();

    if (!$results) {
      return FALSE;
    }
    $id = key($results);
    return $load ? $results_storage->load($id) : $id;
  }

  /**
   * {@inheritdoc}
   */
  public function hasUnfinishedModuleAttempts(): bool {
    $unfinished = \Drupal::entityTypeManager()
      ->getStorage('user_module_status')
      ->getQuery()
      ->condition('lp_status', $this->id())
      ->condition('finished', 0)
      ->execute();

    return !empty($unfinished);
  }

  /**
   * Gets training certificate expiration flag.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return bool
   *   True if training certificate expiration set, false otherwise.
   */
  public static function isCertificateExpireSet(Group $group): bool {
    return (bool) ($group->hasField('field_certificate_expire') ? $group->get('field_certificate_expire')->getString() : FALSE);
  }

  /**
   * Gets training certificate expiration period.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return int|null
   *   Training certificate expiration period.
   */
  public static function getCertificateExpirationPeriod(Group $group) {
    $value = $group->get('field_certificate_expire_results')->getValue();

    if (empty($value)) {
      return NULL;
    }

    return (int) $value[0]['value'];
  }

  /**
   * Returns training latest certificate timestamp.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   *
   * @return int|null
   *   Timestamp if found, null otherwise.
   */
  public static function getLatestCertificateTimestamp($gid, $uid) {
    $db_connection = \Drupal::service('database');
    $result = $db_connection->select('user_lp_status_expire', 'lps')
      ->fields('lps', ['latest_cert_date'])
      ->condition('gid', $gid)
      ->condition('uid', $uid)
      ->execute()->fetchField();

    if ($result) {
      return $result;
    }

    return NULL;
  }

  /**
   * Returns training certificate expire timestamp.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   *
   * @return int|null
   *   Timestamp if found, null otherwise.
   */
  public static function getCertificateExpireTimestamp($gid, $uid): ?int {
    $group = Group::load($gid);
    try {
      $completed_on = opigno_learning_path_completed_on($gid, $uid, TRUE);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | PluginException $e) {
      $completed_on = NULL;
      watchdog_exception('opigno_learning_path_exception', $e);
    }

    // Get the data from the database if that's impossible to get the completion
    // date (old approach). This returns the incorrect result if the user
    // completed the same LP for several times.
    if (!$completed_on || !$group instanceof GroupInterface) {
      return \Drupal::database()->select('user_lp_status_expire', 'lps')
        ->fields('lps', ['expire'])
        ->condition('gid', $gid)
        ->condition('uid', $uid)
        ->execute()
        ->fetchField();
    }

    // Get the certificate expiration time depending on the completion timestamp
    // and LP certificate validity settings.
    if (!self::isCertificateExpireSet($group)) {
      return NULL;
    }

    // Get the available options for the expiration time.
    $field = 'field_certificate_expire_results';
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('group', 'learning_path');
    $definition = $definitions[$field] ?? NULL;

    if (!$definition instanceof FieldDefinitionInterface) {
      return NULL;
    }

    $options = $definition->getSetting('allowed_values');
    $valid_for = $group->get($field)->getString();
    $valid_for = $options[$valid_for] ?? $valid_for . ' months';

    return strtotime($valid_for, $completed_on) ?? NULL;
  }

  /**
   * Returns LP user progress.
   */
  public static function learningPathUserProgress(Group $group, $uid) {
    $progress = 0;
    $contents = LPManagedContent::loadByLearningPathId($group->id());
    if (!empty($contents)) {
      $content_count = count($contents);
      foreach ($contents as $content) {
        $content_type = $content->getLearningPathContentType();
        $user_score = $content_type->getUserScore($uid, $content->getEntityId());
        $progress += $user_score;
      }
      $progress = round(($progress / $content_count) * 100);
    }
    return $progress;
  }

  /**
   * Saves training certificate expire timestamp.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param int $latest_cert_date
   *   Training latest certificate timestamp.
   * @param int $expire
   *   Training certificate expire timestamp.
   */
  public static function setCertificateExpireTimestamp($gid, $uid, $latest_cert_date = 0, $expire = 0) {
    $db_connection = \Drupal::service('database');
    try {
      $result = $db_connection->select('user_lp_status_expire', 'lps')
        ->fields('lps', ['id'])
        ->condition('gid', $gid)
        ->condition('uid', $uid)
        ->execute()->fetchField();

      if (!$result) {
        // Certification not exists.
        // Add training certification for the user.
        $db_connection->insert('user_lp_status_expire')
          ->fields([
            'gid' => $gid,
            'uid' => $uid,
            'latest_cert_date' => $latest_cert_date,
            'expire' => $expire,
          ])
          ->execute();
      }

      if ($result) {
        // Certification expired.
        // Update certification.
        $db_connection->merge('user_lp_status_expire')
          ->key([
            'gid' => $gid,
            'uid' => $uid,
          ])
          ->fields([
            'gid' => $gid,
            'uid' => $uid,
            'latest_cert_date' => $latest_cert_date,
            'expire' => $expire,
          ])
          ->execute();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_learning_path')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Returns flag if training certificate expired for the user.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param int $uid
   *   User ID.
   *
   * @return bool
   *   True if training certificate expired for the user, false otherwise.
   */
  public static function isCertificateExpired(Group $group, $uid): bool {
    $expiration = self::getCertificateExpireTimestamp((int) $group->id(), $uid);
    return $expiration && $expiration < time();
  }

  /**
   * Removes training certificate expire timestamp.
   *
   * @param int $gid
   *   Group ID.
   * @param int|null $uid
   *   User ID.
   */
  public static function removeCertificateExpiration($gid, $uid = NULL) {
    $db_connection = \Drupal::service('database');
    try {
      $query = $db_connection->delete('user_lp_status_expire');
      $query->condition('gid', $gid);
      if ($uid) {
        $query->condition('uid', $uid);
      }
      $query->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_learning_path')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Returns training start date for displaying statistics.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param int $uid
   *   User ID.
   *
   * @return int|null
   *   Training start date timestamp if exists, null otherwise.
   */
  public static function getTrainingStartDate(Group $group, $uid) {
    $start_date = NULL;

    $expiration_set = LPStatus::isCertificateExpireSet($group);
    if ($expiration_set) {
      // If certificate expiration set for training.
      // Get certificate expire timestamp.
      $gid = $group->id();
      if ($expire_timestamp = LPStatus::getCertificateExpireTimestamp($gid, $uid)) {
        if (time() >= $expire_timestamp) {
          // Certificate expired.
          $start_date = $expire_timestamp;
        }
        else {
          // Certificate not expired.
          // Get latest certification timestamp.
          if ($existing_cert_date = LPStatus::getLatestCertificateTimestamp($gid, $uid)) {
            $start_date = $existing_cert_date;
          }
        }
      }
    }

    return $start_date;
  }

  /**
   * Sets a user notified.
   *
   * @param int $gid
   *   The training ID.
   * @param int $uid
   *   The user ID.
   * @param int $value
   *   The user notification value.
   */
  public static function setUserNotification($gid, $uid, $value) {
    $db_connection = \Drupal::service('database');

    try {
      $db_connection->merge('user_lp_status_expire')
        ->key([
          'gid' => $gid,
          'uid' => $uid,
        ])
        ->fields([
          'notified' => $value,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('opigno_learning_path')->error($e->getMessage());
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Returns training certificate expiration message.
   *
   * @param int $gid
   *   Group ID.
   * @param int $uid
   *   User ID.
   * @param string $type
   *   Message text type 'valid'|'expired'|null.
   *
   * @return string
   *   Training certificate expiration message.
   */
  public static function getCertificateExpirationMessage($gid, $uid, $type = NULL) {
    $expire_text = '';
    if (!empty($type)) {
      switch ($type) {
        case 'valid':
          $expire_text = t('Valid until') . ' ';
          break;

        case 'expired':
          $expire_text = t('Expired on') . ' ';
          break;
      }
    }

    $date_formatter = \Drupal::service('date.formatter');
    $expire = LPStatus::getCertificateExpireTimestamp($gid, $uid);

    return !empty($expire) ? $expire_text . $date_formatter->format($expire, 'custom', 'F d, Y') : '';
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Term entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the training status.'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID of the LP Status entity.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['gid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Training'))
      ->setDescription(t('The Training of the LP Status entity.'))
      ->setSettings([
        'target_type' => 'group',
        'default_value' => 0,
      ]);

    $fields['score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Score'))
      ->setDescription(t('The score the user obtained for the training.'));

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setDescription(t('The training status - passed/failed.'))
      ->setSettings([
        'max_length' => 15,
      ])
      ->setDefaultValue('');

    $fields['started'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Started'))
      ->setDescription(t('The time that the training has started.'))
      ->setDefaultValue(0);

    $fields['finished'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Finished timestamp'))
      ->setDescription(t('The time that the training finished.'))
      ->setDefaultValue(0);

    $fields['finalized'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Finalizing status'))
      ->setDescription(t('The LP attempt finalizing status.'))
      ->setDefaultValue(FALSE);

    return $fields;
  }

}
