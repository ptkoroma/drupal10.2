<?php

namespace Drupal\opigno_social_community\Entity;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\opigno_social\Entity\UserInvitation;

/**
 * Defines the Opigno community invitation entity.
 *
 * @ContentEntityType(
 *   id = "opigno_community_invitation",
 *   label = @Translation("Opigno community invitation"),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\opigno_social_community\CommunityInvitationAccessControlHandler"
 *   },
 *   base_table = "opigno_community_invitation",
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "uid",
 *     "invitee" = "invitee",
 *     "community" = "community",
 *   },
 * )
 *
 * @package Drupal\opigno_social_community\Entity
 */
class CommunityInvitation extends UserInvitation implements CommunityInvitationInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['community'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Community'))
      ->setSetting('target_type', 'opigno_community')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['is_join_request'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is join request'))
      ->setDefaultValue(0);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunityId(): int {
    return $this->get('community')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getCommunity(): ?CommunityInterface {
    $id = $this->getCommunityId();
    $community = Community::load($id);

    return $community instanceof CommunityInterface ? $community : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCommunity(CommunityInterface|int $community): CommunityInvitationInterface {
    if (!$community instanceof CommunityInterface) {
      Community::load($community);
    }

    if ($community instanceof CommunityInterface) {
      $this->set('community', $community->id());
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isJoinRequest(): bool {
    return $this->hasField('is_join_request') && $this->get('is_join_request')->getString();
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvitationMessage(AccountInterface $owner): ?TranslatableMarkup {
    return !$this->isJoinRequest()
      ? $this->t('You received an invitation for the community "@community"', [
        '@community' => $this->getCommunity()->getTitle(),
      ])
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvitationUrl(): string {
    return Url::fromRoute('opigno_social_community.join_communities')->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $invitee = $this->getInvitee();
    $community = $this->getCommunity();

    if (!$invitee instanceof AccountInterface || !$community instanceof CommunityInterface) {
      return;
    }

    $is_join_request = $this->isJoinRequest();
    $invitee_url = Url::fromRoute('entity.user.canonical', ['user' => $invitee->id()])->toString();
    $invitee_name = $invitee->getDisplayName();
    $invitor = $this->getOwnerId();
    $community_title = $community->getTitle();

    if ($update) {
      return;
    }

    // Send the notification to the community owner when the invitation created.
    if ($is_join_request) {
      $msg = $this->t('@user requested to join the community "@community"', [
        '@user' => $invitee_name,
        '@community' => $community_title,
      ]);
      $url = Url::fromRoute('entity.opigno_community.canonical', ['opigno_community' => $community->id()])->toString();
    }
    else {
      $msg = $this->t('You sent an invitation to @user for the community "@community"', [
        '@user' => $invitee_name,
        '@community' => $community_title,
      ]);
      $url = $invitee_url;
    }

    try {
      opigno_set_message($invitor, $msg, $url);
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
    }
  }

}
