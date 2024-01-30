<?php

namespace Drupal\opigno_dashboard\Plugin\Block;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\user\UserInterface;

/**
 * The user statistics block.
 *
 * @Block(
 *  id = "opigno_user_stats_block",
 *  admin_label = @Translation("User statistics"),
 *  category = @Translation("Dashboard"),
 * )
 *
 * @package Drupal\opigno_dashboard\Plugin\Block
 */
class UserStatisticsBlock extends SiteHeaderBlock implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->user instanceof UserInterface) {
      return ['#cache' => ['max-age' => 0]];
    }

    if ($this->user->isAnonymous()) {
      return [
        '#cache' => ['max-age' => -1],
      ];
    }

    $stats = $this->statsManager->renderUserStatistics(7);

    return [
      '#theme' => 'opigno_dashboard_user_statistics_block',
      '#user_name' => $this->user->getDisplayName(),
      '#uid' => (int) $this->user->id(),
      '#user_picture' => $this->statsManager->getUserPicture($this->user, 'user_profile'),
      '#role' => $this->statsManager->getUserRole(),
      '#stats' => $stats,
      '#attached' => $stats['#attached'] ?? [],
    ];
  }

}
