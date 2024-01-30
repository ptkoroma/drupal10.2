<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileRepositoryInterface;
use Drupal\opigno_module\Controller\OpignoModuleController;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form controller for Activity edit forms.
 *
 * @ingroup opigno_module
 */
class OpignoActivityForm extends ContentEntityForm {

  /**
   * Taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * Opigno module storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $moduleStorage;

  /**
   * The file storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The Opigno module service.
   *
   * @var \Drupal\opigno_module\Controller\OpignoModuleController
   */
  protected $moduleService;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    RouteMatchInterface $route_match,
    RequestStack $request_stack,
    Messenger $messenger,
    OpignoModuleController $module_service,
    FileRepositoryInterface $file_repository,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->entityTypeManager = $entity_type_manager;
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->moduleStorage = $entity_type_manager->getStorage('opigno_module');
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->moduleHandler = $module_handler;
    $this->routeMatch = $route_match;
    $this->request = $request_stack->getCurrentRequest();
    $this->messenger = $messenger;
    $this->moduleService = $module_service;
    $this->fileRepository = $file_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_route_match'),
      $container->get('request_stack'),
      $container->get('messenger'),
      $container->get('opigno_module.opigno_module'),
      $container->get('file.repository'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $values = $form_state->getValues();
    $auto_skills = FALSE;

    // Add wrapper for ajax.
    $form['#prefix'] = '<div id="activity-wrapper">';
    $form['#suffix'] = '</div>';

    $is_new = $this->getEntity()->isNew();
    $in_skills_system = FALSE;

    $activity = $this->getEntity();
    $skill_id = $activity->getSkillId();
    $params = $this->routeMatch->getParameters();

    // Check if we create/edit activity in the learning path management.
    if (!empty($params->get('opigno_module'))) {
      $module_id = $params->get('opigno_module')->id();
      $module = $this->moduleStorage->load($module_id);
      $in_skills_system = $module->getSkillsActive();
    }

    if ($skill_id) {
      $default_manual = TRUE;
      $parents = $this->termStorage->loadAllParents($skill_id);
      $parents_ids = array_keys($parents);
    }
    else {
      $default_manual = FALSE;
      $parents_ids = [];
    }

    // Hide field 'auto_skills' for all existing activities.
    if (!$is_new
      || !$this->moduleHandler->moduleExists('opigno_skills_system')
      || (isset($module) && !$in_skills_system)
    ) {
      $form['auto_skills']['#access'] = FALSE;
      $auto_skills = $this->getEntity()->get('auto_skills')->getValue()[0]['value'];
    }
    else {
      $form['auto_skills']['widget']['value']['#ajax'] = [
        'method' => 'replace',
        'effect' => 'fade',
        'callback' => '::autoSkillsAjax',
        'wrapper' => 'activity-wrapper',
      ];

      $form['usage_activity']['widget']['#default_value'] = 'global';
    }

    // Check if we creating new activity in skills module.
    if (isset($module) && $in_skills_system && $is_new) {
      $form['auto_skills']['#access'] = FALSE;
      $form['auto_skills']['widget']['value']['#default_value'] = TRUE;
      $auto_skills = TRUE;
    }

    if (!empty($values['auto_skills']['value'])) {
      $auto_skills = $values['auto_skills']['value'];
    }

    // Add 'manual skills management' for activities which is not in the skills
    // system.
    if ((!isset($module_id) || !$module->getSkillsActive()) && ($is_new || !$auto_skills)) {
      $form['manual_skills_management'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Assign a skill to this activity'),
        '#default_value' => $default_manual,
        '#weight' => 1,
        '#ajax' => [
          'method' => 'replace',
          'effect' => 'fade',
          'callback' => '::autoSkillsAjax',
          'wrapper' => 'activity-wrapper',
        ],
      ];
    }

    // Get list of skills trees.
    $target_skills = $this->termStorage->loadTree('skills', 0, 1);
    $default_target_skill = FALSE;
    $options = [];

    if ($target_skills) {
      $default_target_skill = $target_skills[0]->tid;
    }

    foreach ($target_skills as $row) {
      $options[$row->tid] = $row->name;
      if (in_array($row->tid, $parents_ids)) {
        $default_target_skill = $row->tid;
      }
    }

    if ($in_skills_system && !$is_new) {
      $default_target_skill = $module->getTargetSkill();
    }
    else {
      $form['manual_management_tree'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose skills tree'),
        '#options' => $options,
        '#weight' => 1,
        '#default_value' => $default_target_skill ?? '_none',
        '#ajax' => [
          'event' => 'change',
          'callback' => '::autoSkillsAjax',
          'wrapper' => 'activity-wrapper',
        ],
      ];
    }

    $form['skills_list']['widget']['#ajax'] = [
      'event' => 'change',
      'callback' => '::autoSkillsAjax',
      'wrapper' => 'activity-wrapper',
    ];

    $target_skill = $form_state->getValue('manual_management_tree');

    // Get list of skills.
    $form['skills_list']['widget']['#options'] = !empty($target_skill)
      ? $this->getSkillsFromTree($target_skill)
      : $this->getSkillsFromTree($default_target_skill);

    if (isset($values['skills_list'][0])) {
      $selected_skill = $this->termStorage->load($values['skills_list'][0]['target_id']);

      $form['skill_level']['widget']['#default_value'][0] = '0';
      $default_skill_level = $values['skill_level'];
      $default_skill_level[0]['value'] = 'Level 1';
      $form_state->setValue('skill_level', $default_skill_level);
    }
    elseif (isset($form['skills_list']['widget']['#default_value'][0])) {
      $selected_skill = $this->termStorage->load($form['skills_list']['widget']['#default_value'][0]);
    }

    // Remove default options for skill levels except first option.
    $form['skill_level']['widget']['#options'] = [
      1 => $this->t('Level 1'),
    ];

    // Get level names.
    if (isset($selected_skill)) {
      $levels = $selected_skill->get('field_level_names');

      if (isset($levels)) {
        $levels = $levels->getValue();
      }
    }

    if (!empty($levels)) {
      $form['skill_level']['widget']['#options'] = [];

      foreach ($levels as $key => $level) {
        $form['skill_level']['widget']['#options'] += [$key + 1 => $level['value']];
      }
    }

    // Hide fields if needed.
    if (!$auto_skills && ((isset($values['manual_skills_management']) && $values['manual_skills_management'] == 0)
        || (!$activity->getSkillId() && !isset($values['manual_skills_management'])))) {
      $form['manual_management_tree']['#access'] = FALSE;
      $form['skill_level']['#access'] = FALSE;
      $form['skills_list']['#access'] = FALSE;
      $form['usage_activity']['#access'] = FALSE;
    }
    elseif ($auto_skills && $is_new) {
      $form['manual_skills_management']['#access'] = FALSE;
    }
    elseif (isset($values['manual_skills_management']) && $values['manual_skills_management'] == 1) {
      $form['usage_activity']['#access'] = FALSE;
    }

    if ($activity->getType() == 'opigno_h5p') {
      $form['actions']['submit']['#states'] = [
        'disabled' => [
          ':input[name="name[0][value]"]' => ['empty' => TRUE],
        ],
      ];

      $form['#attached']['library'][] = 'opigno_module/activity_validate';
    }

    return $form;
  }

