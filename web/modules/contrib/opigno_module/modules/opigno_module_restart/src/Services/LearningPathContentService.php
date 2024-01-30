<?php

namespace Drupal\opigno_module_restart\Services;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_learning_path\Services\LearningPathContentService as ContentServiceBase;
use Drupal\opigno_module\Entity\OpignoModuleInterface;

/**
 * Overrides the LP content service.
 *
 * @package Drupal\opigno_module_restart\Services
 */
class LearningPathContentService extends ContentServiceBase {

  /**
   * The module restart manager service.
   *
   * @var \Drupal\opigno_module_restart\Services\ModuleRestartManager
   */
  protected ModuleRestartManager $restartManager;

  /**
   * The Opigno module entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $moduleStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleRestartManager $restart_manager, ...$default) {
    parent::__construct(...$default);
    $this->restartManager = $restart_manager;
    $this->moduleStorage = $this->entityTypeManager->getStorage('opigno_module');
  }

  /**
   * {@inheritdoc}
   */
  protected function getStepAction(string $typology, $id, GroupInterface $group): array {
    if ($typology !== 'Module') {
      return [];
    }

    $inactive = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'class' => ['play-inactive'],
      ],
    ];

    $module = $this->moduleStorage->load($id);
    if (!$module instanceof OpignoModuleInterface) {
      return $inactive;
    }

    $link = $this->renderModuleActionLink($module, $group);
    if (!$link) {
      return $inactive;
    }

    $this->displayStepActions = TRUE;

    return $link;
  }

  /**
   * Gets the render array to display the module action link (start/restart).
   *
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $module
   *   The module to generate the action link for.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The LP group the module relates to.
   * @param string $title
   *   The link title.
   * @param bool $add_class
   *   Should the action classes be added to the link or not.
   *
   * @return array|null
   *   The render array to display the module start/restart link; NULL if the
   *   link can't be generated.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function renderModuleActionLink(
    OpignoModuleInterface $module,
    GroupInterface $group,
    string $title = '',
    bool $add_class = TRUE
  ): ?array {
    if (!$module->canCreateNewAttempt($this->currentUser)) {
      return NULL;
    }

    $taken_attempts = $module->getTakenAttemptsNumber($this->currentUser);
    $not_finished = $this->restartManager->isNotFinishedAttemptsExist($module, $group, $this->currentUser);
    $attrs = [];
    if ($taken_attempts && !$not_finished) {
      $route = 'opigno_module_restart.restart_module';
      $title = $title ?? $this->t('Restart');
      if ($add_class) {
        $attrs['attributes']['class'] = ['restart'];
      }
    }
    elseif (!$taken_attempts || $not_finished) {
      $route = 'opigno_module.take_module';
      $title = $title ?? $this->t('Start');
      if ($add_class) {
        $attrs['attributes']['class'] = ['start'];
      }
    }
    else {
      return NULL;
    }

    $url = Url::fromRoute($route, [
      'opigno_module' => $module->id(),
      'group' => $group->id(),
    ], $attrs);

    return $url->access($this->currentUser) ? Link::fromTextAndUrl($title, $url)->toRenderable() : NULL;
  }

}
