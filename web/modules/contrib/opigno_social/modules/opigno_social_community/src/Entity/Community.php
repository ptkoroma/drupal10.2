<?php

namespace Drupal\opigno_social_community\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\opigno_social_community\Services\CommunityStatistics;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the Opigno community entity.
 *
 * @ContentEntityType(
 *   id = "opigno_community",
 *   label = @Translation("Community"),
 *   label_singular = @Translation("Community"),
 *   label_plural = @Translation("Communities"),
 *   label_count = @PluralTranslation(
 *     singular = "@count community",
 *     plural = "@count communities",
 *   ),
 *   base_table = "opigno_community",
 *   admin_permission = "administer opigno communities",
 *   field_ui_base_route = "opigno_community.settings",
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\opigno_social_community\CommunityViewsData",
 *     "access" = "Drupal\opigno_social_community\CommunityAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\opigno_social_community\Form\CommunityForm",
 *       "add" = "Drupal\opigno_social_community\Form\CommunityForm",
 *       "edit" = "Drupal\opigno_social_community\Form\CommunityForm",
 *       "delete" = "Drupal\opigno_social_community\Form\CommunityDeleteForm",
 *     },
 *     "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\opigno_social_community\RouteProvider\OpignoCommunityHtmlRouteProvider",
 *     },
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "uid",
 *     "title" = "title",
 *     "label" = "title",
 *     "created" = "created",
 *     "visibility" = "visibility",
 *   },
 *   links = {
 *     "canonical" = "/community/{opigno_community}",
 *     "add-form" = "/community/create",
 *     "edit-form" = "/community/{opigno_community}/edit",
 *     "delete-form" = "/community/{opigno_community}/delete",
 *   },
 * )
 *
 * @package Drupal\opigno_social_community\Entity
 *
 * @ingroup opigno_social_community
 */
class Community extends ContentEntityBase implements CommunityInterface {

  use StringTranslationTrait;

  /**
   * The "public" community visibility type.
   */
  const VISIBILITY_PUBLIC = 'public';

  /**
   * The "private" community visibility type.
   */
  const VISIBILITY_PRIVATE = 'private';

  /**
   * The "restricted" community visibility type.
   */
  const VISIBILITY_RESTRICTED = 'restricted';

