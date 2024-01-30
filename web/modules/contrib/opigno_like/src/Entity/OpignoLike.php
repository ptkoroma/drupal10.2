<?php

namespace Drupal\opigno_like\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Drupal\user\Entity\User;

/**
 * Defines the Opigno Like entity type.
 *
 * @ingroup opigno_like
 *
 * @ContentEntityType(
 *   id = "opigno_like",
 *   label = @Translation("Opigno like"),
 *   base_table = "opigno_like",
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "uid",
 *     "entity_id" = "entity_id",
 *     "type" = "type",
 *     "created" = "created",
 *   },
 * )
 *
 * @package Drupal\opigno_like\Entity
 */
class OpignoLike extends ContentEntityBase implements OpignoLikeInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Liked entity ID'))
      ->setRequired(TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Liked entity type'))
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId(): int {
    return (int) $this->get('uid')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setUserId(int $uid): OpignoLikeInterface {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(): ?UserInterface {
    $uid = $this->getUserId();
    if (!$uid) {
      return NULL;
    }
    $user = User::load($uid);

    return $user instanceof UserInterface ? $user : NULL;
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
  public function setCreatedTime(int $timestamp): OpignoLikeInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLikedEntityId(): int {
    return (int) $this->get('entity_id')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setLikedEntityId(int $eid): OpignoLikeInterface {
    $this->set('entity_id', $eid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLikedEntity(): ?EntityInterface {
    $type = $this->getLikedEntityType();
    $id = $this->getLikedEntityId();
    try {
      $entity = \Drupal::entityTypeManager()->getStorage($type)->load($id);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_like_exception', $e);
      $entity = NULL;
    }

    return $entity instanceof EntityInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLikedEntityType(): string {
    return $this->get('type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setLikedEntityType(string $type): OpignoLikeInterface {
    $this->set('type', $type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // Set the current user as the author if no other was set before.
    if (!$this->getUserId()) {
      $uid = (int) \Drupal::currentUser()->id();
      $this->setUserId($uid);
    }

    // Prevent duplicates creation.
    $duplicate = $storage->loadByProperties([
      'uid' => $this->getUserId(),
      'entity_id' => $this->getLikedEntityId(),
      'type' => $this->getLikedEntityType(),
    ]);
    $duplicate = reset($duplicate);

    if ($duplicate instanceof OpignoLikeInterface) {
      throw new EntityStorageException('The duplicated Opigno like entity can not be saved.');
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Notify the post/comment author about the new like.
    if ($update) {
      return;
    }

    $entity = $this->getLikedEntity();
    $user = $this->getUser();
    if ($entity instanceof LikeableEntityInterface && $user instanceof UserInterface) {
      $entity->sendLikeNotification($user);
    }
  }

}
