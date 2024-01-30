<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\GroupMembership;
use Drupal\user\UserInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the actions related to LP membership.
 */
class LearningPathMembershipController extends ControllerBase {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The group content storage.
   *
   * @var \Drupal\group\Entity\Storage\GroupContentStorageInterface
   */
  protected $groupContentStorage;

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheInvalidator;

  /**
   * The mail plugin manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * LearningPathMembershipController constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The DB connection service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_invalidator
   *   The cache tags invalidator service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail plugin manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    Connection $connection,
    FormBuilderInterface $form_builder,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    RouteMatchInterface $route_match,
    CacheTagsInvalidatorInterface $cache_invalidator,
    ConfigFactoryInterface $config_factory,
    MailManagerInterface $mail_manager
  ) {
    $this->connection = $connection;
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
    $this->routeMatch = $route_match;
    $this->userStorage = $entity_type_manager->getStorage('user');
    $this->groupContentStorage = $entity_type_manager->getStorage('group_content');
    $this->cacheInvalidator = $cache_invalidator;
    $this->configFactory = $config_factory;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('current_route_match'),
      $container->get('cache_tags.invalidator'),
      $container->get('config.factory'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * Callback for opening the create members modal form.
   */
  public function createMembersFormModal() {
    $form = $this->formBuilder->getForm('Drupal\opigno_learning_path\Form\LearningPathCreateMemberForm');
    $command = new OpenModalDialogCommand($this->t('Create new members'), $form);

    $response = new AjaxResponse();
    $response->addCommand($command);
    return $response;
  }

  /**
   * Callback for opening the create members modal form.
   */
  public function createUserFormModal() {
    $form = $this->formBuilder->getForm('Drupal\opigno_learning_path\Form\LearningPathCreateUserForm');
    $command = new OpenModalDialogCommand($this->t('2/2 create a new user'), $form);

    $response = new AjaxResponse();
    $response->addCommand($command);
    return $response;
  }

  /**
   * Callback for opening the create members modal form.
   */
  public function createClassFormModal() {
    $form = $this->formBuilder->getForm('Drupal\opigno_learning_path\Form\LearningPathCreateClassForm');
    $command = new OpenModalDialogCommand($this->t('Create a new class'), $form);

    $response = new AjaxResponse();
    $response->addCommand($command);
    return $response;
  }

  /**
   * Returns response for the autocompletion.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group.
   *
   * @return array
   *   The autocomplete suggestions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addUserToTrainingAutocompleteSelect(Group $group): array {
    $is_class = $group->getGroupType()->id() == 'opigno_class';
    // Get all available users ids with access check for the current user.
    $user_ids = $this->userStorage->getQuery()
      ->accessCheck()
      ->condition('uid', 0, '<>')
      ->sort('name')
      ->execute();
    $users = $this->userStorage->loadMultiple($user_ids);

    $options = array_reduce($users, function ($carry, $user) {
      $id = $user->id();
      $name = $user->getDisplayName();
      $carry['user_' . $id] = [
        'id' => 'user_' . $id,
        'type' => 'user',
        'value' => "$name (User #$id)",
        'label' => "$name (User #$id)",
      ];
      return $carry;
    }, []);

    $selected = array_filter(array_map(function (GroupMembership $membership) {
      return $membership->getUser() ? 'user_' . $membership->getUser()->id() : NULL;
    }, $group->getMembers()));

    // Check if we are on the class manage page, we can skip the following code.
    if (!$is_class) {
      // Get all available classes ids with access check for the current user.
      $group_storage = $this->entityTypeManager->getStorage('group');
      $class_ids = $group_storage->getQuery()
        ->accessCheck()
        ->condition('type', 'opigno_class')
        ->sort('label')
        ->execute();

      $classes = $group_storage->loadMultiple($class_ids);
      $is_class_added = $group->getContent('subgroup:opigno_class', []);
      // Check if class already added.
      $is_class_added = array_map(function ($item) {
        return $item->getEntity()->id();
      }, $is_class_added);
      $is_class_added = $is_class_added ? array_values($is_class_added) : [];

      $is_class_added = array_map(function ($class) {
        return 'class_' . $class;
      }, $is_class_added);

      $selected = array_merge($selected, $is_class_added);
      $classes = array_reduce($classes, function ($carry, $class) {
        $id = $class->id();
        $name = $class->label();
        $carry['class_' . $id] = [
          'id' => 'class_' . $id,
          'type' => 'group',
          'value' => "$name (Group #$id)",
          'label' => "$name (Group #$id)",
        ];
        return $carry;
      }, []);
      $options = array_merge((array) $options, (array) $classes);
    }

    return [$options, $selected];
  }

  /**
   * Returns response for the autocompletion.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function addUserToClassAutocomplete(Group $group): JsonResponse {
    $matches = [];
    $string = $this->request->query->get('q');
    if (!$string) {
      return new JsonResponse($matches);
    }

    $like_string = '%' . $this->connection->escapeLike($string) . '%';
    // Find users by email or name.
    $query = $this->userStorage->getQuery()
      ->condition('uid', 0, '<>')
      ->condition('name', $like_string, 'LIKE')
      ->sort('name')
      ->range(0, 20);
    $uids = $query->execute();

    $count = count($uids);

    if ($count < 20) {
      $range = 20 - $count;
      $query = $this->userStorage->getQuery()
        ->condition('uid', 0, '<>')
        ->condition('mail', $like_string, 'LIKE')
        ->sort('name')
        ->range(0, $range);
      $uids = array_merge($uids, $query->execute());
    }

    $users = $this->userStorage->loadMultiple($uids);

    /** @var \Drupal\user\Entity\User $user */
    foreach ($users as $user) {
      $id = $user->id();
      $name = $user->getDisplayName();

      $matches[] = [
        'value' => "$name ($id)",
        'label' => "$name ($id)",
        'type' => 'user',
        'id' => 'user_' . $id,
      ];
    }