  /**
   * Community administration config name.
   */
  const ADMIN_CONFIG_NAME = 'opigno_social_community.admin_settings';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->addConstraint('opigno_unique_title')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'settings' => [
          'placeholder' => t('Community name'),
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Image'))
      ->setSettings([
        'target_type' => 'media',
        'handler' => 'default',
        'handler_settings' => ['target_bundles' => ['image']],
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_browser_entity_reference',
        'settings' => [
          'entity_browser' => 'community_images_media_entity_browser',
          'field_widget_remove' => TRUE,
          'open' => TRUE,
          'selection_mode' => 'selection_append',
          'field_widget_display' => 'rendered_entity',
          'field_widget_display_settings' => [
            'view_mode' => 'image_only',
          ],
          'field_widget_edit' => FALSE,
          'field_widget_replace' => FALSE,
          'third_party_settings' => [
            'type' => 'entity_browser_entity_reference',
          ],
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['visibility'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setSetting('allowed_values', static::getVisibilityOptions())
      ->setDefaultValue(static::VISIBILITY_PUBLIC)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['members'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Members'))
      ->setSetting('target_type', 'user')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'))
      ->setRequired(TRUE);

    $fields['updated'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Updated'))
      ->setDescription(t('The time that the community info was last edited.'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * Gets the available visibility options.
   *
   * @return array
   *   The list of available visibility options.
   */
  public static function getVisibilityOptions(): array {
    return [
      static::VISIBILITY_PUBLIC => t('Public'),
      static::VISIBILITY_PRIVATE => t('Private'),
      static::VISIBILITY_RESTRICTED => t('Restricted'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): CommunityInterface {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): ?UserInterface {
    $uid = $this->getOwnerId();
    if (!$uid) {
      return NULL;
    }
    $account = User::load($uid);

    return $account instanceof UserInterface ? $account : NULL;
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
  public function setOwner(?int $uid = NULL): CommunityInterface {
    $user = NULL;
    if ($uid) {
      $user = User::load($uid);
    }

    $uid = $user instanceof AccountInterface ? $uid : \Drupal::currentUser()->id();
    $this->set('uid', $uid);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility(): string {
    return $this->get('visibility')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function isPublic(): bool {
    return $this->getVisibility() === static::VISIBILITY_PUBLIC;
  }

  /**
   * {@inheritdoc}
   */
  public function setVisibility(string $visibility): CommunityInterface {
    $this->set('visibility', $visibility);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMembers(bool $load = FALSE): array {
    $values = $this->get('members')->getValue();
    $members = [];
    foreach ($values as $value) {
      $id = $value['target_id'];
      $members[$id] = $id;
    }

    return $load ? User::loadMultiple($members) : $members;
  }

  /**
   * {@inheritdoc}
   */
  public function addMember(AccountInterface|int $user): CommunityInterface {
    if (!$user instanceof AccountInterface) {
      $user = User::load($user);
    }

    $uid = $user->id();
    $members = $this->getMembers();

    if (!$user instanceof AccountInterface || in_array($uid, $members) || $user->isAnonymous()) {
      return $this;
    }

    $members[] = $uid;
    $this->set('members', $members);

    if (!$this->isNew()) {
      $this->sendNewMemberNotifications($user);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMember(int $uid): CommunityInterface {
    $members = $this->getMembers();
    $key = array_search($uid, $members);

    if ($key && $uid !== $this->getOwnerId()) {
      unset($members[$key]);
      $this->set('members', $members);

      // Delete all community invitations accepted by invitee.
      $invitation_storage = \Drupal::entityTypeManager()->getStorage('opigno_community_invitation');
      $invitations = $invitation_storage->loadByProperties([
        'invitee' => $uid,
        'community' => $this->id(),
        'status' => 1,
      ]);

      if ($invitations) {
        $invitation_storage->delete($invitations);
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isMember(int|AccountInterface $account): bool {
    $uid = $account instanceof AccountInterface ? $account->id() : $account;
    return in_array($uid, $this->getMembers());
  }

  /**
   * {@inheritdoc}
   */
  public function inviteMember(int $invitee, int $invitor, bool $return_invitation = FALSE) {
    if ($this->isUserInvited($invitee) || $this->isMember($invitee)) {
      return;
    }

    // Send the invitation to selected users.
    $invitation = \Drupal::entityTypeManager()->getStorage('opigno_community_invitation')->create([
      'uid' => $invitor,
      'invitee' => $invitee,
      'community' => $this->id(),
      'status' => FALSE,
    ]);

    $invitation->save();

    if ($return_invitation) {
      return $invitation;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isUserInvited(?int $uid, bool $load = FALSE): bool {
    $uid = $uid ?? \Drupal::currentUser()->id();
    $invitations = \Drupal::entityTypeManager()->getStorage('opigno_community_invitation')
      ->getQuery()
      ->condition('invitee', $uid)
      ->condition('community', $this->id())
      ->condition('status', 0)
      ->count()
      ->execute();

    return $invitations > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllInvitees(): array {
    if ($this->isNew()) {
      return [];
    }

    $invitees = \Drupal::database()->select('opigno_community_invitation', 'oci')
      ->fields('oci', ['invitee'])
      ->condition('community', $this->id())
      ->condition('status', 0)
      ->execute()
      ->fetchAllKeyed(0, 0);

    return $invitees;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserPendingInvitations(?int $uid, bool $load = TRUE): array {
    $uid = $uid ?? \Drupal::currentUser()->id();
    $storage = \Drupal::entityTypeManager()->getStorage('opigno_community_invitation');
    $ids = $storage->getQuery()
      ->condition('invitee', $uid)
      ->condition('community', $this->id())
      ->condition('status', FALSE)
      ->execute();

    return $load && $ids ? $storage->loadMultiple($ids) : $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->getString();
  }

  /**
   * Sends the notifications when the new member is added.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $user
   *   The new member (loaded entity or user ID).
   */
  protected function sendNewMemberNotifications(AccountInterface|int $user): void {
    if (!$user instanceof AccountInterface) {
      $user = User::load($user);
    }

    if (!$user instanceof AccountInterface) {
      return;
    }

    $uid = (int) $user->id();
    // Send the notification to the new member.
    $title = $this->getTitle();
    $url = Url::fromRoute('entity.opigno_community.canonical', [
      'opigno_community' => $this->id(),
    ])->toString();

    try {
      $msg = $this->t('You are now a member of the community "@title"', ['@title' => $title]);
      opigno_set_message($uid, $msg, $url);
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
    }

    // Send the notification about the new user to the community owner.
    $owner_id = $this->getOwnerId();
    if ($uid !== $owner_id) {
      $url = Url::fromRoute('entity.user.canonical', ['user' => $uid])->toString();
      $msg = $this->t('@user became a member of your community "@title"', [
        '@user' => $user->getDisplayName(),
        '@title' => $title,
      ]);

      try {
        opigno_set_message($owner_id, $msg, $url);
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_social_community_exception', $e);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set the current user as the owner if none was set before.
    if (!$this->getOwnerId()) {
      $uid = (int) \Drupal::currentUser()->id();
      $this->setOwner($uid);
    }

    // Add the owner to the members list.
    $uid = $this->getOwnerId();
    $this->addMember($uid);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $uid = \Drupal::currentUser()->id();
    $title = $this->getTitle();
    $id = (int) $this->id();
    $url = Url::fromRoute('entity.opigno_community.canonical', [
      'opigno_community' => $id,
    ])->toString();

    if (!$update) {
      // Send the notification about the community creation to the current user.
      $msg = $this->t('You created the community "@title"', ['@title' => $title]);
      try {
        opigno_set_message($uid, $msg, $url);
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_social_community_exception', $e);
      }

      // Create the statistics table record.
      $stats_service = \Drupal::service('opigno_social_community.statistics');
      if ($stats_service instanceof CommunityStatistics) {
        $stats_service->create($id);
      }
      return;
    }

    // Update the community.
    $original = $this->original ?? NULL;
    if (!$original instanceof CommunityInterface) {
      return;
    }

    // Send the notification if one of the listed fields has been updated.
    $fields = [
      'title',
      'description',
      'visibility',
    ];
    foreach ($fields as $field) {
      if ($original->get($field)->getValue() !== $this->get($field)->getValue()) {
        try {
          opigno_set_message($uid, $this->t('You modified the community "@title"', ['@title' => $title]), $url);
        }
        catch (EntityStorageException $e) {
          watchdog_exception('opigno_social_community_exception', $e);
        }

        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();

    $id = (int) $this->id();
    // Delete the statistics record.
    $stats_service = \Drupal::service('opigno_social_community.statistics');
    if ($stats_service instanceof CommunityStatistics) {
      $stats_service->delete($id);
    }

    // Delete all related posts, comments and likes.
    $entity_type_manager = \Drupal::entityTypeManager();
    $posts_storage = $entity_type_manager->getStorage('opigno_community_post');
    $posts = $posts_storage->loadByProperties(['community' => $id]);
    if ($posts) {
      $posts_storage->delete($posts);
    }

    // Delete the community invitations.
    $invitation_storage = $entity_type_manager->getStorage('opigno_community_invitation');
    $invitations = $invitation_storage->loadByProperties(['community' => $id]);
    if ($invitations) {
      $invitation_storage->delete($invitations);
    }

    // Send the notification about the community deletion to the current user.
    try {
      opigno_set_message(\Drupal::currentUser()->id(), $this->t('You deleted the community "@title"', [
        '@title' => $this->getTitle(),
      ]));
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
    }
  }

}
