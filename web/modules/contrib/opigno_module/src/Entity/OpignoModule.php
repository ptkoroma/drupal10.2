<?php

namespace Drupal\opigno_module\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\group\Entity\Group;
use Drupal\media\MediaInterface;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\opigno_learning_path\Entity\LPModuleAvailability;
use Drupal\opigno_learning_path\LearningPathAccess;
use Drupal\opigno_learning_path\LPStatusInterface;
use Drupal\user\UserInterface;
use Drupal\user\Entity\User;

/**
 * Defines the Module entity.
 *
 * @ingroup opigno_module
 *
 * @ContentEntityType(
 *   id = "opigno_module",
 *   label = @Translation("Module"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\opigno_module\OpignoModuleListBuilder",
 *     "views_data" = "Drupal\opigno_module\Entity\OpignoModuleViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\opigno_module\Form\OpignoModuleForm",
 *       "add" = "Drupal\opigno_module\Form\OpignoModuleForm",
 *       "edit" = "Drupal\opigno_module\Form\OpignoModuleForm",
 *       "delete" = "Drupal\opigno_module\Form\OpignoModuleDeleteForm",
 *     },
 *     "access" = "Drupal\opigno_module\OpignoModuleAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\opigno_module\OpignoModuleHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "opigno_module",
 *   data_table = "opigno_module_field_data",
 *   revision_table = "opigno_module_revision",
 *   revision_data_table = "opigno_module_field_revision",
 *   translatable = TRUE,
 *   show_revision_ui = TRUE,
 *   admin_permission = "administer module entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   links = {
 *     "canonical" = "/module/{opigno_module}",
 *     "add-form" = "/admin/structure/opigno_module/add",
 *     "edit-form" = "/admin/structure/opigno_module/{opigno_module}/edit",
 *     "delete-form" = "/admin/structure/opigno_module/{opigno_module}/delete",
 *     "collection" = "/admin/structure/opigno_module",
 *   },
 *   field_ui_base_route = "opigno_module.settings"
 * )
 */
class OpignoModule extends RevisionableContentEntityBase implements OpignoModuleInterface {

  use EntityChangedTrait;

  /**
   * Static cache of user attempts.
   *
   * @var array
   */
  protected $userAttempts = [];

  /**
   * Static cache of user active attempt.
   *
   * @var array
   */
  protected $userActiveAttempt = [];

  /**
   * Static cache of user training active attempt.
   *
   * @var mixed
   */
  protected $userTrainingActiveAttempt = [];

  /**
   * Static cache of activities.
   *
   * @var array
   */
  protected $activities = [];

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * Get Module Randomization setting.
   */
  public function getRandomization() {
    return $this->get('randomization')->value;
  }

  /**
   * Get Module Backwards navigation setting.
   */
  public function getBackwardsNavigation() {
    return (bool) $this->get('backwards_navigation')->value;
  }

  /**
   * Get Allow resume setting.
   */
  public function getAllowResume() {
    return (bool) $this->get('allow_resume')->value;
  }

  /**
   * Get random activities count.
   */
  public function getRandomActivitiesCount() {
    return $this->get('random_activities')->value;
  }

  /**
   * Set random activities count.
   */
  public function setRandomActivitiesCount($value) {
    $this->set('random_activities', $value);
    return $this;
  }

  /**
   * Get random activity score.
   */
  public function getRandomActivityScore() {
    return $this->get('random_activity_score')->value;
  }

  /**
   * Get skills activity of module.
   */
  public function getSkillsActive() {
    return $this->get('skills_active')->value;
  }

  /**
   * Get skills activity of module.
   */
  public function getModuleSkillsGlobal() {
    return $this->get('module_global')->value;
  }

  /**
   * Get target skill of module.
   */
  public function getTargetSkill() {
    return $this->get('skill_target')->value;
  }

  /**
   * Set random activities count.
   */
  public function setRandomActivityScore($value) {
    $this->set('random_activity_score', $value);
    return $this;
  }

