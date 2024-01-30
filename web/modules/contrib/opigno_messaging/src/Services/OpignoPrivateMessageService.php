<?php

namespace Drupal\opigno_messaging\Services;

use Drupal\private_message\Service\PrivateMessageService;
use Drupal\user\UserInterface;

/**
 * Override PrivateMessageService, change the way to get the 1st user thread.
 *
 * @package Drupal\opigno_messaging\Services
 */
class OpignoPrivateMessageService extends PrivateMessageService {

  /**
   * Opigno private messages thread service.
   *
   * @var \Drupal\opigno_messaging\Services\OpignoMessageThread
   */
  protected $msgThreadService;

  /**
   * {@inheritdoc}
   */
  public function __construct(OpignoMessageThread $opigno_message_thread, ...$default) {
    parent::__construct(...$default);
    $this->msgThreadService = $opigno_message_thread;
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstThreadForUser(UserInterface $user) {
    $uid = (int) $user->id();
    $user_threads = $this->msgThreadService->getUserThreads($uid);
    $thread_id = array_shift($user_threads);

    return !$thread_id ? FALSE : $this->pmThreadManager->load($thread_id);
  }

}
