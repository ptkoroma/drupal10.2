<?php

namespace Drupal\opigno_learning_path;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Link;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;

/**
 * The LP action links service.
 *
 * @package Drupal\opigno_learning_path
 */
class LearningPathGroupOperationsLinks implements TrustedCallbackInterface{

  use StringTranslationTrait;

  /**
   * Whether the social features enabled or not.
   *
   * @var bool
   */
  protected $isSocialsEnabled;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * LearningPathGroupOperationsLinks constructor.
   *
   * @param \Drupal\opigno_learning_path\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\opigno_learning_path\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    AccountInterface $account
  ) {
    $config_set = (bool) $config_factory->get('opigno_class.socialsettings')->get('enable_social_features') ?? FALSE;
    $this->isSocialsEnabled = $module_handler->moduleExists('opigno_social') && $config_set;
    $this->account = $account;
  }

  /**
   * Returns context Group operations links.
   */
  public function getLink() {
    return [];
  }

  /**
   * Prepare the render array to build the available LP action links.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to build links for.
   *
   * @return array
   *   The render array to build the available LP action links.
   */
  public function renderActionsDropdown(GroupInterface $group): array {
    $gid = (int) $group->id();
    $build = [
      '#theme' => 'opigno_learning_path_actions',
      '#actions' => [],
    ];

    $options = [
      'attributes' => [
        'class' => ['dropdown-item-text'],
      ],
    ];

    $forum_tid = $group->get('field_learning_path_forum')->getString();
    $actions = [];
    if ($forum_tid) {
      $actions['forum.page'] = [
        'title' => $this->t('Forum'),
        'params' => ['taxonomy_term' => $forum_tid],
      ];
    }

    $actions += [
      'tft.group' => [
        'title' => $this->t('Documents'),
        'params' => ['group' => $gid],
      ],
      'opigno_statistics.training' => [
        'title' => $this->t('Training statistics'),
        'params' => ['group' => $gid],
      ],
      'opigno_learning_path.training' => [
        'title' => $this->t('Results'),
        'params' => ['group' => $gid],
      ],
      'opigno_social.share_post_content' => [
        'title' => $this->t('Share'),
        'params' => ['group' => $gid],
      ],
      'entity.group.edit_form' => [
        'title' => $this->t('Edit'),
        'params' => ['group' => $gid],
      ],
    ];

    // Generate the list of actions, available for the current user.
    foreach ($actions as $route => $action) {
      $params = $action['params'] ?? [];
      $url = Url::fromRoute($route, $params, $options);
      if ($route !== 'opigno_social.share_post_content' && $url->access()) {
        $build['#actions'][] = Link::fromTextAndUrl($action['title'], $url);
        continue;
      }

      // Add extra settings for the sharing link.
      if (($route !== 'opigno_social.share_post_content' && !$url->access())
        || !$this->isSocialsEnabled
        || !($this->account->hasPermission('share any content') || $group->getMember($this->account))
      ) {
        continue;
      }

      $url = $url->toString();
      $share_options = $options;
      $share_options['attributes']['data-opigno-attachment-type'] = 'training';
      $share_options['attributes']['data-opigno-attachment-entity-type'] = 'group';
      $share_options['attributes']['data-opigno-post-attachment-id'] = $gid;
      $build['#actions'][] = Link::createFromRoute($action['title'], '<current>', [], $share_options);

      $build['#attached'] = [
        'library' => ['opigno_social/post_sharing'],
        'drupalSettings' => [
          'opignoSocial' => [
            'shareContentUrl' => $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url,
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['getLink'];
  }

}
