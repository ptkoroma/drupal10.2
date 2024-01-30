<?php

namespace Drupal\opigno_dashboard\Plugin\Block;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\opigno_notification\Services\OpignoNotificationManager;
use Drupal\opigno_statistics\Services\UserStatisticsManager;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The site header block.
 *
 * @Block(
 *  id = "opigno_site_header_block",
 *  admin_label = @Translation("Opigno Site header block"),
 *  category = @Translation("Opigno"),
 * )
 *
 * @package Drupal\opigno_dashboard\Plugin\Block
 */
class SiteHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $user = NULL;

  /**
   * If the current page is a user page.
   *
   * @var bool
   */
  protected $isUserPage;

  /**
   * The user statistics manager.
   *
   * @var \Drupal\opigno_statistics\Services\UserStatisticsManager
   */
  protected $statsManager;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * Notifications manager service.
   *
   * @var \Drupal\opigno_notification\Services\OpignoNotificationManager
   */
  protected $notificationsManager;

  /**
   * The private messages manager service.
   *
   * @var \Drupal\private_message\Service\PrivateMessageServiceInterface
   */
  protected $pmService;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $account, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, UserStatisticsManager $user_stats_manager, MenuLinkTreeInterface $menu_tree, OpignoNotificationManager $notification_manager, PrivateMessageServiceInterface $pm_service, ...$default) {
    parent::__construct(...$default);
    $uid = (int) $account->id();
    $this->isUserPage = $route_match->getRouteName() === 'entity.user.canonical' && (int) $route_match->getRawParameter('user') === $uid;
    $this->statsManager = $user_stats_manager;
    $this->menuTree = $menu_tree;
    $this->notificationsManager = $notification_manager;
    $this->pmService = $pm_service;

    try {
      $this->user = $entity_type_manager->getStorage('user')->load($uid);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_dashboard_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('opigno_statistics.user_stats_manager'),
      $container->get('menu.link_tree'),
      $container->get('opigno_notification.manager'),
      $container->get('private_message.service'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Don't cache if the user can't be loaded.
    if (!$this->user instanceof UserInterface) {
      return [
        '#cache' => ['max-age' => 0],
      ];
    }

    // Get the logo path and the main menu.
    $role = $this->statsManager->getUserRole();
    $logo = theme_get_setting('logo.url');
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters('main');
    $tree = $this->menuTree->load('main', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $menu = $this->menuTree->transform($tree, $manipulators);

    // Notifications.
    try {
      $notifications = $this->notificationsManager->getUserHeaderNotifications();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      watchdog_exception('opigno_dashboard_exception', $e);
      $notifications = [];
    }

    return [
      '#theme' => 'opigno_site_header',
      '#logo' => $logo,
      '#menu' => $this->menuTree->build($menu),
      '#is_anonymous' => $this->user->isAnonymous(),
      '#is_user_page' => $this->isUserPage,
      '#user_name' => $this->user->getDisplayName(),
      '#user_url' => Url::fromRoute('entity.user.canonical', ['user' => (int) $this->user->id()])->toString(),
      '#user_picture' => UserStatisticsManager::getUserPicture($this->user),
      '#notifications_count' => count($notifications),
      '#notifications' => $this->notificationsManager->renderUserHeaderNotifications($notifications),
      '#messages_count' => $this->pmService->getUnreadThreadCount(),
      '#dropdown_menu' => $this->buildUserDropdownMenu($role),
      '#cache' => [
        'contexts' => $this->getCacheContexts(),
        'tags' => $this->getCacheTags(),
      ],
      '#attached' => [
        'library' => ['opigno_notification/opigno_notification'],
        'drupalSettings' => [
          'opignoNotifications' => [
            'updateUrl' => Url::fromRoute('opigno_notification.get_messages')->toString(),
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = Cache::mergeContexts(parent::getCacheContexts(), [
      'url.path.is_current_user_page',
      'url.path',
    ]);
    if ($this->user instanceof UserInterface) {
      $contexts = Cache::mergeContexts($contexts, ['user']);
    }

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [
      'opigno_notification_list',
      'private_message_list',
    ]);
  }

  /**
   * Prepare the user dropdown menu.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $role
   *   The user role.
   *
   * @return array
   *   The array to build the user dropdown menu.
   */
  private function buildUserDropdownMenu(TranslatableMarkup $role): array {
    if (!$this->user instanceof UserInterface) {
      return [];
    }

    return [
      'name' => Link::createFromRoute($this->user->getDisplayName(), 'entity.user.canonical', ['user' => (int) $this->user->id()]),
      'role' => $role,
      'is_admin' => $this->user->hasPermission('access administration pages'),
      'links' => [
        'help' => [
          'title' => $this->t('Help'),
          'path' => 'https://www.opigno.org/contact',
          'external' => TRUE,
          'icon_class' => 'fi-rr-interrogation',
        ],
        'review' => [
          'title' => $this->t('Review Opigno'),
          'path' => 'https://reviews.capterra.com/new/135113?utm_source=vp&utm_medium=none&utm_term=&utm_content=&utm_campaign=vendor_request',
          'external' => TRUE,
          'icon_class' => 'fi-rr-comment',
        ],
        'logout' => [
          'title' => $this->t('Logout'),
          'path' => Url::fromRoute('user.logout')->toString(),
          'icon_class' => 'fi-rr-sign-out',
        ],
      ],
    ];
  }

}