  /**
   * Get image entity.
   */
  public function getModuleImage() {
    $media = $this->get('module_media_image')->entity;
    if ($media) {
      return $media->get('field_media_image')->entity;
    }
    else {
      return NULL;
    }
  }

  /**
   * Get hide results setting.
   */
  public function getHideResults($answer = NULL): bool {
    // Show results for admins.
    $account = \Drupal::currentUser();
    if ($account->hasPermission('view module results')) {
      return FALSE;
    }
    $result = (bool) $this->get('hide_results')->value;
    if (!$answer instanceof OpignoAnswer) {
      return $result;
    }
    // Load group.
    // Check if user has role 'student manager' in a learning_path.
    $user_module_status = $answer->getUserModuleStatus();
    if ($user_module_status instanceof UserModuleStatus &&
      $user_module_status->hasField('learning_path') &&
      !empty($group = $user_module_status->get('learning_path')->target_id) &&
      LearningPathAccess::memberHasRole('user_manager', $account, $group)) {
      return FALSE;
    }

    // Return selected value for others.
    return $result;
  }

  /**
   * Get feedback results options.
   */
  public function getResultsOptions() {
    $select_query = \Drupal::database()->select('opigno_module_result_options', 'omro')
      ->fields('omro')
      ->condition('module_id', $this->id())
      ->condition('module_vid', $this->getRevisionId());

    return $select_query->execute()->fetchAll();
  }

  /**
   * Insert feedback results options.
   */
  public function insertResultsOptions(FormStateInterface $form_state) {
    $insert_query = \Drupal::database()->insert('opigno_module_result_options')
      ->fields([
        'module_id',
        'module_vid',
        'option_name',
        'option_summary',
        'option_summary_format',
        'option_start',
        'option_end',
      ]);
    $form_values = $form_state->getValues();
    foreach ($form_values['results_options'] as $option) {
      if (!empty($option['option_name'])) {
        if (is_array($option['option_summary'])) {
          $option['option_summary_format'] = $option['option_summary']['format'];
          $option['option_summary'] = $option['option_summary']['value'];
        }
        $insert_query->values([
          'module_id' => $this->id(),
          'module_vid' => $this->getRevisionId(),
          'option_name' => $option['option_name'],
          'option_summary' => $option['option_summary'],
          'option_summary_format' => $option['option_summary_format'],
          'option_start' => $option['option_start'],
          'option_end' => $option['option_end'],
        ]);
      }
    }
    $insert_query->execute();
  }

  /**
   * Update feedback results options.
   */
  public function updateResultsOptions(FormStateInterface $form_state) {
    // Remove existing options.
    \Drupal::database()->delete('opigno_module_result_options')
      ->condition('module_id', $this->id())
      ->condition('module_vid', $this->getRevisionId())
      ->execute();
    // Insert options.
    $this->insertResultsOptions($form_state);
  }