  /**
   * Get needed skills by target skill.
   */
  public function getSkillsFromTree($target_skill) {
    $skills_from_tree = $this->termStorage->loadTree('skills', $target_skill);
    $options = ['_none' => $this->t('- None -')];

    foreach ($skills_from_tree as $row) {
      $options[$row->tid] = $row->name;
    }

    return $options;
  }

  /**
   * Ajax form submit.
   */
  public function autoSkillsAjax(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $activity = &$this->entity;
    // Get URL parameters.
    $params = $this->request->query->all();

    // Save Activity entity.
    $status = parent::save($form, $form_state);

    // Reset usage of activity for activities which not in skills modules.
    $values = $form_state->getValues();
    if ($values['auto_skills']['value'] == 0) {
      $activity->set('usage_activity', 'local');

      if ($values['manual_skills_management'] == 0) {
        $activity->setSkillId(NULL);
      }

      $activity->save();
    }

    // Update video filename.
    if (!empty($values['field_video'][0]['fids'])) {
      $fid = $values['field_video'][0]['fids'][0];
      $file = $this->fileStorage->load($fid);
      $this->renameFile($file);
    }

    if ($status == SAVED_NEW) {
      if (isset($params['module_id']) && !empty($params['module_id'] && $params['module_vid'])) {
        $opigno_module = $this->moduleStorage->load($params['module_id']);
        if ($opigno_module instanceof OpignoModuleInterface) {
          $this->moduleService->activitiesToModule([$activity], $opigno_module);
        }
      }
      $this->messenger->addMessage($this->t('Created the %label Activity.', [
        '%label' => $activity->label(),
      ]));
    }
    else {
      $this->messenger->addMessage($this->t('Saved the %label Activity.', [
        '%label' => $activity->label(),
      ]));
    }
    $form_state->setRedirect('entity.opigno_activity.canonical', ['opigno_activity' => $activity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();

    if (empty($values['name'][0]['value'])) {
      $this->messenger()->addError($this->t('The name field should be set.'));
      $form_state->setError($form['name'], $this->t('The name field is required.'));
    }

    if ($this->moduleHandler->moduleExists('opigno_skills_system')
        && isset($values['manual_skill_management']) && $values['manual_skill_management'] == FALSE) {
      unset($values['skills_list'][0]);
      unset($values['skill_level'][0]);
      $form_state->setValues($values);
    }

    if (!isset($values['uid'][0]['target_id'])) {
      // If the author doesn't exist.
      $values['uid'][0]['target_id'] = 0;
      $form_state->setValues($values);
    }
  }

  /**
   * Change filename.
   */
  protected function renameFile(&$file) {
    if (!empty($file)) {
      $stream_wrapper = StreamWrapperManager::getScheme($file->getFileUri());
      $filename = $file->getFilename();
      $filename_new = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', $filename);
      $file->setFilename($filename_new);
      $file->save();
      $this->fileRepository->move($file, $stream_wrapper . '://' . $filename_new);
    }
  }

}
