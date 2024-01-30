<?php

namespace Drupal\opigno_messaging\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to scroll to the last message.
 *
 * @package Drupal\opigno_messaging\Ajax
 */
class OpignoScrollToLastMessage implements CommandInterface {

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return ['command' => 'opignoScrollToLastMessage'];
  }

}