  /**
   * Checks module availability.
   */
  public function checkModuleAvailability() {
    $availability = [
      'open' => TRUE,
      'message' => '',
    ];

    $module_availability = 0;
    $group_id = OpignoGroupContext::getCurrentGroupId();
    $lp_module_availability = LPModuleAvailability::loadByProperties([
      'group_id' => $group_id,
      'entity_id' => $this->id(),
    ]);
    if ($lp_module_availability) {
      $lp_module_availability = current($lp_module_availability);
      $module_availability = $lp_module_availability->getAvailability();
    }

    if ($module_availability) {
      $open_date = $lp_module_availability->getOpenDate();
      $close_date = $lp_module_availability->getCloseDate();
      if ($open_date && $close_date) {
        $quiz_open = \Drupal::time()->getRequestTime() >= $open_date;
        $quiz_closed = \Drupal::time()->getRequestTime() >= $close_date;
      }

      if (isset($quiz_open) && isset($quiz_closed)) {
        if (!$quiz_open || $quiz_closed) {
          $message = '';

          // Load Config.
          $config = \Drupal::config('opigno_module.settings');

          if ($quiz_closed) {
            $message = $config->get('availability_closed_message');
          }
          elseif (!$quiz_open) {
            $message = $config->get('availability_unavailable_message');
          }

          $message = \Drupal::token()->replace($message, ['opigno_module' => $this]);

          $availability = [
            'open' => FALSE,
            'message' => $message,
          ];
        }
      }
    }

    return $availability;
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleAttempts(
    AccountInterface $user,
    $range = NULL,
    $latest_cert_date = NULL,
    bool $finished = FALSE,
    $group_id = NULL
  ) {
    $uid = (int) $user->id();
    $key = $this->id() . '_' . $uid;
    $group_id = $this->getGroupIdCurrentTraining($group_id);
    $last_lp_attempt = static::getLastTrainingAttempt($uid, $group_id);

    if (isset($group_id)) {

      $key .= '_' . $group_id;

      if (isset($range)) {
        $key .= '_' . $range;
      }

      if ($last_lp_attempt) {
        $key .= '_' . $last_lp_attempt;
      }

      if (array_key_exists($key, $this->userAttempts)) {
        return $this->userAttempts[$key];
      }

      $key_base = $key;
      $status_storage = static::entityTypeManager()->getStorage('user_module_status');
      $query = $status_storage->getQuery();
      $query->condition('module', $this->id())
        ->condition('user_id', $uid);
      $query->condition('learning_path', $group_id);

      if ($finished) {
        $query->condition('finished', 0, '>');
      }

      if ($latest_cert_date) {
        $query->condition('started', $latest_cert_date, '>=');
      }

      if ($last_lp_attempt) {
        $query->condition('lp_status', $last_lp_attempt);
      }

      $status_ids = $query->execute();
      if ($status_ids) {
        $status_entities = $status_storage->loadMultiple($status_ids);
        $this->userAttempts[$key_base] = $status_entities;
        // Figure out the 'last' and 'best' scores in PHP to avoid running three
        // queries for potentially the same entity.
        $max_id = max(array_keys($status_ids));
        $this->userAttempts[$key_base . '_last'] = [$max_id => $status_entities[$max_id]];
        $max_score = 0;
        $best_entity = FALSE;
        /** @var \Drupal\opigno_module\Entity\UserModuleStatus[] $status_entities */
        $status_entities = array_filter($status_entities, function ($attempt) {
          return !$attempt->isEvaluated() || ($attempt->isEvaluated() && $this->hasAnsweredActivities($attempt));
        });
        /** @var \Drupal\opigno_module\Entity\UserModuleStatus[] $entity */
        foreach ($status_entities as $entity) {
          if ($entity->getScore() >= $max_score) {
            $max_score = $entity->getScore();
            $best_entity = $entity;
          }
        }

        if (!$best_entity) {
          $this->userAttempts[$key_base . '_best'] = [];
        }
        else {
          $this->userAttempts[$key_base . '_best'] = [$best_entity->id() => $best_entity];
        }
      }
      else {
        $this->userAttempts[$key_base . '_best'] = [];
        $this->userAttempts[$key_base . '_last'] = [];
      }

      return $this->userAttempts[$key] ?? [];
    }

    return [];
  }

  /**
   * Check if user has answered all activities.
   */
  public function hasAnsweredActivities(UserModuleStatus $attempt) {
    $answers = [];
    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = $attempt->getModule();
    $activities = $module->getModuleActivities(TRUE);
    $user = $attempt->getOwner();
    foreach ($activities as $activity) {
      $answers[] = $activity->getUserAnswer($module, $attempt, $user);
    }
    return count(array_filter($answers)) == count($activities);
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleActiveAttempt(
    AccountInterface $user,
    ?string $activity_link_type = NULL,
    $group_id = NULL
  ): ?EntityInterface {
    $key = $this->id() . '_' . $user->id();
    $group_id = $group_id ?? $this->getGroupIdCurrentTraining($group_id);
    if (!$group_id) {
      return NULL;
    }
    $key .= '_' . $group_id;

    if ($activity_link_type == 'flow') {
      // Used Opigno flow type of activity answer link.
      if (array_key_exists($key, $this->userActiveAttempt)) {
        return $this->userActiveAttempt[$key];
      }
      OpignoGroupContext::removeActivityLinkType();
    }
    else {
      // Used activity answer manual/direct link.
      if (array_key_exists($key, $this->userActiveAttempt) && !empty($this->userActiveAttempt[$key])) {
        return $this->userActiveAttempt[$key];
      }
      elseif (!empty($this->userAttempts[$key . '_last'])) {
        $last_attempt = reset($this->userAttempts[$key . '_last']);
        $this->userActiveAttempt[$key] = $last_attempt;
        return $last_attempt;
      }
    }

    $uid = (int) $user->id();
    // First check if any unfinished module attempt that doesn't relate to any
    // LP attempt exist.
    $unfinished_attempt = $this->getUnfinishedAttempt($uid, $group_id);
    if ($unfinished_attempt) {
      $this->userActiveAttempt[$key] = $unfinished_attempt;
    }
    else {
      $lp = Group::load($group_id);
      $lp_attempt = $this->getTrainingActiveAttempt($user, $lp);
      $status_storage = static::entityTypeManager()->getStorage('user_module_status');
      $query = $status_storage->getQuery();
      $query->condition('module', $this->id())
        ->condition('user_id', $user->id())
        ->condition('learning_path', $group_id)
        ->condition('finished', 0);
      if ($lp_attempt instanceof LPStatusInterface) {
        $query->condition('lp_status', $lp_attempt->id());
      }
      $module_statuses = $query->range(0, 1)->execute();

      $this->userActiveAttempt[$key] = !empty($module_statuses) ? $status_storage->load(key($module_statuses)) : NULL;
    }

    return $this->userActiveAttempt[$key];
  }

  /**
   * {@inheritdoc}
   */
  public function getLastModuleAttempt(int $uid, $gid, bool $load = FALSE) {
    $lp_attempt = OpignoModule::getLastTrainingAttempt($uid, $gid);
    $status_storage = static::entityTypeManager()->getStorage('user_module_status');
    $query = $status_storage->getQuery();
    $query->condition('module', $this->id())
      ->condition('user_id', $uid)
      ->condition('learning_path', $gid);
    if ($lp_attempt) {
      $query->condition('lp_status', $lp_attempt);
    }
    $module_attempt = $query
      ->sort('started', 'DESC')
      ->range(0, 1)
      ->execute();

    $module_attempt = reset($module_attempt);
    if (!$module_attempt) {
      return NULL;
    }

    return $load ? $status_storage->load($module_attempt) : $module_attempt;
  }

  /**
   * Checks if the unfinished attempts exist or not.
   *
   * This is needed to update the last unfinished module attempt and add a
   * reference to the current LP attempt.
   *
   * @param int $uid
   *   The user ID to check the unfinished module attempt for.
   * @param int|string $gid
   *   The group ID to check the unfinished module attempt for.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface|null
   *   The unfinished module attempt if it exists, NULL otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUnfinishedAttempt(int $uid, int|string $gid): ?UserModuleStatusInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('user_module_status');
    $unfinished = $storage->getQuery()
      ->condition('module', $this->id())
      ->condition('user_id', $uid)
      ->condition('learning_path', $gid)
      ->condition('finished', 0)
      ->notExists('lp_status')
      ->execute();

    $attempt = NULL;
    if ($unfinished) {
      $attempt = $storage->load(reset($unfinished));
    }

    return $attempt instanceof UserModuleStatusInterface ? $attempt : NULL;
  }

  /**
   * Get module attempt if user didn't finish training.
   *
   * @param \Drupal\user\Entity\User $user
   *   User entity.
   *
   * @return float|int
   *   Best score result.
   *
   * @throws \Exception
   */
  public function getBestScore(User $user) {
    // For each attempt, check the score and save the best one.
    $user_attempts = $this->getModuleAttempts($user, 'best');
    $best_score = 0;

    /** @var \Drupal\opigno_module\Entity\UserModuleStatus $user_attempt */
    foreach ($user_attempts as $user_attempt) {
      // Get the scores.
      $actual_score = $user_attempt->getAttemptScore();

      // Save the best score.
      if ($actual_score > $best_score) {
        $best_score = $actual_score;
      }
    }
    return $best_score;
  }

  /**
   * Implements opigno_module_get_user_module_score().
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account object.
   * @param int|null $latest_cert_date
   *   The last date when was attempt.
   * @param int|string|null $group_id
   *   The module-related group to get the score for.
   *
   * @return int
   *   Score in percents depends of OpignoModule keep_results option.
   *
   * @throws \Exception
   */
  public function getUserScore(AccountInterface $account, ?int $latest_cert_date = NULL, $group_id = NULL) {
    $group_id = $this->getGroupIdCurrentTraining($group_id);
    $attempts = $this->getModuleAttempts($account, 'last', $latest_cert_date, FALSE, $group_id);
    if (!$attempts) {
      return 0;
    }

    /** @var \Drupal\opigno_module\Entity\UserModuleStatus $last_attempt */
    $score = 0;
    $last_attempt = end($attempts);
    $which_score_keep = $this->getKeepResultsOption();
    switch ($which_score_keep) {
      // The newest score always saved in last attempt.
      case 'newest':
        $score = (int) $last_attempt->getScore();
        break;

      // For these options get best score.
      case 'best':
      case 'all':
        $score = $last_attempt->isFinished() ? (int) $last_attempt->calculateBestScore($latest_cert_date, $group_id) : $last_attempt->getScore();
        break;
    }
    // Clamp score.
    $score = max(0, $score);
    $score = min(100, $score);
    return $score;
  }

  /**
   * Returns group id given group type - a training or a course.
   *
   * @param numeric|null $group_id
   *   Group Id.
   *
   * @return int
   *   Group Id.
   */
  public function getGroupIdCurrentTraining($group_id) {
    $context_id = OpignoGroupContext::getCurrentGroupId();
    $group = $group_id ? Group::load($group_id) : NULL;
    if (
      $group instanceof Group &&
      $group->getGroupType()->id() == 'opigno_course' &&
      !empty($context_id) &&
      $group_id != $context_id
    ) {
      // If group is opigno_course, get parent training id.
      $group_id = $context_id;
    }
    $result = $group_id ?: $context_id;
    assert(is_numeric($result) && !empty($result));
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrainingActiveAttempt(AccountInterface $user, Group $group): ?EntityInterface {
    $key = $group->id() . '_' . $user->id();

    if (array_key_exists($key, $this->userTrainingActiveAttempt)) {
      return $this->userTrainingActiveAttempt[$key];
    }

    $status_storage = static::entityTypeManager()->getStorage('user_lp_status');
    $query = $status_storage->getQuery();
    $training_statuses = $query
      ->condition('gid', $group->id())
      ->condition('uid', $user->id())
      ->condition('finalized', 0)
      ->range(0, 1)
      ->execute();

    $this->userTrainingActiveAttempt[$key] = !empty($training_statuses) ? $status_storage->load(key($training_statuses)) : NULL;
    return $this->userTrainingActiveAttempt[$key];
  }

  /**
   * {@inheritdoc}
   */
  public static function getLastTrainingAttempt(int $uid, $gid, bool $load = FALSE) {
    if (!$gid) {
      return NULL;
    }

    $lp_status_storage = \Drupal::entityTypeManager()->getStorage('user_lp_status');
    $lp_status = $lp_status_storage->getQuery()
      ->condition('gid', $gid)
      ->condition('uid', $uid)
      ->sort('started', 'DESC')
      ->range(0, 1)
      ->execute();
    $lp_status = reset($lp_status);

    if (!$load || !$lp_status) {
      return $lp_status ?: NULL;
    }

    $loaded = $lp_status_storage->load($lp_status);
    return $loaded instanceof LPStatusInterface ? $loaded : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleActivities(bool $full = FALSE): ?array {
    if (empty($this->activities)) {
      // @todo Join table with activity revisions.
      $query = \Drupal::database()->select('opigno_activity', 'oa');
      $query->fields('oafd', [
        'id',
        'vid',
        'type',
        'name',
        'usage_activity',
        'skills_list',
        'skill_level',
      ]);
      $query->fields('omr', [
        'activity_status',
        'weight', 'max_score',
        'auto_update_max_score',
        'omr_id',
        'omr_pid',
        'child_id',
        'child_vid',
      ]);
      $query->addJoin('inner', 'opigno_activity_field_data', 'oafd', 'oa.id = oafd.id');
      $query->addJoin('inner', 'opigno_module_relationship', 'omr', 'oa.id = omr.child_id');
      $query->condition('oafd.status', 1);
      $query->condition('omr.parent_id', $this->id());
      if ($this->getRevisionId()) {
        $query->condition('omr.parent_vid', $this->getRevisionId());
      }
      $query->condition('omr_pid', NULL, 'IS');
      $query->orderBy('omr.weight');
      $query->orderBy('omr.omr_id');
      $result = $query->execute();

      foreach ($result as $activity) {
        $this->activities[$activity->id] = $activity;
      }
    }

    // Load entities if full was requested.
    if ($full && !empty($this->activities)) {
      $activity_ids = [];
      foreach ($this->activities as $activity) {
        $activity_ids[$activity->id] = $activity->id;
      }
      return OpignoActivity::loadMultiple($activity_ids);
    }

    return $this->activities;
  }

  /**
   * Get activities related to specific skills.
   */
  public function getSuitableActivities($current_skills) {
    if (empty($current_skills)) {
      return [0 => 0];
    }

    $activities = [];
    $query = \Drupal::database()->select('opigno_activity', 'oa');
    $query->fields('oafd', [
      'id',
      'vid',
      'type',
      'name',
      'usage_activity',
      'skills_list',
      'skill_level',
    ]);
    $query->addJoin('inner', 'opigno_activity_field_data', 'oafd', 'oa.id = oafd.id');
    $query->condition('oafd.status', 1);

    $query->condition('oafd.skills_list', $current_skills, 'IN');
    $query->condition('oafd.usage_activity', 'global');

    $result = $query->execute();
    foreach ($result as $activity) {
      $activities[$activity->id] = $activity;
    }

    return $activities;
  }

  /**
   * Get answers of the specific user within specified attempt.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   User account.
   * @param \Drupal\opigno_module\Entity\UserModuleStatusInterface $attempt
   *   User module attempt object.
   *
   * @return array|\Drupal\Core\Entity\EntityInterface|null
   *   User answers objects.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function userAnswers(AccountInterface $user, UserModuleStatusInterface $attempt) {
    $answers_storage = static::entityTypeManager()->getStorage('opigno_answer');
    $query = $answers_storage->getQuery();
    $answers = $query
      ->condition('module', $this->id())
      ->condition('user_id', $user->id())
      ->condition('user_module_status', $attempt->id())
      ->execute();
    return !empty($answers) ? $answers_storage->loadMultiple($answers) : [];
  }

  /**
   * Returns random activity.
   */
  public function getRandomActivity(UserModuleStatusInterface $attempt) {
    // Need to get activity that was not answered yet in this attempt.
    // Take all the activities.
    $activities = $this->getModuleActivities();
    $activities_storage = static::entityTypeManager()->getStorage('opigno_activity');
    $randomization = $this->getRandomization();
    $random_count = $this->getRandomActivitiesCount();
    $answered_random = 0;
    // Take answers within attempt.
    $user_answers = $this->userAnswers(\Drupal::currentUser(), $attempt);
    if (!empty($user_answers)) {
      foreach ($user_answers as $answer) {
        $answer_activity = $answer->getActivity();
        // Unset answered activity if answer already exist.
        if (isset($activities[$answer_activity->id()])) {
          if ($randomization == 2) {
            $answered_random++;
          }
          unset($activities[$answer_activity->id()]);
        }
      }
    }
    if ($randomization == 2) {
      $assigned_random = $activities;
      $activities = [];
      if (!empty($assigned_random) && $random_count > $answered_random) {
        $required_random = $random_count - $answered_random;
        $random_activities = array_rand($assigned_random, $required_random);
        if (is_array($random_activities)) {
          foreach ($random_activities as $random_activity) {
            $activities[$random_activity] = $assigned_random[$random_activity];
          }
        }
        else {
          $activities[$random_activities] = $assigned_random[$random_activities];
        }
      }
    }
    return !empty($activities) ? $activities_storage->load(array_rand($activities, 1)) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxActivitiesScore(): int {
    $activities = $this->getModuleActivities();
    $max_score = 0;

    if ($activities) {
      foreach ($activities as $activity) {
        $max_score += $activity->max_score ?? 0;
      }
    }

    return $max_score;
  }

  /**
   * Get option to know which result need to be saved on Database.
   *
   * @return string
   *   Return string "best", "newest" or "all"
   */
  public function getKeepResultsOption() {
    $keep_results_options = [
      0 => 'best',
      1 => 'newest',
      2 => 'all',
    ];
    $option = $this->get('keep_results')->value;
    return $keep_results_options[$option];
  }

  /**
   * {@inheritdoc}
   */
  public function getBadgeUrl(): ?string {
    $media = $this->get('badge_media_image')->referencedEntities();
    $image_item = reset($media);
    if (!$image_item instanceof MediaInterface
      || !$image_item->hasField('field_media_image')
      || $image_item->get('field_media_image')->isEmpty()
    ) {
      return NULL;
    }

    $badge_image = $image_item->get('field_media_image')->referencedEntities();
    $badge_image = reset($badge_image);

    return $badge_image instanceof FileInterface ? $badge_image->getFileUri() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedAttemptsNumber(): int {
    return $this->hasField('takes') ? (int) $this->get('takes')->getString() : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getTakenAttemptsNumber(
    ?AccountInterface $account = NULL,
    ?int $range = NULL,
    ?int $latest_cert_date = NULL,
    bool $finished = FALSE,
    ?int $gid = NULL
  ): int {
    $account = $account ?? \Drupal::currentUser();
    $user_attempts = $this->getModuleAttempts($account, $range, $latest_cert_date, $finished, $gid);

    return count($user_attempts);
  }

  /**
   * {@inheritdoc}
   */
  public function canCreateNewAttempt(
    ?AccountInterface $account = NULL,
    ?int $range = NULL,
    ?int $latest_cert_date = NULL,
    bool $finished = FALSE,
    ?int $gid = NULL
  ): bool {
    $account = $account ?? \Drupal::currentUser();
    $allowed = $this->getAllowedAttemptsNumber();

    // The unlimited module attempts.
    if (!$allowed) {
      return TRUE;
    }

    $taken = $this->getTakenAttemptsNumber($account, $range, $latest_cert_date, $finished, $gid);

    return $allowed > $taken;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Module entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Module entity.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue(NULL)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['module_media_image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Image'))
      ->setDescription(t('Set here a module image'))
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setRequired(FALSE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => ['image']])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'media_thumbnail',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_browser_entity_reference',
        'weight' => 26,
        'settings' => [
          'entity_browser' => 'media_entity_browser_groups',
          'field_widget_display' => 'rendered_entity',
          'field_widget_remove' => TRUE,
          'open' => TRUE,
          'selection_mode' => 'selection_append',
          'field_widget_display_settings' => [
            'view_mode' => 'image_only',
          ],
          'field_widget_edit' => FALSE,
          'field_widget_replace' => FALSE,
          'third_party_settings' => [
            'type' => 'entity_browser_entity_reference',
          ],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDefaultValue('')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textfield',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Module is published.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the Module was created.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the Module was last edited.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['random_activity_score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Random activity score'))
      ->setDescription(t('Score per each random activity.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(1);

    $fields['allow_resume'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Allow resume'))
      ->setDescription(t('Allow users to leave this Module incomplete and then resume it from where they left off.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 1,
      ]);

    $fields['backwards_navigation'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Backwards navigation'))
      ->setDescription(t('Allow users to go back and revisit activities already answered.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 2,
      ]);

    $randomization_options = [
      0 => t('No randomization'),
      1 => t('Random order'),
      2 => t('Random activities'),
    ];
    $randomization_description = t("<strong>Random order</strong> - all questions display in random order")
      . '<br/>' . t("<strong>Random activities</strong> - specific number of activities are drawn randomly from this module's pool of questions");
    $fields['randomization'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Randomize activities'))
      ->setDescription($randomization_description)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('allowed_values', $randomization_options)
      ->setDefaultValue(0)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 3,
      ]);

    $fields['random_activities'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Number of random activities'))
      ->setDescription(t('The number of activities to be randomly selected each time someone takes this module.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(1)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 4,
      ]);

    $takes_options = [t('Unlimited')];
    for ($i = 1; $i < 10; $i++) {
      $takes_options[$i] = $i;
    }
    $fields['takes'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Allowed number of attempts'))
      ->setDescription(t('The number of times a user is allowed to take this Module. <strong>Anonymous users are only allowed to take Module that allow an unlimited number of attempts.</strong>'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('allowed_values', $takes_options)
      ->setDefaultValue(0)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 4,
      ]);

    $fields['show_attempt_stats'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Display allowed number of attempts'))
      ->setDescription(t('Display the allowed number of attempts on the starting page for this Module.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 5,
      ]);

    $keep_results_options = [
      0 => t('The best'),
      1 => t('The newest'),
      2 => t('All'),
    ];
    $fields['keep_results'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Store results'))
      ->setDescription(t('These results should be stored for each user.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('allowed_values', $keep_results_options)
      ->setDefaultValue(2)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 6,
      ]);

    $fields['hide_results'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Do not display results at the end of the module'))
      ->setDescription(t('If you check this option, the correct answers won’t be displayed to the users at the end of the module.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 2,
      ]);

    $fields['badge_active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Activate badge system for this module'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 1,
      ]);

    $fields['badge_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ]);

    $fields['badge_description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Badge description'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setRequired(FALSE)
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 2,
        'settings' => [
          'rows' => 3,
        ],
      ]);

    $options = [
      'finished' => 'Finished',
      'success' => 'Success',
    ];
    $fields['badge_criteria'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Badge criteria'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue('finished')
      ->setSetting('allowed_values', $options)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 3,
      ]);

    $fields['badge_media_image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Badge image'))
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', ['target_bundles' => ['image_png']])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'media_thumbnail',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_browser_entity_reference',
        'weight' => 4,
        'settings' => [
          'entity_browser' => 'media_entity_browser_badge_images',
          'field_widget_display' => 'rendered_entity',
          'field_widget_remove' => TRUE,
          'open' => TRUE,
          'selection_mode' => 'selection_append',
          'field_widget_display_settings' => [
            'view_mode' => 'image_only',
          ],
          'field_widget_edit' => FALSE,
          'field_widget_replace' => FALSE,
          'third_party_settings' => [
            'type' => 'entity_browser_entity_reference',
          ],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['skills_active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Activate skills system for this module'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 1,
      ]);

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $target_skills = $term_storage->loadTree('skills', 0, 1);

    $options = [];
    foreach ($target_skills as $row) {
      $options[$row->tid] = $row->name;
    }

    $fields['skill_target'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Target skill'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setSetting('allowed_values', $options)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 3,
      ]);

    $fields['module_global'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Use all suitable activities from Opigno system'))
      ->setDescription(t('If checked then Opigno system will load all suitable activities from other trainings and not assigned activities.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 4,
      ]);

    return $fields;
  }

}
