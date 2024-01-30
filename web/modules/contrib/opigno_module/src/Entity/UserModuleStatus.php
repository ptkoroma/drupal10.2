<?php

namespace Drupal\opigno_module\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;

/**
 * Defines the User module status entity.
 *
 * @ingroup opigno_module
 *
 * @ContentEntityType(
 *   id = "user_module_status",
 *   label = @Translation("User module status"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\opigno_module\UserModuleStatusListBuilder",
 *     "views_data" = "Drupal\opigno_module\Entity\UserModuleStatusViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\opigno_module\Form\UserModuleStatusForm",
 *       "add" = "Drupal\opigno_module\Form\UserModuleStatusForm",
 *       "edit" = "Drupal\opigno_module\Form\UserModuleStatusForm",
 *       "delete" = "Drupal\opigno_module\Form\UserModuleStatusDeleteForm",
 *     },
 *     "access" = "Drupal\opigno_module\UserModuleStatusAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\opigno_module\UserModuleStatusHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "user_module_status",
 *   admin_permission = "administer user module status entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/user_module_status/{user_module_status}",
 *     "add-form" = "/admin/structure/user_module_status/add",
 *     "edit-form" = "/admin/structure/user_module_status/{user_module_status}/edit",
 *     "delete-form" = "/admin/structure/user_module_status/{user_module_status}/delete",
 *     "collection" = "/admin/structure/user_module_status",
 *   },
 *   field_ui_base_route = "user_module_status.settings"
 * )
 */
