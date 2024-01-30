<?php

namespace Drupal\opigno_learning_path;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class JoinService.
 */
class Progress {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The database layer.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The RequestStack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The user storage.
   *
   * @var \Drupal\User\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a new Progress object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    AccountInterface $current_user,
    Connection $database,
    RequestStack $request_stack,
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->dateFormatter = $date_formatter;
    $this->userStorage = $entity_type_manager->getStorage('user');
  }

  /**
   * Calculates progress in a group for a user.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $account_id
   *   User ID.
   * @param int|string $latest_cert_date
   *   Latest certification date.
   *
   * @return float
   *   Attempted activities count / total activities count.
   */
  public function getProgress(int $group_id, int $account_id, $latest_cert_date): float {
    $activities = opigno_learning_path_get_activities($group_id, $account_id, $latest_cert_date);

    $total = count($activities);
    $attempted = count(array_filter($activities, function ($activity) {
      return $activity['answers'] > 0;
    }));

    return $total > 0 ? $attempted / $total : 0;
  }

  /**
   * Check achievements data.
   *
   * It can be reused, but leave as it is for backward compatibility.
   *
   * @see \Drupal\opigno_learning_path\Progress::getProgressRound
   */
  public function getProgressAchievementsData($group_id, $account_id) {
    // Firstly check achievements data.
    $query = $this->database
      ->select('opigno_learning_path_achievements', 'a')
      ->fields('a')
      ->condition('a.gid', $group_id)
      ->condition('a.uid', $account_id);
    return $query->execute()->fetchAssoc();
  }

  /**
   * Get round integer of progress.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $account_id
   *   User ID.
   * @param int|string $latest_cert_date
   *   Latest certification date.
   * @param bool $update_progress
   *   Should the progress be updated or not.
   *
   * @return int
   *   Attempted activities count / total activities count.
   */
  public function getProgressRound(int $group_id, int $account_id, $latest_cert_date = 0, bool $update_progress = FALSE): int {
    // Firstly check achievements' data.
    $query = $this->database
      ->select('opigno_learning_path_achievements', 'a')
      ->fields('a', ['progress'])
      ->condition('a.gid', $group_id)
      ->condition('a.uid', $account_id)
      ->condition('a.status', 'completed');

    $achievements_data = $query->execute()->fetchAssoc();

    if ($achievements_data && !$update_progress) {
      return $achievements_data['progress'];
    }

    if (!$latest_cert_date) {
      $group = Group::load($group_id);
      $latest_cert_date = LPStatus::getTrainingStartDate($group, $account_id);
    }

    return round(100 * $this->getProgress($group_id, $account_id, $latest_cert_date));
  }

  /**
   * Get html container where progress will be loaded via ajax.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $account_id
   *   User ID.
   * @param int|string $latest_cert_date
   *   Latest certification date.
   * @param string $class
   *   Identifier for progress bar.
   * @param bool $build_html
   *   If the HTML should be returned or not.
   *
   * @return array
   *   Renderable array.
   */
  public function getProgressAjaxContainer(int $group_id, int $account_id, $latest_cert_date = 0, string $class = 'basic', bool $build_html = FALSE): array {

    if (!$latest_cert_date) {
      $group = Group::load($group_id);
      $latest_cert_date = LPStatus::getTrainingStartDate($group, $account_id);
    }

    // If latest_cert_date is empty we just set 0 to avoid any errors for empty
    // args.
    if (!$latest_cert_date) {
      $latest_cert_date = 0;
    }

    // Maybe in some cases we need to have pre-loaded progress bar without ajax.
    // An example unit tests or so.
    $preload = $this->requestStack->getCurrentRequest()->query->get('preload-progress');
    if ($preload || $build_html) {
      return $this->getProgressBuild($group_id, $account_id, $latest_cert_date, $class);
    }

    // HTML structure for ajax container.
    return [
      '#theme' => 'opigno_learning_path_progress_ajax_container',
      '#group_id' => $group_id,
      '#account_id' => $account_id,
      '#latest_cert_date' => $latest_cert_date,
      '#class' => $class,
      '#attached' => ['library' => ['opigno_learning_path/progress']],
    ];
  }

  /**
   * Get get progress bar it self.
   *
   * @param int|string $group_id
   *   Group ID.
   * @param int|string $account_id
   *   User ID.
   * @param int|string $latest_cert_date
   *   Latest certification date.
   * @param string $class
   *   Identifier for progress bar.
   *
   * @return array|int
   *   Renderable array.
   */
  public function getProgressBuild($group_id, $account_id, $latest_cert_date, string $class) {
    // If $latest_cert_date argument is 0 than it means it empty.
    if ($latest_cert_date === 0) {
      $latest_cert_date = '';
    }

    // Progress should be shown only for member of group.
    $group = Group::load($group_id);
    $account = $this->userStorage->load($account_id);
    $existing = $account instanceof UserInterface ? $group->getMember($account) : FALSE;
    if ($existing === FALSE) {
      $class = 'empty';
    }

    switch ($class) {
      case 'group-page':
        return $this->getProgressBuildGroupPage($group_id, $account_id, $latest_cert_date);

      case 'module-page':
        // @todo We can reuse a getProgressBuildGroupPage method.
        return $this->getProgressBuildModulePage($group_id, $account_id);

      case 'achievements-page':
        return $this->getProgressBuildAchievementsPage($group_id, $account_id, $latest_cert_date);

      case 'full':
      case 'mini':
        // Full: value, mini - with the progress bar.
        return [
          '#theme' => 'opigno_learning_path_progress',
          '#value' => $this->getProgressRound($group_id, $account_id, $latest_cert_date),
          '#show_bar' => $class === 'mini',
        ];

      case 'circle':
        return [
          '#theme' => 'lp_circle_progress',
          '#radius' => 31,
          '#progress' => $this->getProgressRound($group_id, $account_id, $latest_cert_date),
        ];

      case 'empty':
        // Empty progress.
        return ['#markup' => ''];

      default:
        // Only value.
        return $this->getProgressRound($group_id, $account_id, $latest_cert_date);
    }
  }

  /**
   * Get get progress for group page.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $account_id
   *   User ID.
   * @param int|string $latest_cert_date
   *   Latest certification date.
   *
   * @return array
   *   Renderable array.
   */
  public function getProgressBuildGroupPage(int $group_id, int $account_id, $latest_cert_date): array {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = Group::load($group_id);
    $account = $this->userStorage->load($account_id);
    $progress = $this->getProgressRound($group_id, $account_id, $latest_cert_date);

    return [
      '#theme' => 'lp_progress',
      '#progress' => $progress,
      '#summary' => $this->buildSummary($group, $account),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSummary($group, $account): array {
    $uid = (int) $account->id();
    $gid = (int) $group->id();
    // Get user training expiration flag.
    $expired = LPStatus::isCertificateExpired($group, $uid);
    $result = LPStatus::getCurrentLpAttempt($group, $account, TRUE, TRUE);

    return $result instanceof LPStatusInterface && $result->isStarted() ? [
      '#theme' => 'opigno_learning_path_step_block_progress',
      '#passed' => $result->getStatus() === 'passed',
      '#expired' => $expired,
      '#has_expiration_date' => LPStatus::isCertificateExpireSet($group),
      '#expired_date' => LPStatus::getCertificateExpireTimestamp($gid, $uid),
      '#complete_date' => $result->getFinished(),
      '#started_date' => $result->get('started')->getString(),
    ] : [];
  }

  /**
   * Get get progress for module page.
   *
   * @param int $group_id
   *   Group ID.
   * @param int $account_id
   *   User ID.
   *
   * @return array
   *   Renderable array.
   *
   * @opigno_deprecated
   */
  public function getProgressBuildModulePage(int $group_id, int $account_id): array {
    $progress = $this->getProgressRound($group_id, $account_id);

    return [
      '#theme' => 'block__opigno_module_learning_path_progress_block',
      'content' => [
        'progress' => $progress,
        'fullpage' => FALSE,
      ],
      '#configuration' => [
        'id' => 'opigno_module_learning_path_progress_block',
        'label' => 'Learning path progress',
        'provider' => 'opigno_module',
        'label_display' => '0',
      ],
      '#plugin_id' => 'opigno_module_learning_path_progress_block',
      '#base_plugin_id' => 'opigno_module_learning_path_progress_block',
      '#derivative_plugin_id' => NULL,
    ];
  }

  /**
   * Get get progress for achievements page.
   *
   * @param int $group_id
   *   Group ID.
   * @param int|string $account_id
   *   User ID.
   * @param int|string $latest_cert_date
   *   Latest certification date.
   *
   * @return array
   *   Renderable array.
   */
  public function getProgressBuildAchievementsPage(int $group_id, int $account_id, $latest_cert_date): array {
    $group = Group::load($group_id);
    $account = $this->userStorage->load($account_id);

    /** @var \Drupal\group\Entity\GroupContent $member */
    $member = $group->getMember($account)->getGroupContent();
    $registration = $member->getCreatedTime();
    $registration = $this->dateFormatter->format($registration, 'custom', 'm/d/Y');

    // We can not take data from the achievements table because before the
    // restart LP implementation the data in opigno_learning_path_achievements
    // was not updated until the new attempt is finished.
    $completed_on = opigno_learning_path_completed_on($group_id, $account_id, TRUE);
    $validation = $completed_on > 0
      ? $this->dateFormatter->format($completed_on, 'custom', 'F d, Y')
      : '';
    $validation_date = $this->dateFormatter->format($completed_on, 'custom', 'm/d/Y');

    $time_spent = opigno_learning_path_get_time_spent($group_id, $account_id);
    $time_spent = $this->dateFormatter->formatInterval($time_spent);
    $score = round(opigno_learning_path_get_score($group_id, $account_id, FALSE, $latest_cert_date));
    $progress = $this->getProgressRound($group_id, $account_id, $latest_cert_date);

    $expiration_message = '';
    $expiration_set = LPStatus::isCertificateExpireSet($group);
    $expired = FALSE;
    if ($expiration_set) {
      if ($expiration_timestamp = LPStatus::getCertificateExpireTimestamp($group->id(), $account_id)) {
        if (!LPStatus::isCertificateExpired($group, $account_id)) {
          $expiration_message = $this->t('Valid until');
        }
        else {
          $expired = TRUE;
          $expiration_message = $this->t('Expired on');
        }

        $expiration_message = $expiration_message . ' ' . $this->dateFormatter->format($expiration_timestamp, 'custom', 'F d, Y');
        $valid_until = $this->dateFormatter->format($expiration_timestamp, 'custom', 'm/d/Y');
      }
    }

    // Check the actual data.
    $lp_attempt = LPStatus::getCurrentLpAttempt($group, $account, TRUE, TRUE);
    if ($lp_attempt instanceof LPStatusInterface) {
      $status = $lp_attempt->getStatus();
      $is_passed = $status === 'passed';
      $is_failed = $status === 'failed';
      // Get the stored score if there are any unfinished module attempts
      // related to the LP attempt.
      $score = $lp_attempt->hasUnfinishedModuleAttempts() ? $lp_attempt->getScore() : $score;
    }
    else {
      $is_passed = opigno_learning_path_is_passed($group, $account_id);
      $is_failed = $progress === 100 && !$is_passed;
    }

    if ($is_passed) {
      $state_class = 'passed';
    }
    elseif ($is_failed) {
      $state_class = 'failed';
    }
    elseif (opigno_learning_path_is_attempted($group, $account_id)) {
      $state_class = 'in progress';
    }
    elseif ($expired) {
      $state_class = 'expired';
    }
    else {
      $state_class = 'not started';
    }

    $validation_message = !empty($validation) ? $this->t('Validation date: @date<br />', ['@date' => $validation]) : '';

    $has_certificate = !$group->get('field_certificate')->isEmpty();

    return [
      '#theme' => 'opigno_learning_path_training_summary',
      '#progress' => $progress,
      '#score' => $score,
      '#group_id' => $group_id,
      '#has_certificate' => $has_certificate,
      '#is_passed' => $is_passed,
      '#state_class' => $state_class,
      '#registration_date' => $registration,
      '#validation_message' => $validation_message . $expiration_message,
      '#time_spend' => $time_spent,
      '#validation_date' => $validation_date ?? 0,
      '#valid_until' => ($valid_until ?? ''),
      '#certificate_url' => $has_certificate && $is_passed ?
      Url::fromUri('internal:/certificate/group/' . $group_id . '/pdf') : FALSE,
    ];
  }

}
