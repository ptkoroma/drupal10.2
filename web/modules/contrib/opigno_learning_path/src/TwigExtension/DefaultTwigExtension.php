<?php

namespace Drupal\opigno_learning_path\TwigExtension;

use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_learning_path\LearningPathAccess;
use Drupal\opigno_learning_path\LPStatusInterface;
use Drupal\opigno_learning_path\Progress;
use Drupal\opigno_learning_path\Services\LearningPathContentService;
use Drupal\opigno_learning_path\Traits\LearningPathAchievementTrait;
use Drupal\opigno_module\Entity\OpignoModule;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The twig extension for the learning path.
 *
 * @package Drupal\opigno_learning_path\TwigExtension
 */
class DefaultTwigExtension extends AbstractExtension {

  use LearningPathAchievementTrait;
  use StringTranslationTrait;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Opigno LP progress service.
   *
   * @var \Drupal\opigno_learning_path\Progress
   */
  protected $progress;

  /**
   * The LP content service.
   *
   * @var \Drupal\opigno_learning_path\Services\LearningPathContentService
   */
  protected $lpContentService;

  /**
   * DefaultTwigExtension constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\opigno_learning_path\Progress $progress
   *   The Opigno LP progress service.
   * @param \Drupal\opigno_learning_path\Services\LearningPathContentService $lp_content_service
   *   The Opigno LP content service.
   */
  public function __construct(
    RouteMatchInterface $route_match,
    AccountInterface $account,
    ModuleHandlerInterface $module_handler,
    RendererInterface $renderer,
    Progress $progress,
    LearningPathContentService $lp_content_service
  ) {
    $this->routeMatch = $route_match;
    $this->currentUser = $account;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->progress = $progress;
    $this->lpContentService = $lp_content_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenParsers() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeVisitors() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTests() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction(
        'is_group_member',
        [$this, 'isGroupMember']
      ),
      new TwigFunction(
        'get_join_group_link',
        [$this, 'getJoinGroupLink']
      ),
      new TwigFunction(
        'get_start_link',
        [$this, 'getStartLink']
      ),
      new TwigFunction(
        'get_progress',
        [$this, 'getProgress']
      ),
      new TwigFunction(
        'get_training_content',
        [$this, 'getTrainingContent']
      ),
      new TwigFunction(
        'opigno_modules_counter',
        [$this, 'opignoModulesCounter']
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOperators() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'opigno_learning_path.twig.extension';
  }

  /**
   * Tests if user is member of a group.
   *
   * @param mixed $group
   *   Group.
   * @param mixed $account
   *   User account.
   *
   * @return bool
   *   Member flag.
   */
  public function isGroupMember($group = NULL, $account = NULL) {
    if (!$group) {
      $group = $this->routeMatch->getParameter('group');
    }

    if (empty($group)) {
      return FALSE;
    }

    if (!$account) {
      $account = $this->currentUser;
    }

    return $group->getMember($account) !== FALSE;
  }

  /**
   * Returns join group link.
   *
   * @param mixed $group
   *   Group.
   * @param mixed $account
   *   User account.
   * @param array $attributes
   *   Attributes.
   *
   * @return mixed|null|string
   *   Join group link or empty.
   */
  public function getJoinGroupLink($group = NULL, $account = NULL, array $attributes = []) {
    if (!isset($group)) {
      $group = $this->routeMatch->getParameter('group');
    }

    if (!isset($account)) {
      $account = $this->currentUser;
    }

    $route_name = $this->routeMatch->getRouteName();
    $visibility = $group->field_learning_path_visibility->value;
    $access = isset($group) && $group->access('view', $account) && ($group->hasPermission('join group', $account) || $visibility == 'public' || $visibility == 'semiprivate');

    // If training is paid.
    $is_member = $group->getMember($account) !== FALSE;
    $module_commerce_enabled = $this->moduleHandler->moduleExists('opigno_commerce');
    if ($module_commerce_enabled
      && $group->hasField('field_lp_price')
      && $group->get('field_lp_price')->value != 0
      && !$is_member) {

      return '';
    }

    if ($route_name == 'entity.group.canonical' && $access) {
      $link = NULL;
      $validation = LearningPathAccess::requiredValidation($group, $account);
      $is_anonymous = $account->id() === 0;

      if ($visibility == 'semiprivate' && $validation) {
        $joinLabel = $this->t('Request subscription to the training');
      }
      else {
        $joinLabel = $this->t('Subscribe to training');
      }

      if ($is_anonymous) {
        if ($visibility === 'public') {
          $link = [
            'title' => $this->t('Start'),
            'route' => 'opigno_learning_path.steps.type_start',
            'args' => ['group' => $group->id()],
          ];
          $attributes['class'][] = 'use-ajax';
        }
        else {
          $url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()]);
          $link = [
            'title' => $joinLabel,
            'route' => 'user.login',
            'args' => ['destination' => $url->toString()],
          ];
        }
      }
      elseif (!$is_member) {
        $link = [
          'title' => $joinLabel,
          'route' => 'entity.group.join',
          'args' => ['group' => $group->id()],
        ];
      }

      if ($is_anonymous && $visibility == 'semiprivate') {
        $url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()]);
        $link = [
          'title' => $this->t('Create an account and subscribe'),
          'route' => 'user.login',
          'args' => ['prev_path' => $url->toString()],
        ];
      }

      if ($link) {
        $url = Url::fromRoute($link['route'], $link['args'], ['attributes' => $attributes]);
        return Link::fromTextAndUrl($link['title'], $url)->toRenderable();
      }
    }

    return '';
  }

  /**
   * Returns group start link.
   *
   * @param mixed $group
   *   Group.
   * @param array $attributes
   *   Attributes.
   * @param bool $one_button
   *   Should the one button be displayed or not.
   *
   * @return array|string
   *   Group start link or empty.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getStartLink($group = NULL, array $attributes = [], bool $one_button = FALSE) {
    if (!$group) {
      $group = $this->routeMatch->getParameter('group');
    }

    if (filter_var($group, FILTER_VALIDATE_INT) !== FALSE) {
      $group = Group::load($group);
    }

    if (empty($group) || (!is_object($group)) || (is_object($group) && $group->bundle() !== 'learning_path')) {
      return [];
    }

    $args = [];
    $current_route = $this->routeMatch->getRouteName();
    $visibility = $group->field_learning_path_visibility->value;
    $account = $this->currentUser;
    $is_anonymous = $account->isAnonymous();
    if ($is_anonymous && $visibility != 'public') {
      if ($visibility != 'semiprivate'
            && (!$group->hasField('field_lp_price')
            || $group->get('field_lp_price')->value == 0)) {
        return [];
      }
    }

    // Check if we need to wait validation.
    $validation = LearningPathAccess::requiredValidation($group, $account);
    $member_pending = !LearningPathAccess::statusGroupValidation($group, $account);
    $module_commerce_enabled = $this->moduleHandler->moduleExists('opigno_commerce');
    $required_trainings = LearningPathAccess::hasUncompletedRequiredTrainings($group, $account);

    if (
      $module_commerce_enabled
      && $group->hasField('field_lp_price')
      && $group->get('field_lp_price')->value != 0
      && !$group->getMember($account)) {
      // Get currency code.
      $top_text = $group->get('field_lp_price')->value . ' ' . static::getCurrencyCode();
      $top_text = [
        '#type' => 'inline_template',
        '#template' => '<div class="top-text price">{{top_text}}</div>',
        '#context' => [
          'top_text' => $top_text ?? '',
        ],
      ];
      $text = $this->t('Buy');
      $attributes['class'][] = 'btn-bg';
      $route = 'opigno_commerce.subscribe_with_payment';
    }
    elseif ($visibility === 'public' && $is_anonymous) {
      $text = $this->t('Start');
      $route = 'opigno_learning_path.steps.type_start';
      $attributes['class'][] = 'use-ajax';
      $attributes['class'][] = 'start-link';
    }
    elseif (!$group->getMember($account) || $is_anonymous) {
      if ($group->hasPermission('join group', $account)) {
        if ($current_route == 'entity.group.canonical') {
          $text = $validation ? $this->t('Request subscription') : $this->t('Enroll');
          $attributes['class'][] = 'btn-bg';
          $attributes['data-toggle'][] = 'modal';
          $attributes['data-target'][] = '#join-group-form-overlay';
        }
        else {
          $text = $this->t('Learn more');
        }

        $route = ($current_route == 'entity.group.canonical') ? 'entity.group.join' : 'entity.group.canonical';
        if ($current_route == 'entity.group.canonical') {
          $attributes['class'][] = 'join-link';
        }
      }
      elseif ($visibility === 'semiprivate' && $is_anonymous) {
        if ($current_route == 'entity.group.canonical') {
          $text = $this->t('Create an account and subscribe');
          $route = 'user.login';
          $args += ['prev_path' => Url::fromRoute('entity.group.canonical', ['group' => $group->id()])->toString()];
        }
        else {
          $text = $this->t('Learn more');
          $route = 'entity.group.canonical';
        }
      }
      else {
        return '';
      }
    }
    elseif ($member_pending || $required_trainings) {
      $route = 'entity.group.canonical';
      if ($required_trainings) {
        // Display only the icon for certain cases (for ex., on the catalog).
        if ($one_button) {
          $top_text = [
            '#markup' => Markup::create('<i class="fi fi-rr-lock"></i>'),
          ];
        }
        else {
          $links = [];
          foreach ($required_trainings as $gid) {
            $training = Group::load($gid);
            $url = Url::fromRoute($route, ['group' => $training->id()]);
            $link = Link::fromTextAndUrl($training->label(), $url)
              ->toRenderable();
            array_push($links, $link);
          }
          $top_text = $links;
          $top_text = [
            '#type' => 'inline_template',
            '#template' => '<div class="top-text complete"><i class="fi fi-rr-lock"></i><div>{{"Complete"|t}}<br>{{top_text}}<br>{{"before"|t}}</div></div>',
            '#context' => [
              'top_text' => $this->renderer->render($top_text) ?? '',
            ],
          ];
        }
      }
      else {
        // Display only the icon for certain cases (for ex., on the catalog).
        if ($one_button) {
          $top_text = [
            '#markup' => Markup::create('<i class="fi fi-rr-menu-dots"></i>'),
          ];
        }
        else {
          $top_text = [
            '#type' => 'inline_template',
            '#template' => '<div class="top-text approval"><i class="fi fi-rr-menu-dots"></i><div>{{top_text}}</div></div>',
            '#context' => [
              'top_text' => $this->t('Approval Pending'),
            ],
          ];
        }
      }

      $text = $this->t('Start');

      $attributes['class'][] = 'disabled';
      $attributes['class'][] = 'approval-pending-link';
    }
    else {
      $uid = $account->id();
      $lp_attempt = OpignoModule::getLastTrainingAttempt($uid, $group->id(), TRUE);
      if ($lp_attempt instanceof LPStatusInterface) {
        $status_class = $lp_attempt->getStatus();
      }
      else {
        $expired = LPStatus::isCertificateExpired($group, $uid);
        $is_passed = opigno_learning_path_is_passed($group, $uid, FALSE, $expired);
        $status_class = $is_passed ? 'passed' : 'pending';
      }
      $route = 'opigno_learning_path.steps.type_start';

      switch ($status_class) {
        case 'passed':
        case 'failed':
          // @todo if the user has an attempt, the button should say "Restart".
          //   we are ignoring unfinished attempts for now.
          $text = $this->t('Restart');
          $route = 'opigno_learning_path.restart';
          if (!$one_button) {
            $top_link_text = $this->t('See result');
          }
          break;

        case 'pending':
        default:
          // Legacy code: if the user has an attempt, the button should say
          // "Continue training".
          if (LPStatus::getCurrentLpAttempt($group, $account)) {
            if (!$one_button) {
              $top_link_text = $this->t('See progress');
            }
            $text = $this->t('Continue training');
          }
          else {
            $text = $this->t('Start');
          }
      }

      $url = Url::fromRoute('opigno_learning_path.training', ['group' => $group->id()]);
      $top_text = isset($top_link_text) ? [
        '#type' => 'link',
        '#url' => $url,
        '#title' => $top_link_text,
        '#access' => $url->access($this->currentUser()),
        '#attributes' => [
          'class' => ['btn', 'btn-rounded', 'continue-link'],
        ],
      ] : [];

      $attributes['class'][] = 'use-ajax';

      if (opigno_learning_path_started($group, $account)) {
        $attributes['class'][] = 'continue-link';
      }
      else {
        $attributes['class'][] = 'start-link';
      }
    }

    $type = $current_route === 'view.opigno_training_catalog.training_catalogue' ? 'catalog' : 'group';

    $args += ['group' => $group->id(), 'type' => $type];
    $url = Url::fromRoute($route, $args, ['attributes' => $attributes]);
    $l = Link::fromTextAndUrl($text, $url)->toRenderable();
    $content = [
      $top_text ?? [],
      $l,
    ];
    $cache = CacheableMetadata::createFromRenderArray($content);
    $cache->addCacheableDependency($group);
    $cache->addCacheableDependency($account);
    $cache->applyTo($content);
    return $content;
  }

  /**
   * Gets the store currency code.
   *
   * @return string
   *   The default store currency code.
   */
  private static function getCurrencyCode(): string {
    // This can not be added as a dependency to services.yml to avoid fatal
    // errors in case when the commerce module isn't enabled.
    if (!\Drupal::hasService('commerce_store.current_store')) {
      return '';
    }

    $commerce_store_service = \Drupal::service('commerce_store.current_store');
    if (!$commerce_store_service instanceof CurrentStoreInterface) {
      return '';
    }

    $store_default = $commerce_store_service->getStore();
    return $store_default ? $store_default->getDefaultCurrencyCode() : '';
  }

  /**
   * Returns current user progress.
   *
   * @return array|mixed|null
   *   Current user progress.
   */
  public function getProgress($ajax = TRUE, $class = 'group-page', ?GroupInterface $group = NULL) {
    $group = $group ?: $this->routeMatch->getParameter('group');
    if (!$group instanceof GroupInterface) {
      return [];
    }

    $account = $this->currentUser;
    $member_pending = !LearningPathAccess::statusGroupValidation($group, $account);
    $required_trainings = LearningPathAccess::hasUncompletedRequiredTrainings($group, $account);

    // Don't display the progress not all required trainings completed or the
    // membership approval is needed.
    if ($member_pending || $required_trainings) {
      return [];
    }

    if ($ajax) {
      $content = $this->progress->getProgressAjaxContainer($group->id(), $account->id(), '', $class);
    }
    else {
      $content = $this->progress->getProgressBuild($group->id(), $account->id(), '', $class);
    }

    $cache = CacheableMetadata::createFromRenderArray($content);
    $cache->addCacheableDependency($group);
    $cache->addCacheableDependency($account);
    $cache->applyTo($content);

    return ($content);
  }

  /**
   * Returns training content.
   *
   * @return array
   *   Training content.
   */
  public function getTrainingContent(): array {
    return $this->lpContentService->trainingContent();
  }

  /**
   * Counter of modules by group.
   */
  public function opignoModulesCounter($group) {
    $steps = $this->getStepsByGroup($group);
    return Markup::create(count($steps));
  }

}
