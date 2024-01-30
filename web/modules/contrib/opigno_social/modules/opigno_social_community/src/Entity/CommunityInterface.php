<?php

namespace Drupal\opigno_social_community\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Opigno community entity interface.
 *
 * @package Drupal\opigno_social_community\Entity
 */
interface CommunityInterface extends ContentEntityInterface {

  /**
   * Gets the community title.
   *
   * @return string
   *   The community entity title.
   */
  public function getTitle(): string;

  /**
   * Sets the Opigno community title.
   *
   * @param string $title
   *   The title to be set.
   *
   * @return \Drupal\opigno_social_community\Entity\CommunityInterface
   *   The called Opigno community entity.
   */
  public function setTitle(string $title): CommunityInterface;

  /**
   * Gets the Opigno community owner.
   *
   * @return \Drupal\user\UserInterface|null
   *   The Opigno community owner entity.
   */
  public function getOwner(): ?UserInterface;

  /**
   * Gets the Opigno community owner ID.
   *
   * @return int
   *   The Opigno community owner ID.
   */
  public function getOwnerId(): int;

  /**
   * Sets the Opigno community owner.
   *
   * @param int|null $uid
   *   The owner user ID to be set. The current user will be set by default.
   *
   * @return \Drupal\opigno_social_community\Entity\CommunityInterface
   *   The called Opigno community entity.
   */
  public function setOwner(?int $uid = NULL): CommunityInterface;

  /**
   * Gets the community visibility (private/public).
   *
   * @return string
   *   The community visibility (private/public).
   */
  public function getVisibility(): string;

  /**
   * Checks if the community is public or not.
   *
   * @return bool
   *   Whether the community is public or not.
   */
  public function isPublic(): bool;

  /**
   * Sets the community entity visibility (private/public).
   *
   * @param string $visibility
   *   The community visibility.
   *
   * @return \Drupal\opigno_social_community\Entity\CommunityInterface
   *   The called Opigno community entity.
   */
  public function setVisibility(string $visibility): CommunityInterface;

  /**
   * Gets the list of community members.
   *
   * @param bool $load
   *   Whether the members should be loaded or not. If FALSE, only IDs will be
   *   returned.
   *
   * @return array
   *   The list of the community members.
   */
  public function getMembers(bool $load = FALSE): array;

  /**
   * Adds the community member.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $user
   *   The ID of the user (or loaded account) that should be added as a member.
   *
   * @return \Drupal\opigno_social_community\Entity\CommunityInterface
   *   The called Opigno community entity.
   */
  public function addMember(AccountInterface|int $user): CommunityInterface;

  /**
   * Deletes the community member.
   *
   * @param int $uid
   *   The user ID of the member who should be deleted.
   *
   * @return \Drupal\opigno_social_community\Entity\CommunityInterface
   *   The called Opigno community entity.
   */
  public function deleteMember(int $uid): CommunityInterface;

  /**
   * Checks if the given user is a community member or not.
   *
   * @param \Drupal\Core\Session\AccountInterface|int $account
   *   The user account (or ID) to be checked.
   *
   * @return bool
   *   TRUE if the user is a community member, FALSE otherwise.
   */
  public function isMember(int|AccountInterface $account): bool;

  /**
   * Create a community invitation for the given user.
   *
   * @param int $invitee
   *   The user ID to be invited.
   * @param int $invitor
   *   The invitor ID.
   * @param bool $return_invitation
   *   If TRUE, the invitation entity will be returned.
   *
   * @return \Drupal\Core\Entity\EntityInterface|void
   *   The invitation entity if it should be returned.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function inviteMember(int $invitee, int $invitor, bool $return_invitation = FALSE);

  /**
   * Checks if the given user is invited to the community.
   *
   * @param int|null $uid
   *   The user account ID to be checked for the invitation.
   *
   * @return bool
   *   TRUE if the given user is invited to the community, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function isUserInvited(?int $uid): bool;

  /**
   * Get the list of IDs of all users invited to the community.
   *
   * @return array
   *   The list of IDs of all users invited to the community.
   */
  public function getAllInvitees(): array;

  /**
   * Gets the community pending invitations for the given user.
   *
   * @param int|null $uid
   *   The user account ID to get the community invitations for.
   * @param bool $load
   *   Should invitation entities be loaded or not.
   *
   * @return array
   *   The list of user's community pending invitations.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getUserPendingInvitations(?int $uid, bool $load = TRUE): array;

  /**
   * Gets the community creation timestamp.
   *
   * @return int
   *   The community creation timestamp.
   */
  public function getCreatedTime(): int;

}
