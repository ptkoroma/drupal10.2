<?php

namespace Drupal\opigno_like\Services;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opigno_like\Entity\OpignoLikeInterface;

/**
 * The "Opigno like" entity manager service.
 *
 * @package Drupal\opigno_like\Services
 */
class OpignoLikeManager {

  use StringTranslationTrait;

  /**
   * The cache prefix to store the list of users who had liked the entity.
   */
  const ENTITY_LIKERS_CACHE_PREFIX = 'opigno_entity_likers_';

  /**
   * The cache prefix to store the list of entity likes.
   */
  const ENTITY_LIKES_CACHE_PREFIX = 'opigno_entity_likes_';

  /**
   * Opigno likes storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $storage = NULL;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user account.
   *
   * @var int
   */
  protected $currentUserId;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * OpignoLikeManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user account.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    AccountInterface $user,
    CacheBackendInterface $cache
  ) {
    $this->database = $database;
    $this->currentUserId = (int) $user->id();
    $this->cache = $cache;

    try {
      $this->storage = $entity_type_manager->getStorage('opigno_like');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_like_exception', $e);
    }
  }

  /**
   * Check if the current user had liked the entity before.
   *
   * @param int $eid
   *   The entity ID to check the like for.
   * @param string $type
   *   The entity type.
   *
   * @return \Drupal\opigno_like\Entity\OpignoLikeInterface|null
   *   Loaded "Opigno like" entity if exists, NULL otherwise.
   */
  public function getUserLike(int $eid, string $type): ?OpignoLikeInterface {
    if (!$this->storage instanceof EntityStorageInterface || !$eid || !$type) {
      return NULL;
    }

    $like = $this->storage->loadByProperties([
      'uid' => $this->currentUserId,
      'entity_id' => $eid,
      'type' => $type,
    ]);
    $like = reset($like);

    return $like instanceof OpignoLikeInterface ? $like : NULL;
  }

  /**
   * Generate the entity like/dislike link.
   *
   * @param int $eid
   *   The entity ID to get likes for.
   * @param string $type
   *   The entity type.
   *
   * @return array
   *   The render array to display the entity like/dislike link.
   */
  public function getEntityLikeLink(int $eid, string $type = 'opigno_post'): array {
    $like = $this->getUserLike($eid, $type);
    $params = [
      'eid' => $eid,
      'type' => $type,
    ];
    $options = [
      'attributes' => [
        'id' => "opigno-like-$eid",
        'class' => ['comment-item__actions--like', 'use-ajax'],
      ],
    ];

    if ($like instanceof OpignoLikeInterface) {
      $options['attributes']['class'][] = 'active';
    }

    $title = Markup::create('<i class="fi fi-rr-thumbs-up"></i>' . $this->t('Like', [], ['context' => 'Opigno: Like']));

    return Link::createFromRoute($title, 'opigno_like.like_action', $params, $options)->toRenderable();
  }

  /**
   * Generate the likes count link.
   *
   * @param int $eid
   *   The entity ID to get likes for.
   * @param string $type
   *   The entity type.
   *
   * @return array
   *   The render array to display the likes link with the count.
   */
  public function getLikersCountLink(int $eid, string $type = 'opigno_post'): array {
    $likers = $this->getEntityLikers($eid, $type);
    $count = count($likers);
    // Prepare the link to get likers.
    $title = Markup::create($count . '<i class="fi fi-rr-thumbs-up icon-like"></i>');
    $class = ['comment-item__number--like', 'use-ajax'];

    // Hide the link if there are no likes.
    if (!$count) {
      $class[] = 'hidden';
    }

    $options = [
      'attributes' => [
        'id' => "opigno-likes-count-$type-$eid",
        'class' => $class,
      ],
    ];
    $params = [
      'eid' => $eid,
      'type' => $type,
    ];

    return Link::createFromRoute($title, 'opigno_like.get_likers', $params, $options)->toRenderable();
  }

  /**
   * Get the list of users who had liked the given entity.
   *
   * @param int $eid
   *   The entity ID to get likers for.
   * @param string $type
   *   The entity type.
   *
   * @return array
   *   The list of users who had liked the given entity.
   */
  public function getEntityLikers(int $eid, string $type): array {
    $result = [];
    if (!$eid || !$type) {
      return $result;
    }

    // Try to get the result from cache first.
    $cid = static::ENTITY_LIKERS_CACHE_PREFIX . $type . $eid;
    $cached = $this->cache->get($cid)->data ?? [];
    if ($cached && is_array($cached)) {
      return $cached;
    }

    // Get the list of users from the database.
    $users = $this->database->select('opigno_like', 'ol')
      ->fields('ol', ['uid'])
      ->condition('ol.entity_id', $eid)
      ->condition('ol.type', $type)
      ->execute()
      ->fetchCol();

    if (is_array($users)) {
      $this->cache->set($cid, $users, Cache::PERMANENT, [$cid]);
      $result = $users;
    }

    return $result;
  }

  /**
   * Get the list of loaded entity likes.
   *
   * @param int $eid
   *   The entity ID to get likes for.
   * @param string $type
   *   The entity type.
   * @param bool $load
   *   Should likes be loaded or not.
   *
   * @return array
   *   The list of loaded entity likes.
   */
  public function getEntityLikes(int $eid, string $type = 'opigno_post', bool $load = TRUE): array {
    $result = [];
    if (!$eid || !$type) {
      return $result;
    }

    // Try to get the result from cache first.
    $cid = static::ENTITY_LIKES_CACHE_PREFIX . $type . $eid;
    $cached = $this->cache->get($cid)->data ?? [];
    if ($cached && is_array($cached)) {
      // Load cached likes if needed.
      return $load && $this->storage instanceof EntityStorageInterface ? $this->storage->loadMultiple($cached) : $cached;
    }

    // Get the list of likes from the database.
    if (!$this->storage instanceof EntityStorageInterface) {
      return $result;
    }

    $result = $this->storage->getQuery()
      ->condition('entity_id', $eid)
      ->condition('type', $type)
      ->execute();

    if (!$result || !is_array($result)) {
      return [];
    }

    // Store the result in the cache, load entities if needed.
    $this->cache->set($cid, $result, Cache::PERMANENT, [$cid]);
    if ($load) {
      $result = $this->storage->loadMultiple($result);
    }

    return $result;
  }

  /**
   * Delete all entity likes.
   *
   * @param int $eid
   *   The entity ID to delete likes for.
   * @param string $type
   *   The entity type.
   */
  public function deleteEntityLikes(int $eid, string $type = 'opigno_post'): void {
    $likes = $this->getEntityLikes($eid, $type);
    if (!$likes || !$this->storage instanceof EntityStorageInterface) {
      return;
    }

    try {
      $this->storage->delete($likes);
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_like_exception', $e);
      return;
    }

    // Flush caches.
    $this->cache->deleteMultiple([
      static::ENTITY_LIKES_CACHE_PREFIX . $type . $eid,
      static::ENTITY_LIKERS_CACHE_PREFIX . $type . $eid,
    ]);
  }

}