class UserModuleStatus extends ContentEntityBase implements UserModuleStatusInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    // Set the LP attempt.
    if ($this->getLearningPathAttempt()) {
      return;
    }

    $lp = $this->getLearningPath();
    if (!$lp instanceof GroupInterface) {
      return;
    }

    $uid = (int) $this->getOwnerId();
    $lp_attempt_id = OpignoModule::getLastTrainingAttempt($uid, (int) $lp->id());
    if ($lp_attempt_id) {
      $this->setLearningPathAttempt($lp_attempt_id);
    }
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
    return $this->get('started')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('started', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished(): bool {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished(bool $published): UserModuleStatusInterface {
    $this->set('status', $published);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getModule(): ?OpignoModuleInterface {
    $module = $this->hasField('module') ? $this->get('module')->entity : NULL;
    return $module instanceof OpignoModuleInterface ? $module : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setModule(OpignoModuleInterface $module) {
    $this->set('module', $module->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFinished($timestamp): UserModuleStatusInterface {
    $this->set('finished', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFinishedTime(): int {
    return (int) $this->get('finished')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getCompletionTime(): int {
    if (!$this->isFinished()) {
      return 0;
    }

    // We'll consider that the max time that the user can spend on the module
    // is 8h.
    $max = 28800;
    $time = $this->getFinishedTime() - $this->getCreatedTime();

    return $time < $max ? $time : $max;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastActivityId() {
    return $this->get('last_activity')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastActivity() {
    return $this->get('last_activity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastActivity(OpignoActivityInterface $activity) {
    $this->set('last_activity', $activity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentActivity(OpignoActivityInterface $activity = NULL) {
    $this->set('current_activity', $activity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentActivityId() {
    return $this->get('current_activity')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function isFinished(): bool {
    return (bool) $this->finished->value != 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isEvaluated() {
    return (bool) $this->evaluated->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEvaluated($value) {
    $this->set('evaluated', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setScore(string | int $value): UserModuleStatusInterface {
    $this->set('score', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore(): int {
    return (int) $this->get('score')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateScore(): int {
    $score = 0;
    $answers = $this->getAnswers();
    foreach ($answers as $answer) {
      /** @var \Drupal\opigno_module\Entity\OpignoAnswer $answer */
      $score += $answer->getScore();
    }

    return $score;
  }

  /**
   * Get Attempt Score.
   */
  public function getAttemptScore() {
    $score = (int) $this->calculateScore();
    $max_score = (int) $this->calculateMaxScore();

    if ($max_score <= 0) {
      return 0;
    }

    // Clamp score.
    $score = max(0, $score);
    $score = min($max_score, $score);

    // Convert absolute values to percent.
    return round(100 * $score / $max_score);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateMaxScore(): int {
    $max_score = 0;
    /** @var \Drupal\Core\Database\Connection $db_connection */
    $db_connection = \Drupal::service('database');
    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = $this->getModule();
    $score_query = $db_connection->select('opigno_module_relationship', 'omr')
      ->fields('omr', ['max_score', 'activity_status'])
      ->condition('omr.parent_id', $module->id())
      ->condition('omr.parent_vid', $module->getRevisionId());
    $score_results = $score_query->execute()->fetchAll();
    if (!empty($score_results)) {
      if ($module->getRandomization() == 2) {
        // Get max score only for answered random activities.
        $max_score += $this->getRandomActivitiesMaxScore();
      }
      else {
        foreach ($score_results as $score_result) {
          $max_score += $score_result->max_score;
        }
      }
    }
    return $max_score;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxScore() {
    return $this->get('max_score')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMaxScore($value) {
    $this->set('max_score', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnswers() {
    $answer_storage = static::entityTypeManager()->getStorage('opigno_answer');
    $query = $answer_storage->getQuery();
    $aids = $query->condition('user_id', $this->getOwnerId())
      ->condition('user_module_status', $this->id())
      ->execute();

    return $answer_storage->loadMultiple($aids);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateBestScore($latest_cert_date = NULL, $group_id = NULL): int {
    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = $this->getModule();
    $user = $this->getOwner();
    // For each attempt, check the score and get the best one.
    $user_attempts = $module->getModuleAttempts($user, 'best', $latest_cert_date, FALSE, $group_id);
    if (!$user_attempts) {
      return 0;
    }
    $best_score = 0;
    /** @var \Drupal\opigno_module\Entity\UserModuleStatus $user_attempt */
    foreach ($user_attempts as $user_attempt) {
      // Get the scores.
      $actual_score = $user_attempt->getScore();
      // Clamp score.
      $actual_score = max(0, $actual_score);
      $actual_score = min(100, $actual_score);
      // Save the best score.
      if ($actual_score > $best_score) {
        $best_score = $actual_score;
      }
    }

    return $best_score;
  }

  /**
   * Finish user attempt.
   */
  public function finishAttempt() {
    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = $this->getModule();
    $which_score_keep = $module->getKeepResultsOption();
    $this->setFinished(time());
    $this->setCurrentActivity();
    // Calculate both score and maximum score.
    $score = $this->calculateScore();
    $max_score = $this->calculateMaxScore();
    if ($max_score > 0) {
      $percents = round(($score / $max_score) * 100);
    }
    // If only best score need to be saved.
    elseif ($which_score_keep == 'best') {
      // Check if there are created attempts.
      if ($module->getModuleAttempts($this->getOwner())) {
        $best_attempt_score = $this->calculateBestScore();
        $percents = isset($percents) && $best_attempt_score < $percents
          ? $percents : $best_attempt_score;
      }
    }
    else {
      $percents = 100;
    }

    $this->setScore((int) $percents);
    $this->setMaxScore($max_score);
    // Check if attempt must be evaluated.
    $answers = $this->getAnswers();
    $attempt_evaluated = 1;
    if (!empty($answers)) {
      foreach ($answers as $answer) {
        // At least one answer is not evaluated.
        if (!$answer->isEvaluated()) {
          $attempt_evaluated = 0;
          break;
        }
      }
    }
    $this->setEvaluated($attempt_evaluated);
    $this->save();
    return $this;
  }

  /**
   * Calculate max score for the Module with enabled "Random activities" option.
   */
  public function getRandomActivitiesMaxScore() {
    $max_score = 0;
    /** @var \Drupal\Core\Database\Connection $db_connection */
    $db_connection = \Drupal::service('database');
    /** @var \Drupal\opigno_module\Entity\OpignoModule $module */
    $module = $this->getModule();
    $random_activities_count = $module->getRandomActivitiesCount();
    // Get activities from answers.
    $activities_query = $db_connection->select('opigno_answer_field_data', 'oafd')
      ->fields('oafd', ['activity'])
      ->condition('oafd.user_id', $this->getOwnerId())
      ->condition('oafd.user_module_status', $this->id())
      ->range(0, $random_activities_count);
    $results = $activities_query->execute()->fetchAllAssoc('activity');

    // Get max score for activities.
    if ($aids = array_keys($results)) {
      $score_query = $db_connection->select('opigno_module_relationship', 'omr')
        ->fields('omr', ['max_score', 'activity_status'])
        ->condition('omr.parent_id', $module->id())
        ->condition('omr.child_id', $aids, 'IN');
      $score_results = $score_query->execute()->fetchAll();
      if (!empty($score_results)) {
        foreach ($score_results as $score_result) {
          if ($score_result->activity_status == 1) {
            $max_score += $score_result->max_score;
          }
        }
      }
    }

    return $max_score;
  }

  /**
   * {@inheritdoc}
   */
  public function getLearningPath(): ?GroupInterface {
    $lp = $this->hasField('learning_path') ? $this->get('learning_path')->entity : NULL;
    return $lp instanceof GroupInterface && $lp->bundle() === 'learning_path' ? $lp : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLearningPathAttempt(bool $load = FALSE) {
    $field = 'lp_status';
    if (!$this->hasField($field) || $this->get($field)->isEmpty()) {
      return NULL;
    }

    $lp_status = $this->get($field)->getString();
    return $load ? \Drupal::entityTypeManager()->getStorage('user_lp_status')->load($lp_status) : $lp_status;
  }

  /**
   * {@inheritdoc}
   */
  public function setLearningPathAttempt(int $lp_status): UserModuleStatusInterface {
    $field = 'lp_status';
    if ($this->hasField($field)) {
      $this->set($field, $lp_status);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isInCurrentLpAttempt(int $uid, int $gid): bool {
    return OpignoModule::getLastTrainingAttempt($uid, $gid) === $this->getLearningPathAttempt();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the User module status entity.'))
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

    $fields['module'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Module'))
      ->setDescription(t('The Module of this status.'))
      ->setSetting('target_type', 'opigno_module');

    $fields['score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Score'))
      ->setDescription(t('The score the user obtained for this Module (percents)'));

    $fields['max_score'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Maximum Score'))
      ->setDescription(t('The maximum score that can be obtained for this Module.'));

    $fields['given_answers'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Given answer count'))
      ->setDescription(t('How many correct answers were given.'));

    $fields['total_questions'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Total questions count'))
      ->setDescription(t('How many answers were in the Module.'));

    $fields['percent'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Pass Percent'))
      ->setDescription('');

    $fields['last_activity'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Last activity'))
      ->setDescription(t('The last activity in a Module that has been answered.'))
      ->setSetting('target_type', 'opigno_activity');

    $fields['current_activity'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Current activity'))
      ->setDescription(t('The activity that is authorized to be answered'))
      ->setSetting('target_type', 'opigno_activity');

    $fields['evaluated'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Evaluation status'))
      ->setDescription(t('A boolean indicating whether the answer is evaluated.'))
      ->setDefaultValue(FALSE);

    $fields['started'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Started'))
      ->setDescription(t('The time that the Module has started.'));

    $fields['finished'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Finished'))
      ->setDescription(t('The time that the Module finished.'));

    $fields['learning_path'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Learning path'))
      ->setDescription(t('The learning path whose context the module was taken in.'))
      ->setSetting('target_type', 'group')
      ->setSetting('target_bundles', ['learning_path' => 'learning_path']);

    // The user_lp_status entity is provided by the opigno_learning_path module.
    // This workaround is needed to fix the order of module enabling on fresh
    // installs.
    if (\Drupal::moduleHandler()->moduleExists('opigno_learning_path')) {
      $fields['lp_status'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Learning path status'))
        ->setDescription(t('The learning path status entity the current module attempt belongs to'))
        ->setSetting('target_type', 'user_lp_status')
        ->setSetting('target_bundles', ['user_lp_status' => 'user_lp_status']);
    }

    return $fields;
  }

}
