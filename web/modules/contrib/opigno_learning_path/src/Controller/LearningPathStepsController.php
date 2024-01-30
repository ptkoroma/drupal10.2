<?php

namespace Drupal\opigno_learning_path\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_group_manager\Controller\OpignoGroupManagerController;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_group_manager\OpignoGroupContentTypesManager;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\opigno_learning_path\Entity\LPStatus;
use Drupal\opigno_learning_path\Form\DeleteAchievementsForm;
use Drupal\opigno_learning_path\LearningPathAccess;
use Drupal\opigno_learning_path\LearningPathValidator;
use Drupal\opigno_learning_path\LearningPathContent;
use Drupal\opigno_learning_path\LPStatusInterface;
use Drupal\opigno_learning_path\Services\LearningPathContentService;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\UserModuleStatusInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the LP steps controller.
 *
 * @package Drupal\opigno_learning_path\Controller
 */
class LearningPathStepsController extends ControllerBase {

  /**
   * The Opigno group content type manager service.
   *
   * @var \Drupal\opigno_group_manager\OpignoGroupContentTypesManager
   */
  protected $contentTypeManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Type of source page.
   *
   * @var string
   */
  protected $sourceType = 'group';

  /**
   * Group entity.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected $group;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    OpignoGroupContentTypesManager $content_types_manager,
    Connection $database,
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    TimeInterface $time,
    DateFormatterInterface $date_formatter,
    RequestStack $request_stack
  ) {
    $this->contentTypeManager = $content_types_manager;
    $this->database = $database;
    $this->currentUser = $account;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_group_manager.content_types.manager'),
      $container->get('database'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('request_stack')
    );
  }

  /**
   * Redirect to the group homepage.
   *
   * @param bool $is_ajax
   *   Is ajax request or not.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|false|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   */
  protected function redirectToHome(bool $is_ajax = FALSE) {
    if (!empty($this->group) && !empty($this->sourceType) && $this->sourceType == 'catalog') {
      if ($is_ajax) {
        $url = Url::fromRoute('entity.group.canonical', [
          'group' => $this->group->id(),
          'force' => 1,
        ]);
        return (new AjaxResponse())->addCommand(new RedirectCommand($url->toString()));
      }
      else {
        return $this->redirect('entity.group.canonical', [
          'group' => $this->group->id(),
          'force' => 1,
        ]);
      }
    }
    return FALSE;
  }

  /**
   * Provide a default failed messages for the learning path.
   *
   * @param string $type
   *   The message type.
   * @param bool $modal
   *   Should the message be displayed in modal or not.
   * @param string $message
   *   The message text.
   *
   * @return mixed
   *   The render array to display the message.
   */
  protected function failedStep(string $type, bool $modal = FALSE, string $message = '') {
    switch ($type) {
      case 'no_first':
        $message = $this->t('No first step assigned.');
        break;

      case 'no_url':
        $message = $this->t('No URL for the first step.');
        break;

      case 'no_score':
        $message = $this->t('No score provided');
        break;

      case 'passed':
        $message = $this->t('You passed!');
        break;

      case 'failed':
        $message = $this->t('You failed!');
        break;
    }

    if ($type !== 'none' && $redirect = $this->redirectToHome($modal)) {
      return $redirect;
    }

    $content = [
      '#type' => 'html_tag',
      '#value' => $message,
      '#tag' => 'p',
    ];

    if ($modal) {
      return $this->showPopup($content);
    }
    else {
      return $content;
    }
  }

