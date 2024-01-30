<?php

namespace Drupal\opigno_group_manager;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;

/**
 * This class manage the context when a user enters or exits a Learning Path.
 */
final class OpignoGroupContext {

  /**
   * The $_SESSION key for storing the last group NID that the user visited.
   */
  const GROUP_ID = 'group_id';

  /**
   * The current content ID key.
   */
  const CURRENT_CONTENT_ID = 'current_content_id';

  /**
   * The active link type key.
   */
  const ACTIVITY_LINK_TYPE = 'activity_link_type';

  /**
   * The static temporary id.
   *
   * @var numeric
   */
  private static $contextGroupId;

  /**
   * Start session for anonymous.
   */
  protected static function ensureSession() {
    if (\Drupal::currentUser()->isAnonymous()
      && !isset($_SESSION['session_started'])) {
      $_SESSION['session_started'] = TRUE;
      \Drupal::service('session_manager')->start();
    }
  }

  /**
   * Get the group ID.
   *
   * @return int|null
   *   The group context NID. Return Null if not exist.
   */
  public static function getCurrentGroupId() {
    self::ensureSession();

    /*
     * Priority of group context.
     * 1. Try to receive the group id from the route.
     * 2. Probably, the static and temporary context are redundant,
     *    but for backward compatibility, in some cases we use just to set
     *    the group id to the static variable.
     *  a. If the static is available it has a priority on the temp storage.
     *  b. If we have no any context to return the temp storage.
     */

    $group_id = \Drupal::routeMatch()->getParameter('group');
    if (!empty($group_id)) {
      if ($group_id instanceof EntityInterface) {
        $group_id = (int) $group_id->id();
      }
    }
    elseif (!($group_id = self::getStaticGroupId())) {
      /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
      $store = \Drupal::service('tempstore.private')
        ->get('opigno_group_manager');
      $group_id = $store->get(self::GROUP_ID);
    }
    return (!empty($group_id) && is_numeric($group_id)) ? (int) $group_id : NULL;
  }

  /**
   * Get the group current Content ID (cid).
   *
   * @return int
   *   The Content ID. Can be empty.
   */
  public static function getCurrentGroupContentId() {
    self::ensureSession();

    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = \Drupal::service('tempstore.private')
      ->get('opigno_group_manager');
    return $store->get(self::CURRENT_CONTENT_ID);
  }

  /**
   * Gets the context activity answer link type.
   *
   * @return string|null
   *   Context activity link type. Can be empty.
   */
  public static function getActivityLinkType() {
    self::ensureSession();

    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = \Drupal::service('tempstore.private')->get('opigno_group_manager');
    return $store->get(self::ACTIVITY_LINK_TYPE);
  }

  /**
   * Set the context Group ID.
   *
   * @param int|string $group_id
   *   Group ID.
   * @param bool $permanent
   *   Group ID.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public static function setGroupId($group_id, bool $permanent = TRUE) {
    self::ensureSession();

    if (!$permanent) {
      self::setStaticGroupId($group_id);
    }
    else {
      /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
      $store = \Drupal::service('tempstore.private')
        ->get('opigno_group_manager');
      $store->set(self::GROUP_ID, $group_id);
    }
  }

  /**
   * Getter for private static variable.
   */
  public static function getStaticGroupId() {
    return self::$contextGroupId;
  }

  /**
   * Setter for protected static variable.
   *
   * @param int|string $group_id
   *   The group ID.
   */
  public static function setStaticGroupId($group_id) {
    self::$contextGroupId = $group_id;
  }

  /**
   * Set the context Learning Path Content ID.
   *
   * This method will refresh the local actions as well.
   *
   * @param int $current_content_id
   *   Content ID.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public static function setCurrentContentId($current_content_id) {
    self::ensureSession();

    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = \Drupal::service('tempstore.private')
      ->get('opigno_group_manager');
    if ($store->get(self::CURRENT_CONTENT_ID) != $current_content_id) {
      $store->set(self::CURRENT_CONTENT_ID, $current_content_id);
      self::rebuildActions();
    }
  }

  /**
   * Gets the group content ID.
   *
   * @param int $gid
   *   The group ID to get the content for.
   * @param int $entity_id
   *   The content entity ID.
   * @param string $type
   *   The content type.
   *
   * @return int|null
   *   The group content ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getGroupContentId(int $gid, int $entity_id, string $type = 'ContentTypeModule'): ?int {
    $cid = \Drupal::entityTypeManager()->getStorage('opigno_group_content')
      ->getQuery()
      ->condition('group_id', $gid)
      ->condition('group_content_type_id', $type)
      ->condition('entity_id', $entity_id)
      ->execute();
    $cid = reset($cid);

    return $cid ? (int) $cid : NULL;
  }

  /**
   * Sets the context activity answer link type.
   *
   * @param string $activity_link_type
   *   Type of activity link.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public static function setActivityLinkType($activity_link_type) {
    self::ensureSession();

    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = \Drupal::service('tempstore.private')->get('opigno_group_manager');
    $store->set(self::ACTIVITY_LINK_TYPE, $activity_link_type);
  }

  /**
   * Remove all the context variables.
   *
   * Refresh the local actions as well.
   */
  public static function removeContext() {
    self::ensureSession();

    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = \Drupal::service('tempstore.private')
      ->get('opigno_group_manager');
    $store->delete(self::GROUP_ID);
    $store->delete(self::CURRENT_CONTENT_ID);
    $store->delete(self::ACTIVITY_LINK_TYPE);
    self::rebuildActions();
  }

  /**
   * Removes the context activity answer link type.
   *
   * Refresh the local actions as well.
   */
  public static function removeActivityLinkType() {
    self::ensureSession();

    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = \Drupal::service('tempstore.private')->get('opigno_group_manager');
    $store->delete(self::ACTIVITY_LINK_TYPE);
    self::rebuildActions();
  }

  /**
   * Refresh the local actions.
   */
  public static function rebuildActions() {
    // @todo This should be a global cache context if necessary.
    $bins = Cache::getBins();
    $bins['render']->invalidateAll();
  }

}
