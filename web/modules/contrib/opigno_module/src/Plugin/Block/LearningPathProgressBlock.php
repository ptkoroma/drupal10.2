<?php

namespace Drupal\opigno_module\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\opigno_learning_path\Entity\LPStatus;


/**
 * Provides a 'LearningPathProgressBlock' block.
 *
 * @Block(
 *  id = "opigno_module_learning_path_progress_block",
 *  admin_label = @Translation("Learning path progress"),
 * )
 */
class LearningPathProgressBlock extends BlockBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function build() {
    if (!opigno_module_is_activity_route()) {
      return [];
    }

    $build = [];
    $home_link = NULL;
    $user = \Drupal::currentUser();
    $progress = 0;

    if ($gid = OpignoGroupContext::getCurrentGroupId()) {
      if ($group = \Drupal::entityTypeManager()->getStorage('group')->load($gid)) {
        $home_link = Link::createFromRoute(t('home'), 'entity.group.canonical', ['group' => $group->id()], ['attributes' => ['class' => ['w-100']]])->toRenderable();
        $home_link = \Drupal::service('renderer')->render($home_link);
      }
    }

    if ($user && isset($group)) {
      $progress_service = \Drupal::service('opigno_learning_path.progress');
      $progress = $progress_service->getProgressAjaxContainer($gid, $user->id(), '', 'module-page');
      $progress = \Drupal::service('renderer')->render($progress);
    }

    $build = [
      'home_link' => $home_link,
      'progress' => $progress,
      'ajax_conteiner' => TRUE,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
