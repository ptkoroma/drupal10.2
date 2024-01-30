<?php

namespace Drupal\opigno_learning_path\Plugin\views\field;

use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_learning_path\Entity\LatestActivity;
use Drupal\opigno_learning_path\Progress;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to output user progress for current LP.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_learning_path_progress")
 */
class OpignoLearningPathProgress extends FieldPluginBase {

  /**
   * The current user ID.
   *
   * @var int
   */
  protected $uid;

  /**
   * Opigno learning path progress service.
   *
   * @var \Drupal\opigno_learning_path\Progress
   */
  protected $progress;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $user, Progress $progress, ...$default) {
    parent::__construct(...$default);
    $this->uid = (int) $user->id();
    $this->progress = $progress;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_user'),
      $container->get('opigno_learning_path.progress'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function render(ResultRow $values) {
    // Get an entity object.
    $entity = $values->_entity;
    $group = $entity instanceof LatestActivity ? $entity->getTraining() : $entity;

    return !is_null($group) ? $this->progress->getProgressBuild($group->id(), $this->uid, '', 'full') : '';
  }

}
