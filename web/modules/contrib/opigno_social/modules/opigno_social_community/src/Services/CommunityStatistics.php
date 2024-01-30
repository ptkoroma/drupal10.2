<?php

namespace Drupal\opigno_social_community\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social_community\Entity\CommunityPostInterface;

/**
 * Defines a service for storing and retrieving community statistics.
 *
 * @package Drupal\opigno_social_community\Services
 */
class CommunityStatistics {

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The community post storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $postStorage;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * CommunityStatistics constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $account
  ) {
    $this->database = $database;
    $this->postStorage = $entity_type_manager->getStorage('opigno_community_post');
    $this->account = $account;
  }

  /**
   * Inserts an empty record for the community statistics.
   *
   * @param int $community
   *   The ID of the community entity to create a record for.
   *
   * @throws \Exception
   */
  public function create(int $community): void {
    $this->database->insert('opigno_community_statistics')
      ->fields([
        'community_id',
        'last_post_id',
        'last_post_timestamp',
        'post_count',
      ])
      ->values([
        $community,
        0,
        0,
        0,
      ])
      ->execute();
  }

  /**
   * Deletes a community statistics record.
   *
   * @param int $community
   *   The ID of the community entity to delete a record for.
   */
  public function delete(int $community): void {
    $this->database->delete('opigno_community_statistics')
      ->condition('community_id', $community)
      ->execute();
  }

  /**
   * Updates a community statistics record when a post is created.
   *
   * @param int $community
   *   The ID of the community entity to update a record for.
   * @param \Drupal\opigno_social_community\Entity\CommunityPostInterface $post
   *   The latest community post.
   */
  public function updateAddPost(int $community, CommunityPostInterface $post): void {
    $this->database->update('opigno_community_statistics')
      ->condition('community_id', $community)
      ->fields([
        'last_post_id' => (int) $post->id(),
        'last_post_timestamp' => $post->getCreatedTime(),
      ])
      ->expression('post_count', 'post_count + 1')
      ->execute();
  }

  /**
   * Updates a community statistics record when a post is deleted.
   *
   * @param int $community
   *   The ID of the community entity to update a record for.
   * @param int $post
   *   The community post that has been deleted.
   */
  public function updateDeletePost(int $community, int $post): void {
    $timestamp = 0;
    $pid = 0;
    $last_post_id = $this->postStorage->getQuery()
      ->condition('community', $community)
      ->condition('id', $post, '!=')
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    $last_post_id = reset($last_post_id);

    if ($last_post_id) {
      $last_post = $this->postStorage->load($last_post_id);
      if ($last_post instanceof CommunityPostInterface) {
        $timestamp = $last_post->getCreatedTime();
        $pid = (int) $last_post_id;
      }
    }

    $this->database->update('opigno_community_statistics')
      ->condition('community_id', $community)
      ->fields([
        'last_post_id' => $pid,
        'last_post_timestamp' => $timestamp,
      ])
      ->expression('post_count', 'post_count - 1')
      ->execute();
  }

  /**
   * Gets N user communities with the recent activities (posts/comments).
   *
   * @param int $number
   *   The number of communities to be returned. Leave empty to get all
   *   communities the user is a member of with comments/posts sorted by the
   *   latest post creation date.
   *
   * @return array
   *   The list of user communities with the recent activities (posts/comments).
   *   The array format is [community_id => community_title].
   */
  public function getLatestActiveUserCommunities(int $number = 0): array {
    $query = $this->database->select('opigno_community_statistics', 'occ');
    $query->join('opigno_community__members', 'ocm', 'ocm.entity_id = occ.community_id');
    $query->join('opigno_community', 'oc', 'oc.id = occ.community_id');
    $query->fields('oc', ['id', 'title'])
      ->fields('occ', ['last_post_timestamp'])
      ->condition('ocm.members_target_id', $this->account->id())
      ->orderBy('occ.last_post_timestamp', 'DESC');
    if ($number > 0) {
      $query->range(0, $number);
    }

    return $query->execute()->fetchAllKeyed();
  }

}
