<?php

namespace Drupal\opigno_social\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the User Invitation entity.
 *
 * @ingroup opigno_social
 *
 * @ContentEntityType(
 *   id = "user_invitation",
 *   label = @Translation("User invitation"),
 *   handlers = {
 *     "views_data" = "Drupal\opigno_social\UserInvitationViewsData",
 *   },
 *   base_table = "opigno_user_invitations",
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "uid",
 *     "invitee" = "invitee",
 *   },
 * )
 */
class UserInvitation extends ContentEntityBase implements UserInvitationInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Invitation owner'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['invitee'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Invitee')
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Accepted'))
      ->setDefaultValue(FALSE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'));

    return $fields;
  }

  /**
   * Get the invitation user.
   *
   * @param int $uid
   *   The user ID to load the user entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   The invitation user.
   */
  private function getUser(int $uid): ?UserInterface {
    if (!$uid) {
      return NULL;
    }
    $user = User::load($uid);

    return $user instanceof UserInterface ? $user : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): int {
    return (int) $this->get('uid')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): ?UserInterface {
    $uid = $this->getOwnerId();

    return $this->getUser($uid);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId(int $uid): UserInvitationInterface {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInviteeId(): int {
    return (int) $this->get('invitee')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getInvitee(): ?UserInterface {
    $uid = $this->getInviteeId();

    return $this->getUser($uid);
  }

  /**
   * {@inheritdoc}
   */
  public function setInviteeId(int $uid): UserInvitationInterface {
    $this->set('invitee', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isAccepted(): bool {
    return (bool) $this->get('status')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setAccepted(bool $status = TRUE): UserInvitationInterface {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Create the notification for the invitee when the connection request is
    // created.
    if ($update) {
      return;
    }

    $owner = $this->getOwner();
    if (!$owner instanceof UserInterface) {
      return;
    }

    $msg = $this->getInvitationMessage($owner);
    if ($msg) {
      try {
        opigno_set_message($this->getInviteeId(), $msg, $this->getInvitationUrl());
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_social_exception', $e);
      }
    }
  }

  /**
   * Gets the invitation message text.
   *
   * @param \Drupal\Core\Session\AccountInterface $owner
   *   The invitation owner.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The invitation message text.
   */
  protected function getInvitationMessage(AccountInterface $owner): ?TranslatableMarkup {
    return $this->t('@user would like to connect', [
      '@user' => $owner->getDisplayName(),
    ]);
  }

  /**
   * Gets the invitation url.
   *
   * @return string
   *   The invitation url.
   */
  protected function getInvitationUrl(): string {
    $url = Url::fromRoute('opigno_social.manage_connections')->toString();
    return $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url;
  }

}