  /**
   * Show the AJAX popup.
   *
   * @param array $content
   *   The popup content.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response to show the popup.
   */
  public function showPopup(array $content): AjaxResponse {
    $build = [
      '#theme' => 'opigno_confirmation_popup',
      '#body' => $content,
    ];

    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $build));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));

    return $response;
  }

  /**
   * Start the learning path.
   *
   * This page will redirect the user to the first learning path content.
   */
  public function start(Group $group, $type = 'group') {
    // Create empty result attempt if current attempt doesn't exist.
    // Will be used to detect if user already started LP or not.
    $current_attempt = LPStatus::getCurrentLpAttempt($group, $this->currentUser);

    $visibility = $group->get('field_learning_path_visibility')->value;
    $this->sourceType = $type;
    $this->group = $group;

    if (!$current_attempt) {
      // Create training new attempt.
      $current_attempt = LPStatus::create([
        'uid' => $this->currentUser->id(),
        'gid' => $group->id(),
        'status' => 'in progress',
        'started' => $this->time->getRequestTime(),
        'finished' => 0,
      ]);
      $current_attempt->save();
    }
    assert(TRUE, 'Current attempt is never used.');

    $user = $this->currentUser();

    $uid = $user->id();
    $gid = $group->id();
    $is_owner = $uid === $group->getOwnerId();

    $is_ajax = $this->request->isXmlHttpRequest();

    // Load group steps.
    $steps = LearningPathContent::getAllStepsOnlyModules($gid, $uid);

    $steps = array_filter($steps, static function ($step) use ($user) {
      return LearningPathContentService::filterStep($step, $user);
    });
    $steps = array_values($steps);

    foreach ($steps as $step) {
      if (in_array($step['typology'], ['Meeting', 'ILT'])) {
        // If training starts with a mandatory live meeting
        // or instructor-led training, check requirements.
        $is_mandatory = $step['mandatory'] === 1
          && LearningPathContentService::filterStep($step, $user);

        if ($is_mandatory) {
          $name = $step['name'];
          $required = $step['required score'];
          if ($required >= 0 || ($step['typology'] == 'Meeting' && !$is_owner)) {
            if ($step['best score'] < $required || OpignoGroupManagerController::mustBeVisitedMeeting($step, $group)) {
              $course_entity = OpignoGroupManagedContent::load($step['cid']);
              $course_content_type = $this->contentTypeManager->createInstance(
                $course_entity->getGroupContentTypeId()
              );

              $current_step_url = $course_content_type->getStartContentUrl(
                $course_entity->getEntityId(),
                $gid
              );

              return $this->failedStep('none', $is_ajax, $this->requiredStepMessage($name, $required, $current_step_url->toString()));
            }
          }
          else {
            if ($step['attempts'] === 0) {
              return $this->failedStep('none', $is_ajax, $this->requiredStepMessage($name));
            }
          }
        }
      }
    }

    // Check that training is completed.
    $is_completed = (int) $this->database
      ->select('opigno_learning_path_achievements', 'a')
      ->fields('a')
      ->condition('uid', $user->id())
      ->condition('gid', $group->id())
      ->condition('status', 'completed')
      ->countQuery()
      ->execute()
      ->fetchField() > 0;

    if ($is_completed) {
      // Load steps from cache table.
      $results = $this->database
        ->select('opigno_learning_path_step_achievements', 'a')
        ->fields('a', [
          'id',
          'typology',
          'entity_id',
          'parent_id',
          'position',
        ])
        ->condition('uid', $user->id())
        ->condition('gid', $group->id())
        ->execute()
        ->fetchAllAssoc('id');

      if (!empty($results)) {
        // Check training structure.
        $is_valid = TRUE;
        $steps_mandatory = array_filter($steps, function ($step) {
          return $step['mandatory'];
        });
        foreach ($steps_mandatory as $step) {
          $filtered = array_filter($results, function ($result) use ($results, $step) {
            if (isset($step['parent'])) {
              $step_parent = $step['parent'];
              $result_parent = $results[$result->parent_id] ?? NULL;
              if (!isset($result_parent)
                || $result_parent->typology !== $step_parent['typology']
                || (int) $result_parent->entity_id !== (int) $step_parent['id']
                || (int) $result_parent->position !== (int) $step_parent['position']) {
                return FALSE;
              }
            }

            return $result->typology === $step['typology']
              && (int) $result->entity_id === (int) $step['id']
              && (int) $result->position === (int) $step['position'];
          });

          if (count($filtered) !== 1) {
            $is_valid = FALSE;
            break;
          }
        }

        // If training is changed.
        if (!$is_valid) {
          $form_state = new FormState();
          $form_state->addBuildInfo('args', [$group]);
          $form = $this->formBuilder()->buildForm(DeleteAchievementsForm::class, $form_state);
          if ($is_ajax) {
            $redirect = $this->redirectToHome(TRUE);
            if ($redirect) {
              return $redirect;
            }
            else {
              return $this->showPopup($form);
            }
          }
          else {
            return $form;
          }
        }
      }
    }

    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);
    if ($freeNavigation) {
      $content = OpignoGroupManagedContent::getFirstStep($group->id());
      if ($content instanceof OpignoGroupManagedContent && $content->getGroupContentTypeId() != 'ContentTypeCourse') {
        $content_type = $this->contentTypeManager->createInstance($content->getGroupContentTypeId());
        $step_url = $content_type->getStartContentUrl($content->getEntityId(), $group->id());
        if ($is_ajax) {
          return (new AjaxResponse())->addCommand(new RedirectCommand($step_url->toString()));
        }
        else {
          return $this->redirect($step_url->getRouteName(), $step_url->getRouteParameters(), $step_url->getOptions());
        }
      }
    }

    // Check if there is resumed step. If is - redirect.
    // We disallow resuming for anonymous user if a training is public.
    $step_resumed_cid = ($visibility === 'public' && $uid == 0) ? FALSE : opigno_learning_path_resumed_step($steps);
    if ($step_resumed_cid) {
      $content = OpignoGroupManagedContent::load($step_resumed_cid);
      if ($content instanceof OpignoGroupManagedContent && in_array($content->getGroupContentTypeId(), [
        'ContentTypeMeeting',
        'ContentTypeILT',
      ])) {
        for ($i = 0; $i < count($steps); ++$i) {
          if (intval($steps[$i]['cid']) == $step_resumed_cid) {
            // Set resumed step which is next to the meeting.
            $step_resumed_cid = $steps[$i + 1]['cid'];
            $content = OpignoGroupManagedContent::load($step_resumed_cid);
            break;
          }
        }
      }

      if ($content instanceof OpignoGroupManagedContent) {
        // Find and load the content type linked to this content.
        $content_type = $this->contentTypeManager->createInstance($content->getGroupContentTypeId());
        $step_url = $content_type->getStartContentUrl($content->getEntityId(), $group->id());
        // Before redirecting, keep the content ID in context.
        OpignoGroupContext::setCurrentContentId($step_resumed_cid);
        OpignoGroupContext::setGroupId($group->id());
        // Finally, redirect the user to the first step.
        if ($is_ajax) {
          return (new AjaxResponse())->addCommand(new RedirectCommand($step_url->toString()));
        }
        else {
          return $this->redirect($step_url->getRouteName(), $step_url->getRouteParameters(), $step_url->getOptions());
        }
      }
    }

    // Get the first step of the learning path. If no steps, show a message.
    $first_step = reset($steps);
    if ($first_step === FALSE) {
      return $this->failedStep('no_first', $is_ajax);
    }

    // Load first step entity.
    $first_step = OpignoGroupManagedContent::load($first_step['cid']);

    // Find and load the content type linked to this content.
    $content_type = $this->contentTypeManager->createInstance($first_step->getGroupContentTypeId());

    // Finally, get the "start" URL
    // If no URL, show a message.
    $step_url = $content_type->getStartContentUrl($first_step->getEntityId(), $group->id());
    if (empty($step_url)) {
      return $this->failedStep('no_url', $is_ajax);
    }

    // Before redirecting, keep the content ID in context.
    OpignoGroupContext::setCurrentContentId($first_step->id());
    OpignoGroupContext::setGroupId($group->id());

    // Finally, redirect the user to the first step.
    if ($is_ajax) {
      return (new AjaxResponse())->addCommand(new RedirectCommand($step_url->toString()));
    }
    else {
      return $this->redirect($step_url->getRouteName(), $step_url->getRouteParameters(), $step_url->getOptions());
    }
  }

  /**
   * Restarts the LP attempt for the user.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The LP group to create an attempt for.
   * @param string $type
   *   The page type, needed for the "opigno_learning_path.steps.type_start"
   *   route.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function restart(GroupInterface $group, $type = 'group'): AjaxResponse {
    $response = new AjaxResponse();
    $gid = $group->id();
    $storage = $this->entityTypeManager->getStorage('user_lp_status');
    $unfinished_attempts = $storage->loadByProperties([
      'uid' => $this->currentUser->id(),
      'gid' => $gid,
      'finalized' => 0,
    ]);

    // Finish all unfinished LP attempts if they're passed or failed.
    $error = [];
    $timestamp = $this->time->getRequestTime();
    if ($unfinished_attempts) {
      $finished_statuses = ['passed', 'failed'];
      foreach ($unfinished_attempts as $attempt) {
        if ($attempt instanceof LPStatusInterface && in_array($attempt->getStatus(), $finished_statuses)) {
          $attempt->setFinished($timestamp)->save();
        }
        else {
          $error[] = $attempt;
        }
      }
    }

    // Create a new LP attempt.
    if (!$error) {
      // Close unfinished module attempts for the LP if there are any.
      $unfinished_module_attempts = $this->entityTypeManager
        ->getStorage('user_module_status')
        ->loadByProperties([
          'user_id' => $this->currentUser->id(),
          'learning_path' => $gid,
          'finished' => 0,
        ]);
      if ($unfinished_module_attempts) {
        foreach ($unfinished_module_attempts as $module_attempt) {
          if ($module_attempt instanceof UserModuleStatusInterface) {
            $module_attempt->setFinished($timestamp)->save();
          }
        }
      }

      $uid = $this->currentUser->id();
      $gid = $group->id();
      $new_attempt = $storage->create([
        'uid' => $uid,
        'gid' => $gid,
        'status' => 'in progress',
        'started' => $timestamp,
        'finished' => 0,
      ]);
      $new_attempt->save();

      // Update the record in LP achievements table.
      $this->database->update('opigno_learning_path_achievements')
        ->fields([
          'status' => 'pending',
          'score' => 0,
          'progress' => 0,
          'time' => 0,
          'registered' => DrupalDateTime::createFromTimestamp($timestamp)->format(DrupalDateTime::FORMAT),
          'completed' => NULL,
        ])
        ->condition('uid', $uid)
        ->condition('gid', $gid)
        ->execute();
    }

    // Redirect to the LP start route.
    $redirect_url = Url::fromRoute('opigno_learning_path.steps.type_start',
      ['group' => $gid, 'type' => $type]
    )->toString();
    $response->addCommand(new RedirectCommand($redirect_url));

    return $response;
  }

  /**
   * Redirect the user to the next step.
   */
  public function getNextStep(Group $group, OpignoGroupManagedContent $parent_content, $content_update = TRUE) {
    // Get the user score of the parent content.
    // First, get the content type object of the parent content.
    $content_type = $this->contentTypeManager->createInstance($parent_content->getGroupContentTypeId());
    $user_score = $content_type->getUserScore($this->currentUser->id(), $parent_content->getEntityId());

    // If no no score and content is mandatory, show a message.
    if ($user_score === FALSE && $parent_content->isMandatory()) {
      return $this->failedStep('no_score');
    }

    $user = $this->currentUser();
    $uid = $user->id();
    $gid = $group->id();
    $is_owner = $uid === $group->getOwnerId();
    $cid = $parent_content->id();

    // Get training guided navigation option.
    $freeNavigation = !OpignoGroupManagerController::getGuidedNavigation($group);

    // Load group steps.
    if ($freeNavigation) {
      // Get all steps for LP.
      $steps = LearningPathContent::getAllStepsOnlyModules($gid, $uid, TRUE);
    }
    else {
      $steps = LearningPathContent::getAllStepsOnlyModules($gid, $uid);
    }

    $steps = array_filter($steps, static function ($step) use ($user) {
      return LearningPathContentService::filterStep($step, $user);
    });
    $steps = array_values($steps);

    // Find current & next step.
    $count = count($steps);
    $current_step = NULL;
    $current_step_index = 0;
    for ($i = 0; $i < $count - 1; ++$i) {
      if ($steps[$i]['cid'] === $cid || (!$freeNavigation && ($steps[$i]['required score'] > $steps[$i]['best score']))) {
        $current_step_index = $i;
        $current_step = $steps[$i];
        break;
      }
    }

    // Check mandatory step requirements.
    if (!$freeNavigation && isset($current_step) && $current_step['mandatory'] === 1) {
      $name = $current_step['name'];
      $required = $current_step['required score'];
      if ($required >= 0 || $current_step['typology'] == 'Meeting') {
        // Check if it's "skills module" with skills which user is already
        // passed.
        if ($current_step['typology'] == 'Module') {
          $module = $this->entityTypeManager
            ->getStorage('opigno_module')
            ->load($current_step['id']);

          if ($this->moduleHandler->moduleExists('opigno_skills_system') && $module->getSkillsActive()) {
            $current_step['current attempt score'] = $current_step['best score'];
          }
        }

        if (($current_step['mandatory'] && !$current_step['attempts']) ||
          ($current_step['mandatory'] && $current_step['attempts'] && empty($current_step['completed on'])) ||
          $current_step['best score'] < $required ||
          OpignoGroupManagerController::mustBeVisitedMeeting($current_step, $group) && !$is_owner) {

          $course_entity = OpignoGroupManagedContent::load($current_step['cid']);
          $course_content_type = $this->contentTypeManager->createInstance(
            $course_entity->getGroupContentTypeId()
          );
          $current_step_url = $course_content_type->getStartContentUrl(
            $course_entity->getEntityId(),
            $gid
          );

          // Message if current step score less than required.
          $message = $this->requiredStepMessage($name, $required, $current_step_url->toString());
          $message = $this->failedStep('none', FALSE, $message);

          // Check if current step is module and has activities
          // with manual evaluation which haven't been evaluated yet.
          if ($current_step['typology'] == 'Module') {
            $module = OpignoModule::load($current_step['id']);
            if (!empty($module)) {
              $activities = $module->getModuleActivities();
              $activities = array_map(function ($activity) {
                return OpignoActivity::load($activity->id);
              }, $activities);

              $attempts = $module->getModuleAttempts($user);
              if (!empty($attempts)) {
                // If "newest" score - get the last attempt,
                // else - get the best attempt.
                $attempt = $this->getTargetAttempt($attempts, $module);
              }
              else {
                $attempt = NULL;
              }

              if ($activities) {
                foreach ($activities as $activity) {
                  $answer = isset($attempt) ? $activity->getUserAnswer($module, $attempt, $user) : NULL;
                  if ($answer && $activity->hasField('opigno_evaluation_method') && $activity->get('opigno_evaluation_method')->value && !$answer->isEvaluated()) {
                    // Message if current step is module and has activities
                    // with manual evaluation which haven't been evaluated yet.
                    $training_url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()]);
                    $message = $this->t('One or several activities in module %step require a manual grading. You will be allowed to continue the training as soon as these activities have been graded and if you reach the minimum score %required.<br /><a href=":link">Back to training homepage.</a>', [
                      '%step' => $name,
                      '%required' => $required,
                      ':link' => $training_url->toString(),
                    ]);

                    $message = $this->failedStep('none', FALSE, $message);
                    break;
                  }
                }
              }
            }
          }
          if ($content_update) {
            $this->setGroupAndContext($group->id(), $current_step['cid']);
          }
          return $message;
        }
      }
    }

    if (isset($current_step['is last child']) && $current_step['is last child']
      && isset($current_step['parent'])) {
      $course = $current_step['parent'];
      // Check mandatory course requirements.
      if ($course['mandatory'] === 1) {
        $name = $course['name'];
        $required = $course['required score'];
        if ($required >= 0) {
          if ($course['best score'] < $required) {
            $module_content = OpignoGroupManagedContent::getFirstStep($course['id']);
            $module_content_type = $this->contentTypeManager->createInstance(
              $module_content->getGroupContentTypeId()
            );
            $module_url = $module_content_type->getStartContentUrl(
              $module_content->getEntityId(),
              $gid
            );

            if ($content_update) {
              $this->setGroupAndContext($group->id(), $module_content->id());
            }
            return $this->failedStep('none', FALSE, $this->requiredStepMessage($name, $required, $module_url->toString()));

          }
        }
        else {
          if ($course['attempts'] === 0) {
            $module_content = OpignoGroupManagedContent::getFirstStep($course['id']);

            if ($content_update) {
              $this->setGroupAndContext($group->id(), $module_content->id());
            }
            return $this->failedStep('none', FALSE, $this->requiredStepMessage($name));
          }
        }
      }
    }

    $next_step = $steps[$current_step_index + 1];
    $is_mandatory = $current_step
      && $current_step['mandatory'] === 1
      && LearningPathContentService::filterStep($current_step, $user);

    if ($is_mandatory) {
      $name = $current_step['name'];
      $required = $current_step['required score'];
      // But if the live meeting or instructor-led training is
      // a mandatory and not passed,
      // block access to the next step.
      if ($required > 0) {
        if ($current_step['best score'] < $required) {
          return $this->failedStep('none', FALSE, $this->requiredStepMessage($name, $required));
        }
      }
      else {
        if ($current_step['attempts'] === 0) {
          return $this->failedStep('none', FALSE, $this->requiredStepMessage($name));
        }
      }
    }

    return $next_step ?? NULL;
  }

  /**
   * Sets a groupd and content.
   */
  public function setGroupAndContext($group_id, $content_id) {
    OpignoGroupContext::setGroupId($group_id);
    OpignoGroupContext::setCurrentContentId($content_id);
  }

  /**
   * Redirect the user to the next step.
   */
  public function nextStep(Group $group, OpignoGroupManagedContent $parent_content, $content_update = TRUE) {
    $next_step = $this->getNextStep($group, $parent_content, $content_update);
    // If there is no next step, show a message.
    if ($next_step === NULL) {
      // Redirect to training home page.
      $this->messenger()->addWarning($this->t('You reached the last content of that training.'));
      return $this->redirect('entity.group.canonical', ['group' => $group->id()]);
    }
    if (!isset($next_step['cid'])) {
      return $next_step;
    }
    // Load next step entity.
    $next_step = OpignoGroupManagedContent::load($next_step['cid']);

    // Before redirect, change the content context.
    if ($content_update) {
      $this->setGroupAndContext($group->id(), $next_step->id());
    }
    // Finally, redirect the user to the next step URL.
    $next_step_content_type = $this->contentTypeManager->createInstance($next_step->getGroupContentTypeId());
    $next_step_url = $next_step_content_type->getStartContentUrl($next_step->getEntityId(), $group->id());
    return $this->redirect($next_step_url->getRouteName(), $next_step_url->getRouteParameters(), $next_step_url->getOptions());
  }

  /**
   * Show the finish page and save the score.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @deprecated in opigno:3.0.9 and is removed from opigno:3.1.0. The method is never used.
   * @see https://www.drupal.org/project/opigno/issues/3090004
   *
   * @todo Remove this method in opigno:3.1.0.
   */
  public function finish(Group $group) {
    // Get the "user passed" status.
    $current_uid = $this->currentUser->id();
    $user_passed = LearningPathValidator::userHasPassed($current_uid, $group);
    $user_status = $user_passed ? 'passed' : 'failed';
    if ($user_passed) {
      $latest_attempt = LPStatus::getCurrentLpAttempt(
        $group,
        $this->currentUser,
        TRUE,
        TRUE
      );
      // Legacy code refactored.
      if ($latest_attempt !== FALSE) {
        $this->updateResultAttempt($latest_attempt, $user_status);
      }
      else {
        $this->createResultAttempt($group, $user_status);
      }
      return $this->failedStep('passed');
    }
    else {
      return $this->failedStep('failed');
    }
  }

  /**
   * Update and finalize the result attempt.
   *
   * @param bool|\Drupal\opigno_learning_path\Entity\LPStatus $attempt
   *   The attempt.
   * @param string $user_status
   *   The user status.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @deprecated in opigno:3.0.9 and is removed from opigno:3.1.0. The method is never used.
   * @see https://www.drupal.org/project/opigno/issues/3090004
   */
  public function updateResultAttempt(LPStatus $attempt, string $user_status): void {
    $training_score = opigno_learning_path_get_score($attempt->getTrainingId(), $attempt->getUserId(), TRUE);
    $attempt->setScore($training_score);
    $attempt->setStatus($user_status);
    $attempt->setFinished($this->time->getRequestTime());
    $attempt->save();
  }

  /**
   * Create result attempt.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The group entity.
   * @param string $user_status
   *   The user status.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @deprecated in opigno:3.0.9 and is removed from opigno:3.1.0. The method is never used.
   * @see https://www.drupal.org/project/opigno/issues/3090004
   */
  public function createResultAttempt(Group $group, string $user_status): void {
    // Store the result in database.
    // @todo the attempt should be exist on this step,
    // otherwise we will not able to calculate the start date.
    $result = LPStatus::create([
      'uid' => $this->currentUser->id(),
      'gid' => $group->id(),
      'status' => $user_status,
      'started' => $this->time->getRequestTime(),
      'finished' => $this->time->getRequestTime(),
    ]);
    $result->save();
  }

  /**
   * Steps.
   */
  public function contentSteps(Group $group, $current) {
    // Check if user has uncompleted steps.
    LearningPathValidator::stepsValidate($group);
    // Get group type.
    $type = opigno_learning_path_get_group_type();
    // Get all steps.
    $all_steps = opigno_learning_path_get_routes_steps();
    // Get unique steps numbers.
    $unique_steps = array_unique($all_steps);
    // Get next step.
    $next_step = ($current < count($unique_steps)) ? $current + 1 : NULL;
    // If last step.
    if (!$next_step) {
      if ($type == 'learning_path') {
        return $this->redirect('opigno_learning_path.manager.publish', ['group' => $group->id()]);
      }
      else {
        // For courses and classes.
        return $this->redirect('entity.group.canonical', ['group' => $group->id()]);
      }
    }
    // If not last step.
    else {
      if ($type == 'learning_path') {
        // Check for existing courses in the LP.
        // If there are no courses - skip courses step.
        $group_courses = $group->getContent('subgroup:opigno_course');
        if ($current == 2 && empty($group_courses)) {
          $next_step++;
        }
      }
      // For all group types.
      $route = array_search($next_step, opigno_learning_path_get_routes_steps());
      return $this->redirect($route, ['group' => $group->id()]);
    }
  }

  /**
   * Steps list.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The group to get steps for.
   *
   * @return array
   *   The list of steps.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function listSteps(Group $group): array {
    $date_formatter = $this->dateFormatter;

    $group_id = $group->id();
    $uid = $this->currentUser->id();

    $steps = opigno_learning_path_get_steps($group_id, $uid);

    $rows = array_map(function ($step) use ($date_formatter) {
      return [
        $step['name'],
        $step['typology'],
        $date_formatter->formatInterval($step['time spent']),
        $step['best score'],
      ];
    }, $steps);

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Typology'),
        $this->t('Total time spent'),
        $this->t('Best score achieved'),
      ],
      '#rows' => $rows,
    ];
  }

  /**
   * Check if the user has access to any next content from the Learning Path.
   */
  public function nextStepAccess(Group $group, OpignoGroupManagedContent $parent_content) {
    // Check if there is a next step and if the user has access to it.
    // Get the user score of the parent content.
    // First, get the content type object of the parent content.
    $content_type = $this->contentTypeManager->createInstance($parent_content->getGroupContentTypeId());
    $user_score = $content_type->getUserScore($this->currentUser->id(), $parent_content->getEntityId());

    // If no no score and content is mandatory, return forbidden.
    if ($user_score === FALSE && $parent_content->isMandatory()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Check if the user has access to start the Learning Path.
   */
  public function startAccess(Group $group) {
    if ($group->bundle() !== 'learning_path') {
      return AccessResult::neutral();
    }

    $user = $this->currentUser();
    $group_visibility = $group->get('field_learning_path_visibility')->getValue()[0]['value'];

    if ($user->isAnonymous() && $group_visibility != 'public') {
      return AccessResult::forbidden();
    }

    $access = LearningPathAccess::statusGroupValidation($group, $user);
    if ($access === FALSE) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Get last or best user attempt for Module.
   *
   * @param array $attempts
   *   User module attempts.
   * @param \Drupal\opigno_module\Entity\OpignoModule $module
   *   Module.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatus
   *   $attempt
   */
  protected function getTargetAttempt(array $attempts, OpignoModule $module) {
    if ($module->getKeepResultsOption() == 'newest') {
      $attempt = end($attempts);
    }
    else {
      $attempt = opigno_learning_path_best_attempt($attempts);
    }

    return $attempt;
  }

  /**
   * Provide the required step messages.
   *
   * @param string $name
   *   Step name.
   * @param int|null $required
   *   Minimum score.
   * @param string $link
   *   Link to try again.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  protected function requiredStepMessage(string $name, ?int $required = NULL, string $link = ''): TranslatableMarkup {
    if (empty($required)) {
      // The simple message.
      return $this->t('A required step: %step should be done first.', [
        '%step' => $name,
      ]);
    }
    else {
      if (!empty($link)) {
        return $this->t("You should first get a minimum score of %required to the step %step before going further. <a href=':link'>Try again.</a>", [
          '%step' => $name,
          '%required' => $required,
          ':link' => $link,
        ]);
      }
      else {
        return $this->t('You should first get a minimum score of %required to the step %step before going further.', [
          '%step' => $name,
          '%required' => $required,
        ]);
      }
    }
  }

}
