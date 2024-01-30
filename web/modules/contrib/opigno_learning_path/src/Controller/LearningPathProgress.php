<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\opigno_learning_path\Progress;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Returns responses for ajax progress bar routes.
 */
class LearningPathProgress implements ContainerInjectionInterface {

  /**
   * The progress bar generator service.
   *
   * @var \Drupal\opigno_learning_path\Progress
   */
  protected $progressService;

  /**
   * Constructs a new LearningPathProgress object.
   *
   * @param Drupal\opigno_learning_path\Progres $progress_service
   *   The progress bar service.
   */
  public function __construct(Progress $progress_service) {
    $this->progressService = $progress_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_learning_path.progress')
    );
  }

  /**
   * Returns a html of progress bar.
   *
   * @param object $group
   *   Group entity.
   * @param object $account
   *   User entity.
   * @param int $latest_cert_date
   *   Latest certification date.
   * @param string $class
   *   identifier for progress bar.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function getHTML(Group $group, User $account, int $latest_cert_date, string $class) {
    $selector = '#progress-ajax-container-' . $group->id() . '-' . $account->id() . '-' . $latest_cert_date . '-' . $class;
    $content = $this->progressService->getProgressBuild($group->id(), $account->id(), $latest_cert_date, $class);
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

}
