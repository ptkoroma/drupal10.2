<?php

namespace Drupal\opigno_user_selection\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\group\GroupMembership;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Opigno User Selection routes.
 */
class OpignoUserSelectionController extends ControllerBase {

  /**
   * Service "request_stack" definition.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $currentRequest;

  /**
   * Service "group.membership_loader" definition.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembershipLoader;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack, GroupMembershipLoaderInterface $membership_loader) {
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->groupMembershipLoader = $membership_loader;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('group.membership_loader')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @opigno_deprecated
   */
  protected function getEntityField(EntityInterface $entity, string $field_name) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $user */
    if (
      $entity->hasField($field_name) &&
      !($field = $entity->get($field_name))->isEmpty()
    ) {
      if (
        /** @var \Drupal\Core\Entity\EntityInterface $child_entity */
      (($child_entity = $field->entity) instanceof EntityInterface)
      ) {
        return $child_entity;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @opigno_deprecated
   */
  public function getUserAvatar($user) {
    $default_image = \Drupal::service('file_url_generator')->generateAbsoluteString(\Drupal::service('extension.list.module')->getPath('opigno_user_selection') . '/assets/profile.svg');
    $image_style = ImageStyle::load('thumbnail');
    if (!($image_style instanceof ImageStyle)) {
      return $default_image;
    }
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $user */
    if (!($file = $this->getEntityField($user, 'user_picture'))) {
      return $default_image;
    }
    return $image_style->buildUrl($file->getFileUri());
  }

  /**
   * {@inheritdoc}
   *
   * @opigno_deprecated
   */
  public function getGroupImage($type, $group) {
    $default_image = \Drupal::service('file_url_generator')->generateAbsoluteString(\Drupal::service('extension.list.module')->getPath('opigno_user_selection') . '/assets/' . $type . '.svg');

    $image_style = ImageStyle::load('thumbnail');
    if (!($image_style instanceof ImageStyle)) {
      return $default_image;
    }
    $media = $this->getEntityField($group, 'field_learning_path_media_image');
    if (!($media instanceof Media)) {
      return $default_image;
    }
    $file = $this->getEntityField($media, 'field_media_image');
    if (!($file instanceof File)) {
      return $default_image;
    }

    return $image_style
      ->buildUrl($file->getFileUri());
  }

  /**
   * {@inheritdoc}
   */
  public function post($data = NULL) {
    $allowed_recipient = $this->getAllowedRecipients();
    $content = $this->currentRequest->getContent();
    if (!empty($content)) {
      // 2nd param to get as array.
      $data = json_decode($content, TRUE);
    }
    $data = array_intersect(array_keys($allowed_recipient), $data);
    $groups_id = [];
    $response_data = [];

    $meta = new CacheableMetadata();
    $meta->setCacheMaxAge(Cache::PERMANENT);

    /** @var \Drupal\user\Entity\User[] $users */
    $users = $this->entityTypeManager()
      ->getStorage('user')
      ->loadMultiple($data ?: []);

    $map = [
      'learning_path' => 'training',
      'opigno_class' => 'class',
    ];

    $response_data['users'] = (array_map(function ($user) use (&$groups_id, $meta, $map) {
      if (!$user->access('view')) {
        return FALSE;
      }
      $meta->addCacheableDependency($user);

      $memberships = $this->groupMembershipLoader->loadByUser($user);
      /** @var \Drupal\user\Entity\User $user */
      return [
        'id' => $user->id(),
        'name' => $user->getDisplayName(),
        'email' => $this->checkUserEmail($this->currentUser()) ? $user->getEmail() : '',
        'avatar' => $this->getUserAvatar($user),
        'member' => array_filter(array_map(function (GroupMembership $membership) use (&$groups_id, $map) {
          $group = $membership->getGroup();
          if (array_key_exists($group->bundle(), $map)) {
            $groups_id[] = ($gid = (int) $group->id());
            return $gid;
          }
          return NULL;
        }, $memberships)),
      ];
    }, $users));

    $response_data['users'] = array_filter($response_data['users'], function ($user) use ($data) {
      return in_array((int) $user['id'], $data);
    });

    /** @var \Drupal\group\Entity\Group[] $groups */
    $groups = $this->entityTypeManager()
      ->getStorage('group')
      ->loadMultiple($groups_id ?: []);
    $response_data['members'] = array_filter(array_map(function ($group) use ($meta, $map, $data) {

      $meta->addCacheableDependency($group);

      $memberships = $this->groupMembershipLoader->loadByGroup($group);
      /** @var \Drupal\group\Entity\Group $group */
      return $group->access('take') ? [
        "id" => $group->id(),
        "type" => $map[$group->bundle()],
        "info" => [
          "name" => $group->label(),
          'avatar' => $this->getGroupImage($map[$group->bundle()], $group),
        ],
        "key" => $map[$group->bundle()] . "_" . $group->id(),
        "loaded" => TRUE,
        "members" => array_filter(array_map(function (GroupMembership $membership) {
          return (int) ($membership->getGroupContent()
            ->getEntity() ? $membership->getGroupContent()
            ->getEntity()
            ->id() : 0);
        }, $memberships), function ($user) use ($data) {
          return in_array((int) $user, $data);
        }),
      ] : [];
    }, $groups));

    foreach ($response_data['users'] as $key => $user) {
      $response_data['users'][$key]['member'] = (array) array_values(array_intersect(array_keys($response_data['members']), $user['member']));
    }
    foreach ($response_data['members'] as $key => $member) {
      $response_data['members'][$key]['members'] = (array) array_values(array_intersect($data, $member['members']));
    }

    $response = new CacheableJsonResponse($response_data);
    $response->addCacheableDependency($meta);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function training($data = NULL) {
    $content = $this->currentRequest->getContent();
    if (!empty($content)) {
      // 2nd param to get as array.
      $data = json_decode($content, TRUE);
    }
    $response_data = [];

    $meta = new CacheableMetadata();
    $meta->setCacheMaxAge(Cache::PERMANENT);

    $map = [
      'learning_path' => 'training',
      'opigno_class' => 'class',
    ];

    /** @var \Drupal\group\Entity\Group[] $groups */
    $groups = $this->entityTypeManager()
      ->getStorage('group')
      ->loadMultiple($data ?: []);

    // Response_data key should be "users".
    $response_data['users'] = (array_map(function ($group) use ($meta, $map) {

      $meta->addCacheableDependency($group);

      /** @var \Drupal\group\Entity\Group $group */
      return [
        'id' => $group->id(),
        'name' => $group->label(),
        'email' => '',
        'avatar' => $this->getGroupImage($map[$group->bundle()], $group),
      ];
    }, $groups));

    $response = new CacheableJsonResponse($response_data);
    $response->addCacheableDependency($meta);
    return $response;
  }

  /**
   * Authenticated users only have access to this route.
   */
  public function checkAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated() && $account->hasPermission('access user profiles'));
  }

  /**
   * Check permissions to the e-mail view.
   */
  public function checkUserEmail(AccountInterface $account): bool {
    return $account->hasPermission('view user email addresses');
  }

  /**
   * Returns allowed recipients.
   *
   * @return bool|array|AccountInterface[]
   */
  private function getAllowedRecipients() {
    $mapped_recipients = [];
    // Get the groups, this allows to filter the available users.
    $show_all = AccessResult::allowedIfHasPermissions($this->currentUser(), [
      'add any members to calendar event',
      'message anyone regardless of groups',
    ], 'OR')->isAllowed();
    /** @var \Drupal\Core\Session\AccountInterface[] $allowed_recipients */
    $allowed_recipients = opigno_messaging_get_all_recipients($show_all);
    foreach ($allowed_recipients as $allowed_recipient) {
      $mapped_recipients[$allowed_recipient->id()] = $allowed_recipient;
    }
    return $mapped_recipients ?: [];
  }

}
