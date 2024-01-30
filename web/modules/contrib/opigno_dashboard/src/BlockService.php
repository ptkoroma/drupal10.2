<?php

namespace Drupal\opigno_dashboard;

use Drupal\block\Entity\Block;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The dashboard block manager service definition.
 */
class BlockService implements BlockServiceInterface {

  use StringTranslationTrait;

  /**
   * The block manager service.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * BlockService constructor.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   */
  public function __construct(
    BlockManagerInterface $block_manager,
    RendererInterface $renderer,
    AccountInterface $account,
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    Connection $database
  ) {
    $this->blockManager = $block_manager;
    $this->renderer = $renderer;
    $this->currentUser = $account;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllBlocks(): array {
    return $this->blockManager->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableBlocks(): array {
    $blocks = $this->getAllBlocks();
    $availables = $this->configFactory->get('opigno_dashboard.settings')->get('blocks');
    $account_roles = $this->currentUser->getRoles();

    foreach ($blocks as $key1 => &$block) {
      if (!isset($availables[$key1])
      || (isset($availables[$key1]) && !$availables[$key1]['available'])
      ) {
        unset($blocks[$key1]);
      }
      else {
        // Check access first.
        $block_real = Block::load($this->sanitizeId($key1));
        if (!$block_real) {
          // Try to load old version of block.
          $block_real = Block::load($this->sanitizeIdOld($key1));
        }

        $role_access = TRUE;
        if (!empty($block_real)) {
          $block_visibility = $block_real->getVisibility();

          if (isset($block_visibility['user_role']) && !empty($block_visibility['user_role'])) {
            $role_access = FALSE;

            foreach ($block_visibility['user_role']['roles'] as $block_role) {
              if (in_array($block_role, $account_roles)) {
                $role_access = TRUE;
              }
            }
          }
        }

        if (!$role_access) {
          unset($blocks[$key1]);
          continue;
        }

        foreach ($block as &$value) {
          if (is_object($value)) {
            $value = $value->render();
          }
        }

        $blocks[$key1]['id'] = $key1;

        unset(
            $blocks[$key1]['config_dependencies'],
            $blocks[$key1]['class'],
            $blocks[$key1]['provider'],
            $blocks[$key1]['category'],
            $blocks[$key1]['deriver'],
            $blocks[$key1]['context']
          );
      }
    }

    return array_values($blocks);
  }

  /**
   * {@inheritdoc}
   */
  public function getDashboardBlocksContents($need_render = TRUE): array {
    $ids = [];
    foreach ($this->getAvailableBlocks() as $block) {
      $ids[] = $block['id'];
    }

    $data = [
      'blocks' => [],
      'attachments' => [],
    ];
    foreach ($ids as $id) {
      try {
        $block = $this->blockManager->createInstance($id);
      }
      catch (PluginException $e) {
        watchdog_exception('opigno_dashboard_exception', $e);
        continue;
      }

      if (!$block instanceof BlockBase) {
        continue;
      }

      $render = $block->build();
      $data['blocks'][$id] = $need_render ? $this->renderer->renderRoot($render) : $render;
      $attachments = $render['#attached'] ?? [];
      $data['attachments'] = array_merge_recursive($data['attachments'], $attachments);
    }

    // Get only unique libraries.
    $data['attachments']['library'] = array_unique($data['attachments']['library']);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function createBlocksInstances(): void {
    $items = $this->getAvailableBlocks();
    $theme = $this->configFactory->get('opigno_dashboard.settings')->get('theme');

    foreach ($items as $item) {
      $id = $this->sanitizeId($item['id']);

      if (!Block::load($id)) {
        $settings = [
          'plugin' => $item['id'],
          'region' => 'content',
          'id' => $id,
          'theme' => $theme ?? $this->configFactory->get('system.theme')->get('default'),
          'label' => $this->t('Dashboard: @label', ['@label' => $item['admin_label'] ?? '']),
          'visibility' => [
            'request_path' => [
              'id' => 'request_path',
              'pages' => '<front>',
              'negate' => FALSE,
              'context_mapping' => [],
            ],
          ],
          'weight' => 0,
        ];

        $values = [];
        foreach (['region', 'id', 'theme', 'plugin', 'weight', 'visibility'] as $key) {
          $values[$key] = $settings[$key];
          // Remove extra values that do not belong in the settings array.
          unset($settings[$key]);
        }
        foreach ($values['visibility'] as $id => $visibility) {
          $values['visibility'][$id]['id'] = $id;
        }
        $values['settings'] = $settings;
        $block = Block::create($values);

        try {
          $block->save();
        }
        catch (EntityStorageException $e) {
          watchdog_exception('opigno_dashboard_exception', $e);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeId(string $id): string {
    return 'dashboard_' . str_replace([':', '-'], ['_', '_'], $id);
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeIdOld(string $id): string {
    return 'dashboard_' . str_replace(':', '_', $id);
  }

  /**
   * {@inheritdoc}
   */
  public function isSocialFeatureEnabled(): bool {
    $socials_enabled = $this->configFactory->get('opigno_class.socialsettings')->get('enable_social_features') ?? FALSE;
    return $this->moduleHandler->moduleExists('opigno_social') && $socials_enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultDashboardConfig(): string {
    // Return the default dashboard layout depending on whether Opigno Social
    // module is enabled or not.
    return $this->isSocialFeatureEnabled()
      ? '{"1":[{"admin_label":"User statistics","context_definitions":[],"id":"opigno_user_stats_block"},{"admin_label":"User connections","context_definitions":[],"id":"opigno_user_connections_block"},{"admin_label":"Latest active trainings","id":"views_block:latest_active_trainings-block","mandatory":false},{"admin_label":"Who is new","context_definitions":[],"id":"views_block:who_s_new-block_1"}],"2":[{"admin_label":"Social wall","context_definitions":[],"id":"opigno_social_wall_block"}],"3":[{"admin_label":"Opigno calendar block","id":"views_block:opigno_calendar-month_block","mandatory":false},{"admin_label":"Who is online","context_definitions":[],"id":"views_block:who_s_online-who_s_online_block"}]}'
      : '{"1":[{"admin_label":"Latest active trainings","id":"views_block:latest_active_trainings-block"}, {"admin_label":"Private Message: Dashboard - Last Threads","id":"views_block:private_message-block_dashboard"}],"2":[{"admin_label":"Opigno calendar block","id":"views_block:opigno_calendar-month_block"},{"admin_label":"Opigno Notifications: Dashboard - Unread Notifications","id":"views_block:opigno_notifications-block_unread_dashboard"},{"admin_label":"Recent comments","id":"views_block:comments_recent-block_1"}],"3":[]}';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLayoutId(): int {
    // Return 3-column layout in case if social features enabled, 2-column
    // otherwise.
    return $this->isSocialFeatureEnabled() ? 5 : 3;
  }

  /**
   * {@inheritdoc}
   */
  public function getPositioning($uid = NULL, bool $default = FALSE, bool $user_default = FALSE) {
    if (empty($uid)) {
      $uid = $this->currentUser->id();
    }

    $availables = $this->getAvailableBlocks();
    // Get default configuration.
    $config_default = $this->configFactory->get('opigno_dashboard.default.settings');
    $default_positions = json_decode($config_default->get('positions'), TRUE);
    $default_columns = $config_default->get('columns');

    if ($default) {
      $positions = $default_positions;
      $columns = $default_columns;
    }
    else {
      $query = $this->database->select('opigno_dashboard_positioning', 'p')
        ->fields('p', ['columns', 'positions'])
        ->condition('p.uid', $uid);

      $result = $query->execute()->fetchObject();
      $positions = FALSE;
      if (!empty($result->positions)) {
        $positions = json_decode($result->positions, TRUE);
      }
      $columns = $result->columns ?? $this->getDefaultLayoutId();
    }

    if (!$positions) {
      if (!empty($default_positions)) {
        $positions = $default_positions;
        $columns = $default_columns;
      }
      else {
        $positions = json_decode($this->getDefaultDashboardConfig(), TRUE);
        $columns = $this->getDefaultLayoutId();
      }
    }

    // Get mandatory blocks.
    $mandatory_blocks = $this->configFactory->get('opigno_dashboard.settings')->get('blocks');
    if (!empty($mandatory_blocks)) {
      $mandatory_blocks = array_filter($mandatory_blocks, function ($block) {
        return $block['available'] && $block['mandatory'];
      });
    }
    // Keep all mandatory blocks.
    $mandatory = $mandatory_blocks ?? [];

    // Remove blocks that are not available.
    $available_keys = [];
    foreach ($availables as $available) {
      $available_id = $available['id'] ?? '';
      if ($available_id) {
        $available_keys[$available_id] = $available_id;
      }
    }
    foreach ($positions as $key1 => $column) {
      foreach ($column as $key2 => $row) {
        // Make the title translatable.
        $label = $positions[$key1][$key2]['admin_label'] ?? '';
        if ($label) {
          $positions[$key1][$key2]['admin_label'] = $this->t($label);
        }
        if (!isset($row['id'])) {
          continue;
        }
        if (!in_array($row['id'], $available_keys)) {
          unset($positions[$key1][$key2]);
          continue;
        }
        // Filter unused mandatory blocks.
        if (!empty($mandatory_blocks) && isset($mandatory_blocks[$row['id']])) {
          unset($mandatory_blocks[$row['id']]);
        }
        // Add mandatory property to positions blocks.
        $positions[$key1][$key2]['mandatory'] = $mandatory && array_key_exists($row['id'], $mandatory);
      }
    }

    // Remove block already used.
    foreach ($availables as $key => $value) {
      foreach ($positions as $column) {
        foreach ($column as $row) {
          if (isset($row['id']) && isset($value['id']) && ($row['id'] == $value['id'])) {
            unset($availables[$key]);
          }
        }
      }
      // Save mandatory blocks key from "availables" array.
      if (!empty($mandatory_blocks) && array_key_exists($value['id'], $mandatory_blocks)) {
        $mandatory_blocks[$value['id']]['availables_key'] = $key;
      }
    }

    if (!$user_default) {
      $available_values = array_values($availables);
      $entities = array_merge([$available_values], $positions);
      $positions = $entities ?: array_merge([$available_values], [[], [], []]);
    }

    // Add unused mandatory blocks.
    if (!empty($mandatory_blocks)) {
      foreach ($mandatory_blocks as $id => $mandatory_block) {
        if (!empty($mandatory_block['availables_key'])) {
          array_unshift($positions[1], [
            'admin_label' => $availables[$mandatory_block['availables_key']]['admin_label'] ?? '',
            'id' => $id,
            'mandatory' => TRUE,
          ]);
        }
      }
    }

    $columns = $columns ?? $this->getDefaultLayoutId();

    $this->clearEmptyPositions($positions, $available_keys);
    if ($default) {
      return [
        'positions' => $positions,
        'columns' => $columns,
      ];
    }
    else {
      return new JsonResponse([
        'positions' => $positions,
        'columns' => $columns,
      ]);
    }
  }

  /**
   * Clear empty positions.
   *
   * @param array $positions
   *   Blocks positioning.
   * @param array $available_keys
   *   The list of available keys.
   */
  private function clearEmptyPositions(array &$positions, array $available_keys) {
    foreach ($positions as $c_key => $columns) {
      if (!is_array($columns)) {
        continue;
      }
      foreach ($columns as $key => $position) {
        if (!is_array($columns)) {
          continue;
        }
        // Unset empty arrays and removed blocks.
        if (!isset($position['id']) || (isset($position['id']) && !in_array($position['id'], $available_keys))) {
          unset($positions[$c_key][$key]);
        }
      }
      // Reset array keys.
      $positions[$c_key] = array_values($positions[$c_key]);
    }
  }

}
