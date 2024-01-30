<?php

namespace Drupal\go_back_history\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Block to go back browser history.
 *
 * @Block(
 *   id = "go_back_history_block",
 *   admin_label = @Translation("Go back history block"),
 *   category = @Translation("Go back history block"),
 * )
 */
class GoBackHistoryBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    return [
      '#theme' => 'block_go_back_history',
      '#button_value' => $this->t('Go back'),
      '#attached' => [
        'library' => [
          'go_back_history/go_back_history',
        ],
      ],
    ];
  }

}