    return new JsonResponse($matches);
  }

  /**
   * Returns users of current group for the autocompletion.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function findUsersInGroupAutocomplete(): JsonResponse {
    $matches = [];
    $string = $this->request->query->get('q');

    if (!$string) {
      return new JsonResponse($matches);
    }

    $like_string = '%' . $this->connection->escapeLike($string) . '%';
    /** @var \Drupal\group\Entity\Group $curr_group */
    $curr_group = $this->routeMatch->getParameter('group');

    // Find users by email or name.
    $query = $this->userStorage->getQuery()
      ->condition('uid', 0, '<>');

    $cond_group = $query
      ->orConditionGroup()
      ->condition('mail', $like_string, 'LIKE')
      ->condition('name', $like_string, 'LIKE');

    $query = $query
      ->condition($cond_group)
      ->sort('name');

    $uids = $query->execute();
    $users = $this->userStorage->loadMultiple($uids);

    /** @var \Drupal\user\Entity\User $user */
    foreach ($users as $user) {
      $id = $user->id();
      $name = $user->getDisplayName();

      // Remove users that are not members of current group.
      if ($curr_group->getMember($user) === FALSE) {
        continue;
      }

      $matches[] = [
        'value' => "$name ($id)",
        'label' => $name,
        'id' => $id,
      ];
    }

    return new JsonResponse($matches);
  }

  /**
   * Ajax callback for searching user in a training classes.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param string $class_id
   *   Class group ID.
   * @param string $uid
   *   User ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax command or empty.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function findGroupMember(Group $group, $class_id, $uid): AjaxResponse {
    $response = new AjaxResponse();

    if ($class_id === '0') {
      $content_types = [
        'group_content_type_27efa0097d858',
        'group_content_type_af9d804582e19',
        'learning_path-group_membership',
      ];

      $group_content_ids = $this->groupContentStorage->getQuery()
        ->condition('gid', $group->id())
        ->condition('type', $content_types, 'IN')
        ->sort('changed', 'DESC')
        ->execute();
      $content = $this->groupContentStorage->loadMultiple($group_content_ids);

      $users = [];
      $classes = [];

      /** @var \Drupal\group\Entity\GroupContentInterface $item */
      foreach ($content as $item) {
        $entity = $item->getEntity();
        if ($entity === NULL) {
          continue;
        }

        $type = $entity->getEntityTypeId();
        $bundle = $entity->bundle();

        if ($type === 'user') {
          $users[$entity->id()] = [
            'group content' => $item,
            'entity' => $entity,
          ];
        }
        elseif ($type === 'group' && $bundle === 'opigno_class') {
          $classes[$entity->id()] = [
            'group content' => $item,
            'entity' => $entity,
          ];
        }
      }

      if ($classes) {
        foreach ($classes as $class) {
          $view_id = 'opigno_group_members_table';
          $display = 'group_members_block';
          $args = [$class['entity']->id()];

          $members_view = Views::getView($view_id);
          if (is_object($members_view)) {
            $members_view->storage->set('group_members', array_keys($users));
            $members_view->setArguments($args);
            $members_view->setDisplay($display);
            $members_view->setItemsPerPage(0);
            $members_view->execute();
            if (!empty($members_view->result)) {
              foreach ($members_view->result as $key => $item) {
                $member = $item->_entity->getEntity();
                if ($member->id() == $uid) {
                  $display_default = $members_view->storage->getDisplay('default');
                  $per_page = $display_default["display_options"]["pager"]["options"]["items_per_page"];
                  $current_page = intdiv($key, $per_page);
                  $class_id = $class['entity']->id();
                  break 2;
                }
              }
            }
          }
        }

        if (isset($current_page)) {
          $selector = '#class-' . $class_id . ' .views-element-container';
          $members_view = Views::getView($view_id);
          if (is_object($members_view)) {
            $members_view->storage->set('group_members', array_keys($users));
            $members_view->setArguments($args);
            $members_view->setDisplay($display);
            $members_view->setCurrentPage($current_page);
            $members_view->preExecute();
            $members_view->execute();
            $members_view_renderable = $members_view->buildRenderable($display, $args);

            $response->addCommand(new ReplaceCommand($selector, $members_view_renderable));
          }
        }
      }
    }

    return $response;
  }

  /**
   * Ajax callback used in opigno_learning_path_member_overview.js.
   *
   * Removes member from learning path.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function deleteUser() {
    /** @var \Drupal\group\Entity\Group $group */
    $group = $this->routeMatch->getParameter('group');
    if (!isset($group)) {
      throw new NotFoundHttpException();
    }

    $uid = $this->request->query->get('user_id');
    $user = $this->userStorage->load($uid);
    if (!$user instanceof UserInterface) {
      throw new NotFoundHttpException();
    }

    $member = $group->getMember($user);
    if (!isset($member)) {
      throw new NotFoundHttpException();
    }

    $group->removeMember($user);
    return new JsonResponse();
  }

  /**
   * Ajax callback used in opigno_learning_path_member_overview.js.
   *
   * Removes class from learning path.
   */
  public function deleteClass() {
    /** @var \Drupal\group\Entity\Group $group */
    $group = $this->routeMatch->getParameter('group');

    $class_id = $this->request->query->get('class_id');
    $class = Group::load($class_id);

    if (!isset($group) || !isset($class)) {
      throw new NotFoundHttpException();
    }

    $content = $group->getContent();
    /** @var \Drupal\group\Entity\GroupContentInterface $item */
    foreach ($content as $item) {
      $entity = $item->getEntity();
      $type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();

      if ($type === 'group' && $bundle === 'opigno_class'
        && $entity->id() === $class->id()) {
        $item->delete();
        break;
      }
    }

    return new JsonResponse();
  }

  /**
   * Ajax callback used in opigno_learning_path_member_overview.js.
   *
   * Toggles user role in learning path.
   */
  public function toggleRole() {
    /** @var \Drupal\group\Entity\Group $group */
    $group = $this->routeMatch->getParameter('group');
    $query = $this->request->query;
    $uid = $query->get('uid');
    $user = $this->userStorage->load($uid);
    $role = $query->get('role');
    if (!isset($group) || !$user instanceof UserInterface || !isset($role)) {
      throw new NotFoundHttpException();
    }

    $member = $group->getMember($user);
    if (!isset($member)) {
      throw new NotFoundHttpException();
    }

    $group_content = $member->getGroupContent();
    $values = $group_content->get('group_roles')->getValue();
    $found = FALSE;

    foreach ($values as $index => $value) {
      if ($value['target_id'] === $role) {
        $found = TRUE;
        unset($values[$index]);
        break;
      }
    }

    if ($found === FALSE) {
      $values[] = ['target_id' => $role];
    }

    $group_content->set('group_roles', $values);
    $group_content->save();

    return new JsonResponse();
  }

  /**
   * Ajax callback used in opigno_learning_path_member_overview.js.
   *
   * Validates user role in learning path.
   */
  public function validate() {
    /** @var \Drupal\group\Entity\Group $group */
    $group = $this->routeMatch->getParameter('group');
    $gid = $group->id();

    $uid = $this->request->query->get('user_id');
    $user = $this->userStorage->load($uid);

    if (!isset($group) || !$user instanceof UserInterface) {
      throw new NotFoundHttpException();
    }

    $member = $group->getMember($user);
    if (!isset($member)) {
      throw new NotFoundHttpException();
    }

    $group_content = $member->getGroupContent();

    $query = $this->connection
      ->merge('opigno_learning_path_group_user_status')
      ->key('mid', $group_content->id())
      ->insertFields([
        'mid' => $group_content->id(),
        'uid' => $uid,
        'gid' => $gid,
        'status' => 1,
        'message' => '',
      ])
      ->updateFields([
        'uid' => $uid,
        'gid' => $gid,
        'status' => 1,
        'message' => '',
      ]);
    $result = $query->execute();

    if ($result) {
      // Invalidate cache.
      $tags = $member->getCacheTags();
      $this->cacheInvalidator->invalidateTags($tags);

      // Set notification.
      $message = $this->t('Enrollment validated to a new training "@name"', ['@name' => $group->label()]);
      $url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()])->toString();
      opigno_set_message($uid, $message, $url);

      $config = $this->config('opigno_learning_path.learning_path_settings');
      $send_to_users = $config->get('opigno_learning_path_notify_users');
      if ($send_to_users) {
        // Send email.
        $module = 'opigno_learning_path';
        $key = 'opigno_learning_path_membership_validated';
        $email = $user->getEmail();
        $lang = $user->getPreferredLangcode();
        $params = [];
        $params['subject'] = $this->t('Your membership to the training @training has been approved', [
          '@training' => $group->label(),
        ]);
        $site_config = $this->config('system.site');
        $link = $group->toUrl()->setAbsolute()->toString();
        $args = [
          '@username' => $user->getDisplayName(),
          '@training' => $group->label(),
          ':link' => $link,
          '@link_text' => $link,
          '@platform' => $site_config->get('name'),
        ];
        $params['message'] = $this->t('Dear @username

Your membership to the training @training has been approved. You can now access this training at: <a href=":link">@link_text</a>

@platform', $args);

        $this->mailManager->mail($module, $key, $email, $lang, $params);
      }
    }

    return new JsonResponse();
  }

}
