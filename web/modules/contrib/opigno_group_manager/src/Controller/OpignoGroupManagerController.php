<?php

namespace Drupal\opigno_group_manager\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\h5p\Entity\H5PContent;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_group_manager\Entity\OpignoGroupManagedLink;
use Drupal\opigno_group_manager\OpignoGroupContentTypesManager;
use Drupal\opigno_group_manager\OpignoGroupContext;
use Drupal\opigno_learning_path\LearningPathValidator;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Drupal\opigno_moxtra\Entity\Workspace;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\Serializer;

/**
 * Controller for all the actions of the Opigno group manager app.
 *
 * @package Drupal\opigno_group_manager\Controller
 */
class OpignoGroupManagerController extends ControllerBase {

  /**
   * Opigno group content type manager.
   *
   * @var \Drupal\opigno_group_manager\OpignoGroupContentTypesManager
   */
  protected $contentTypesManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The temporary storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tmpStore;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The Drupal time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * The media storage.
   *
   * @var \Drupal\media\MediaStorage
   */
  protected $mediaStorage;

  /**
   * The file storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * OpignoGroupManagerController constructor.
   *
   * @param \Drupal\opigno_group_manager\OpignoGroupContentTypesManager $content_types_manager
   *   The Opigno group content types manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tmp_store
   *   The temporary file storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Drupal time service.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    OpignoGroupContentTypesManager $content_types_manager,
    Connection $database,
    MessengerInterface $messenger,
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request,
    ModuleHandlerInterface $module_handler,
    PrivateTempStoreFactory $tmp_store,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    AccountInterface $account,
    TimeInterface $time,
    Serializer $serializer,
    FormBuilderInterface $form_builder
  ) {
    $this->contentTypesManager = $content_types_manager;
    $this->database = $database;
    $this->messenger = $messenger;
    $this->loggerFactory = $logger_factory;
    $this->logger = $logger_factory->get('opigno_group_manager');
    $this->request = $request->getCurrentRequest();
    $this->moduleHandler = $module_handler;
    $this->tmpStore = $tmp_store;
    $this->entityTypeManager = $entity_type_manager;
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->mediaStorage = $entity_type_manager->getStorage('media');
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->fileSystem = $file_system;
    $this->currentUser = $account;
    $this->time = $time;
    $this->serializer = $serializer;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_group_manager.content_types.manager'),
      $container->get('database'),
      $container->get('messenger'),
      $container->get('logger.factory'),
      $container->get('request_stack'),
      $container->get('module_handler'),
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('serializer'),
      $container->get('form_builder')
    );
  }

  /**
   * Root page for angular app.
   */
  public function index(Group $group, Request $request) {
    // Check if user has uncompleted steps.
    LearningPathValidator::stepsValidate($group);

    if ($group instanceof GroupInterface) {
      $current_step = opigno_learning_path_get_current_step();
      $next_step = ($current_step < 5) ? $current_step + 1 : NULL;
      $link_text = !$next_step ? $this->t('Publish') : $this->t('Next');
      $next_link = Link::createFromRoute(Markup::create($link_text . '<i class="fi fi-rr-angle-small-right"></i>'), 'opigno_learning_path.content_steps', [
        'group' => $group->id(),
        'current' => ($current_step) ? $current_step : 0,
      ], [
        'attributes' => [
          'class' => [
            'btn',
            'btn-rounded',
          ],
        ],
      ])->toString();
    }

    return [
      '#theme' => 'opigno_group_manager',
      '#attached' => ['library' => ['opigno_group_manager/manage_app']],
      '#base_path' => $request->getBasePath(),
      '#base_href' => $request->getPathInfo(),
      '#group_id' => $group->id(),
      '#next_link' => $next_link ?? NULL,
      '#user_has_info_card' => !$this->tmpStore->get('opigno_group_manager')->get('hide_info_card'),
      '#text_add_a_link' => $this->t('add a link')->render(),
      '#text_add_your_first_item' => $this->t('Add your first item')->render(),
      '#text_guided_navigation' => $this->t('Guided navigation')->render(),
      '#text_free_navigation' => $this->t('Free navigation')->render(),
      '#text_if_entity_dropped_under_children' => $this->t('Cannot drop under or at the same level as children, move children before')->render(),
      '#text_cannot_drop_over_or_at_the_same_level' => $this->t('Cannot drop over or at the same level as parent')->render(),
      '#text_click_now_on_the_two_steps_of_your_training' => $this->t('Click now on the two steps of your training to be linked in order to create the link')->render(),
      '#text_status_message' => $this->t('Status message')->render(),
      '#text_conditions' => $this->t('Conditions')->render(),
      '#text_score' => $this->t('Score:')->render(),
      '#text_edit' => $this->t('edit')->render(),
      '#text_delete' => $this->t('delete')->render(),
      '#text_last_step' => $this->t('Last step:')->render(),
      '#text_add' => $this->t('add')->render(),
      '#text_validate' => $this->t('Validate')->render(),
      '#text_cancel' => $this->t('cancel')->render(),
      '#text_delete_link' => $this->t('delete link')->render(),
      '#text_this_action_create_an_orphan' => $this->t('This action create an orphan, confirm:')->render(),
      '#text_confirm' => $this->t('confirm')->render(),
      '#language_prefix' => opigno_group_manager_get_language_prefix(),
    ];
  }

  /**
   * Returns next link.
   */
  public function getNextLink(Group $group) {
    $next_link = NULL;

    if ($group instanceof GroupInterface) {
      $current_step = opigno_learning_path_get_current_step();
      $next_step = ($current_step < 5) ? $current_step + 1 : NULL;
      $link_text = !$next_step ? $this->t('Publish') : $this->t('Next');
      $next_link = Link::createFromRoute(Markup::create($link_text . '<i class="fi fi-rr-angle-small-right"></i>'), 'opigno_learning_path.content_steps', [
        'group' => $group->id(),
        'current' => ($current_step) ? $current_step : 0,
      ], [
        'attributes' => [
          'class' => [
            'btn',
            'btn-rounded',
          ],
        ],
      ])->toRenderable();
    }

    return $next_link;
  }

  /**
   * Method called when the LP manager needs a create or edit form.
   */
  public function getItemForm(Group $group, $type = NULL, $item = 0) {
    // Get the good form from the corresponding content type.
    $content_type = $this->contentTypesManager->createInstance($type);
    $form = $content_type->getFormObject($item);

    if ($type === 'ContentTypeModule'
      && $item !== NULL
      && ($module = OpignoModule::load($item)) !== NULL) {
      /** @var \Drupal\opigno_module\Entity\OpignoModuleInterface $module */
      if (!$module->access('update')) {
        throw new AccessDeniedHttpException();
      }
    }
    elseif ($type === 'ContentTypeCourse'
      && $item !== NULL
      && ($group = Group::load($item)) !== NULL) {
      /** @var \Drupal\group\Entity\GroupInterface $group */
      if (!$group->access('update')) {
        throw new AccessDeniedHttpException();
      }
    }

    // Add parent learning path id to the form
    // in the case if current group is a course.
    $keys = $this->request->query->keys();
    if ($keys && in_array('learning_path', $keys)) {
      $learning_path = $this->request->query->get('learning_path');
    }

    // Adds some information used
    // in the method opigno_learning_path_form_alter().
    return $this->formBuilder->getForm($form, [
      'opigno_group_info' => [
        'group_id' => ($group) ? $group->id() : NULL,
        'opigno_group_content_type' => $type,
        'learning_path' => $learning_path ?? NULL,
      ],
    ]);
  }

  /**
   * Form ajax callback.
   */
  public static function ajaxFormEntityCallback(&$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If errors, returns the form with errors and messages.
    if ($form_state->hasAnyErrors()) {
      // Add class for displaying form errors in iframe.
      $form['#attributes']['class'][] = 'lp-content-item-errors';
      return $form;
    }

    $entity = $form_state->getBuildInfo()['callback_object']->getEntity();
    // Load image.
    if ($entity->hasField('field_course_media_image')) {
      $image_field = 'field_course_media_image';
    }
    elseif ($entity->hasField('module_media_image')) {
      $image_field = 'module_media_image';
    }

    if ($entity->hasField($image_field)) {
      $media = $entity->get($image_field)->entity;
      $file = isset($media)
        ? File::load($media->get('field_media_image')->target_id)
        : NULL;
    }

    $route_match = \Drupal::routeMatch();
    $item = [];
    $item['cid'] = $entity->id();
    $item['contentType'] = $item['entityBundle'] = $route_match->getParameter('type');
    $item['entityId'] = $entity->id();
    $item['title'] = $entity->label();
    $item['imageUrl'] = $file
      ? \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri())
      : static::getDefaultBundleImageUrl($entity->bundle());
    $item['in_skills_system'] = FALSE;
    $item['isMandatory'] = FALSE;

    if ($item['contentType'] === 'ContentTypeModule') {
      $item['in_skills_system'] = $entity->getSkillsActive();
      $group = $route_match->getParameter('group');

      $managed_contents = OpignoGroupManagedContent::loadByProperties([
        'group_content_type_id' => 'ContentTypeModule',
        'group_id' => $group->id(),
        'entity_id' => $entity->id(),
      ]);

      if (!empty($managed_contents)) {
        $content = reset($managed_contents);
        $item['isMandatory'] = $content->isMandatory();
      }
    }

    $messenger = \Drupal::messenger();
    $response->addCommand(
      new SettingsCommand([
        'formValues' => $item,
        'messages' => $messenger->all(),
      ], TRUE)
    );

    $messenger->deleteAll();

    return $response;
  }

  /**
   * Submit handler added by opigno_learning_path_form_alter().
   *
   * @see opigno_learning_path_form_alter()
   */
  public static function ajaxFormEntityFormSubmit($form, FormState &$form_state) {
    // Gets back the content type and learning path id.
    $build_info = $form_state->getBuildInfo();
    foreach ($build_info['args'][0] as $arg_key => $arg_value) {
      if ($arg_key === 'opigno_group_info') {
        $lp_id = $arg_value['group_id'];
        $lp_content_type_id = $arg_value['opigno_group_content_type'];
        break;
      }
    }

    // If one information missing, return an error.
    if (!isset($lp_id) || !isset($lp_content_type_id)) {
      // @todo Add an error message here.
      return;
    }

    // Get the newly or edited entity.
    $entity = $build_info['callback_object']->getEntity();
    if (\Drupal::moduleHandler()->moduleExists('opigno_skills_system') && $lp_content_type_id == 'ContentTypeModule') {
      $managed_contents = OpignoGroupManagedContent::loadByProperties([
        'group_content_type_id' => 'ContentTypeModule',
        'group_id' => $lp_id,
        'entity_id' => $entity->id(),
      ]);

      if (!empty($managed_contents)) {
        $content = reset($managed_contents);
        $content->setSkillSystem($entity->getSkillsActive());

        if ($entity->getSkillsActive()) {
          $content->setMandatory(0);
        }

        $content->save();
      }
    }

    // Clear user input.
    $input = $form_state->getUserInput();
    // We should not clear the system items from the user input.
    $clean_keys = $form_state->getCleanValueKeys();
    $clean_keys[] = 'ajax_page_state';
    foreach ($input as $key => $item) {
      if (!in_array($key, $clean_keys) && substr($key, 0, 1) !== '_') {
        unset($input[$key]);
      }
    }

    // Store new entity for display in the AJAX callback.
    $input['entity'] = $entity;
    $form_state->setUserInput($input);

    // Rebuild the form state values.
    $form_state->setRebuild();
    $form_state->setStorage([]);
  }

  /**
   * Method called when a step is set as mandatory or not.
   */
  public function updateItemMandatory(Group $group, Request $request) {
    // Get the data and ensure that all data are okay.
    $datas = json_decode($request->getContent());
    if (empty($datas->cid) || isset($datas->isMandatory) === FALSE) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }
    $cid = $datas->cid;
    $mandatory = $datas->isMandatory;

    // Load the good content, update it and save it.
    $content = OpignoGroupManagedContent::load($cid);
    $content->setMandatory($mandatory);
    $content->save();

    // Finally, return the JSON response.
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Method called when an item success score is set or not.
   */
  public function updateItemMinScore(Group $group, Request $request) {
    // Ensure all data are okay.
    $datas = json_decode($request->getContent());
    if (empty($datas->cid)) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }
    $cid = $datas->cid;
    $success_score_min = empty($datas->successScoreMin) ? 0 : $datas->successScoreMin;

    // Update the item.
    $content = OpignoGroupManagedContent::load($cid);
    $content->setSuccessScoreMin($success_score_min);
    $content->save();

    // Return the JSON response.
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * This method is called on learning path load.
   *
   * It returns all the steps and their links in JSON format.
   */
  public function getItems(Group $group) {
    $courses_storage = $this->entityTypeManager->getStorage('group');

    // Init the response and get all the contents from this learning path.
    $entities = [];
    $managed_contents = OpignoGroupManagedContent::loadByProperties(['group_id' => $group->id()]);
    // @todo Maybe extend the class LPManagedContent.
    // with LearningPathContent
    // (and use Parent::__constructor() to fill the params).
    // Convert all the LPManagedContent to LearningPathContent
    // and convert it to an array.
    foreach ($managed_contents as $managed_content) {
      // Need the content type object to get the LearningPathContent object.
      $content_type_id = $managed_content->getGroupContentTypeId();
      $content_type = $this->contentTypesManager->createInstance($content_type_id);
      $lp_content = $content_type->getContent($managed_content->getEntityId());
      if ($lp_content === FALSE) {
        continue;
      }
      // Create the array that is ready for JSON.
      $manager_array = $lp_content->toManagerArray($managed_content);
      if ($lp_content->getGroupContentTypeId() == 'ContentTypeCourse') {
        $course = $courses_storage->load($lp_content->getEntityId());
        $group_content = $course->getContent('opigno_module_group');
        $manager_array['modules_count'] = count($group_content);
      }

      // Add translatable values into Angular.
      switch ($lp_content->getGroupContentTypeId()) {
        case 'ContentTypeCourse':
          $manager_array['translate']['content_type_title'] = $this->t('Course')->render();
          break;

        case 'ContentTypeModule':
          $manager_array['translate']['content_type_title'] = $this->t('Opigno module')->render();
          break;

        case 'ContentTypeMeeting':
          $manager_array['translate']['content_type_title'] = $this->t('Live meeting')->render();
          break;

        case 'ContentTypeILT':
          $manager_array['translate']['content_type_title'] = $this->t('Instructor-Led Training')->render();
          break;
      }

      $manager_array['translate']['text_close'] = $this->t('close')->render();
      $manager_array['translate']['text_add_a_new_item'] = $this->t('Click here to add a new item')->render();
      $manager_array['translate']['text_do_not_show_this_message_again'] = $this->t('Do not show this message again')->render();
      $manager_array['translate']['text_modules'] = $this->t('modules')->render();
      $manager_array['translate']['text_module'] = $this->t('module')->render();
      $manager_array['translate']['text_add'] = $this->t('add')->render();
      $manager_array['translate']['text_score'] = $this->t('score')->render();
      $manager_array['translate']['text_update'] = $this->t('update')->render();
      $manager_array['translate']['text_delete'] = $this->t('delete')->render();
      $manager_array['translate']['text_mandatory'] = $this->t('Mandatory')->render();
      $manager_array['translate']['text_minimum_score_to_validate_step'] = $this->t('Minimum score to validate step')->render();

      $entities[] = $manager_array;
    }

    // Return all the contents in JSON format.
    return new JsonResponse($entities, Response::HTTP_OK);
  }

  /**
   * This function is called on learning path load.
   *
   * It return the coordinates of every steps.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getPositions(Group $group) {
    // Get the positions from DB.
    $entityPositions = [];
    try {
      $managed_contents = OpignoGroupManagedContent::loadByProperties(['group_id' => $group->id()]);
    }
    catch (InvalidPluginDefinitionException $e) {
      return new JsonResponse(NULL, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    // Then, create a big array with the positions and return OK.
    foreach ($managed_contents as $managed_content) {
      // Check if it's not deleted.
      $content_type_id = $managed_content->getGroupContentTypeId();
      $content_type = $this->contentTypesManager->createInstance($content_type_id);
      $lp_content = $content_type->getContent($managed_content->getEntityId());
      if ($lp_content === FALSE) {
        continue;
      }

      $entityPositions[] = [
        'cid' => $managed_content->id(),
        'col' => $managed_content->getCoordinateX() ? $managed_content->getCoordinateX() : 1,
        'row' => $managed_content->getCoordinateY() ? $managed_content->getCoordinateY() : 1,
      ];
    }
    return new JsonResponse($entityPositions, Response::HTTP_OK);
  }

  /**
   * Called after each update on learning path structure (add/remove/move node).
   *
   * It update the position of a content.
   */
  public function setPositions(Request $request) {
    // Get the data and check if it's correct.
    $datas = json_decode($request->getContent());
    if (empty($datas->mainItemPositions)) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }
    $content_positions = $datas->mainItemPositions;

    // Then, for each content, update the position in DB and return OK.
    foreach ($content_positions as $content_position) {
      $content = OpignoGroupManagedContent::load($content_position->cid);
      if (empty($content)) {
        continue;
      }
      $content->setCoordinateX($content_position->col);
      $content->setCoordinateY($content_position->row);
      $content->save();
    }
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Checking if the group contains item.
   *
   * @param int|string $group_id
   *   The group ID to check.
   * @param int|string $entity_id
   *   The entity ID.
   * @param string $content_type
   *   The content type.
   *
   * @return bool
   *   If the group contains the item or not.
   */
  protected function hasItem($group_id, $entity_id, string $content_type): bool {
    if (empty($group_id) || empty($entity_id) || empty($content_type)) {
      return FALSE;
    }

    $query = $this->database->select('opigno_group_content', 'gc')
      ->condition('gc.group_id', $group_id)
      ->condition('gc.group_content_type_id', $content_type)
      ->condition('gc.entity_id', $entity_id)
      ->fields('gc', ['id']);
    $result = $query->execute()->fetchField();

    return !empty($result);
  }

  /**
   * This method adds an item (content) in the learning path.
   */
  public function addItem(Group $group, Request $request) {
    // First, check if all parameters are here.
    $datas = json_decode($request->getContent());
    $entity_id = !empty($datas->entityId) ? $datas->entityId : 0;
    $content_type = !empty($datas->contentType) ? $datas->contentType : 0;

    $exist = $this->hasItem($group->id(), $entity_id, $content_type);

    if ($exist) {
      return new JsonResponse(NULL, Response::HTTP_OK);
    }

    if (
      empty($datas->entityId)
      || empty($datas->contentType)
    ) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }

    // Get the params.
    $entityId = $datas->entityId;
    $contentType = $datas->contentType;
    $parentCid = empty($datas->parentCid) ? NULL : $datas->parentCid;

    // Create the added item as an LP content.
    $new_content = OpignoGroupManagedContent::createWithValues(
      $group->id(),
      $contentType,
      $entityId
    );

    // Check if the content type is a module.
    // If yes then check if the module in the skills system.
    if ($this->moduleHandler->moduleExists('opigno_skills_system') && $contentType === 'ContentTypeModule') {
      $module = $this->entityTypeManager->getStorage('opigno_module')->load($entityId);
      $new_content->set('in_skills_system', $module->getSkillsActive());
    }

    $new_content->save();

    // Then, create the links to the parent content.
    if (!empty($parentCid)) {
      OpignoGroupManagedLink::createWithValues(
        $group->id(),
        $parentCid,
        $new_content->id()
      )->save();
    }
    // Add created entity as Group content.
    $plugin_definition = $this->contentTypesManager->getDefinition($datas->contentType);
    if (!empty($plugin_definition['group_content_plugin_id'])) {
      // Load Course (Group) entity and save as content using specific plugin.
      $added_entity = $this->entityTypeManager
        ->getStorage($plugin_definition['entity_type'])
        ->load($datas->entityId);
      $group->addContent($added_entity, $plugin_definition['group_content_plugin_id']);
    }
    $response = new JsonResponse(['cid' => $new_content->id()], Response::HTTP_OK);
    sleep(1);
    return $response;
  }

  /**
   * Remove item from learning path.
   */
  public function removeItem(Request $request) {
    // Get and check the params of the ajax request.
    $datas = json_decode($request->getContent());
    if (empty($datas->cid)) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }
    $cid = $datas->cid;
    // Load Learning path content entity.
    $lp_content_entity = OpignoGroupManagedContent::load($cid);
    $learning_path_plugin = $lp_content_entity->getGroupContentType();
    $plugin_definition = $this->contentTypesManager->getDefinition($learning_path_plugin->getPluginId());
    if (!empty($plugin_definition['group_content_plugin_id'])) {
      $lp_group = $this->entityTypeManager
        ->getStorage('group')
        ->load($lp_content_entity->get('group_id')->entity->id());
      // Remove Learning path course if it's exist.
      $group_contents = $lp_group->getContentByEntityId($plugin_definition['group_content_plugin_id'], $lp_content_entity->get('entity_id')->value);
      if (!empty($group_contents)) {
        // Probably, there can be a few same items. Get only last.
        $group_content = end($group_contents);
        $group_content->delete();
      }
    }

    // Then delete the content and return OK.
    $lp_content_entity->delete();
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Add a new link in the Learning Path.
   */
  public function addLink(Group $group, Request $request) {
    // First, check if all params are okay.
    $datas = json_decode($request->getContent());
    if (
      empty($datas->parentCid)
      || empty($datas->childCid)
    ) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }

    // Get the request params.
    $parentCid = $datas->parentCid;
    $childCid = $datas->childCid;

    // Create the new link and return OK.
    $new_link = OpignoGroupManagedLink::createWithValues(
      $group->id(),
      $parentCid,
      $childCid,
      0
    );
    $new_link->save();
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Update a link minimum score to go to next step.
   */
  public function updateLink(Group $group, Request $request) {
    // First, check the params.
    $datas = json_decode($request->getContent());
    if (empty($datas->parentCid) || empty($datas->childCid)) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }

    // Then get the params.
    $parentCid = $datas->parentCid;
    $childCid = $datas->childCid;
    $requiredScore = !empty($datas->requiredScore) ? $datas->requiredScore : 0;
    $requiredActivities = !empty($datas->requiredActivities) ? serialize($datas->requiredActivities) : NULL;

    // Get the links that use the same LP ID,
    // parent CID and child CID. Should be only one.
    try {
      $links = OpignoGroupManagedLink::loadByProperties([
        'group_id' => $group->id(),
        'parent_content_id' => $parentCid,
        'child_content_id' => $childCid,
      ]);
    }
    catch (InvalidPluginDefinitionException $e) {
      return new JsonResponse(NULL, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    // If no link returned, create it and return OK.
    if (empty($links)) {
      $new_link = OpignoGroupManagedLink::createWithValues(
        $group->id(),
        $parentCid,
        $childCid,
        $requiredScore,
        $requiredActivities
      );
      $new_link->save();

      return new JsonResponse(NULL, Response::HTTP_OK);
    }

    // If the link is found, update it and return OK.
    foreach ($links as $link) {
      $link->setRequiredScore($requiredScore);
      $link->setRequiredActivities($requiredActivities);
      $link->save();
    }
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Removes a link.
   */
  public function removeLink(Group $group, Request $request) {
    // First, check that the params are okay.
    $datas = json_decode($request->getContent());
    if (
      empty($datas->parentCid)
      || empty($datas->childCid)
    ) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }

    // Get the params.
    $parentCid = $datas->parentCid;
    $childCid = $datas->childCid;

    // Get the links. Should be only one.
    try {
      $links = OpignoGroupManagedLink::loadByProperties([
        'group_id' => $group->id(),
        'parent_content_id' => $parentCid,
        'child_content_id' => $childCid,
      ]);
    }
    catch (InvalidPluginDefinitionException $e) {
      return new JsonResponse(NULL, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    // Delete the links and return OK.
    foreach ($links as $link) {
      $link->delete();
    }
    return new JsonResponse(NULL, Response::HTTP_OK);
  }

  /**
   * Return contents availables when you want add content to learning path.
   *
   * @param int $mainItem
   *   The main level content ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getAvailableItems($mainItem = NULL) {
    // Init the return array and get all the content types available.
    $available_contents = [];
    $content_types_definitions = $this->contentTypesManager->getDefinitions();

    // For each content type,
    // get the available contents from them and store it in the return array.
    foreach ($content_types_definitions as $content_type_id => $content_type_definition) {
      // Get the available contents from the content type.
      $content_type = $this->contentTypesManager->createInstance($content_type_id);
      $content_type_contents = $content_type->getAvailableContents();

      // For each content, convert it to an array.
      foreach ($content_type_contents as $content_type_content) {
        $available_contents[] = $content_type_content->toManagerArray();
      }
    }

    // Return the available contents in JSON.
    return new JsonResponse($available_contents, Response::HTTP_OK);
  }

  /**
   * Return content types available for learning paths.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param bool $json_output
   *   JSON format flag.
   *
   * @return array|\Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function getItemTypes(Group $group, $json_output = TRUE) {
    // Init the return array and get all the content types available.
    $available_types = [];
    $content_types_definitions = $this->contentTypesManager->getDefinitions();

    // Get the group bundle name.
    $group_type = $group->getGroupType();

    // Check if module 'Opigno Moxtra' is enabled and configured.
    $moxtra_status = $this->moduleHandler->moduleExists('opigno_moxtra') && _opigno_moxtra_get_organization_status();

    // For each content type available,
    // convert it to an array and store it in the return array.
    foreach ($content_types_definitions as $content_type_definition) {
      // Add available content type only if it's allowed by plugin.
      if (in_array($group_type->id(), $content_type_definition['allowed_group_types'])) {
        if ($content_type_definition['id'] == 'ContentTypeMeeting' && !$moxtra_status) {
          continue;
        }

        $available_types[] = [
          'bundle' => $content_type_definition['id'],
          'contentType' => $content_type_definition['id'],
          'name' => $content_type_definition['readable_name'],
        ];
      }
    }

    // If a JSON response is asked, return JSON.
    // Else, return the array.
    if ($json_output) {
      return new JsonResponse($available_types, Response::HTTP_OK);
    }
    else {
      return $available_types;
    }
  }

  /**
   * Create item from ajax drupal entity form.
   */
  public function createItem(Request $request, $type = NULL) {
    // @todo Wait for Fred move first.
  }

  /**
   * Update entities ancestors.
   *
   * Eg. if user switch two entities in learning path.
   */
  public function updateEntities(Group $group, Request $request) {
    $datas = Json::decode($request->getContent());

    // @todo Update parents links if changed.
    foreach ($datas['entities'] as $entity) {
      if (!$entity['cid']) {
        return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
      };
      $current_cid = $entity['cid'];
      $content = OpignoGroupManagedContent::load($current_cid);

      // Parent content id.
      if (!empty($entity['parents'])) {
        $parent_cid = $entity['parents'][0]['cid'];
      }
      else {
        $parent_cid = NULL;
      };
      // Can be null if there is not parent.
      $parent_link = $content->getParentsLinks();
      if ($parent_link) {
        $parent_link = reset($parent_link);
        // Update parent link if was changed.
        if ($parent_cid == NULL) {
          // Delete parent link.
          $parent_link->delete();
        }
        elseif ($parent_link->getParentContentId() != $parent_cid) {
          $parent_link->setParentContentId($parent_cid);
          $parent_link->save();
        }
      }
      else {
        if ($parent_cid) {
          // Create new parent link instance.
          $new_link = OpignoGroupManagedLink::createWithValues(
            $group->id(),
            $parent_cid,
            $current_cid,
            $entity['successScoreMin']
          );
          $new_link->save();
        };
      };
    }

    return new JsonResponse([], Response::HTTP_OK);
  }

  /**
   * Update Learning Path guided navigation field.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request array.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response array.
   */
  public function updateGuidedNavigation(Group $group, Request $request) {
    $data = json_decode($request->getContent());
    if (!isset($data->guidedNavigation)) {
      return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
    }

    $guidedNavigation = 0;
    if ($group->hasField('field_guided_navigation')) {
      $guidedNavigation = $data->guidedNavigation;
      $group->set('field_guided_navigation', $guidedNavigation);

      try {
        $group->save();
      }
      catch (EntityStorageException $e) {
        return new JsonResponse(NULL, Response::HTTP_INTERNAL_SERVER_ERROR);
      }
    }

    return new JsonResponse(['guidedNavigation' => $guidedNavigation], Response::HTTP_OK);
  }

  /**
   * Return Learning Path guided navigation field.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response array.
   */
  public function getGuidedNavigationResponse(Group $group) {
    $guidedNavigation = self::getGuidedNavigation($group);

    // Return the JSON response.
    return new JsonResponse($guidedNavigation, Response::HTTP_OK);
  }

  /**
   * Get Learning Path guided navigation field.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return bool
   *   Training guided navigation flag
   */
  public static function getGuidedNavigation(Group $group): bool {
    if ($group->bundle() === 'opigno_course') {
      $group_id = OpignoGroupContext::getCurrentGroupId();
      if ($group_id) {
        $group = Group::load($group_id);
      }
    }

    return $group instanceof GroupInterface
      && $group->hasField('field_guided_navigation')
      && $group->get('field_guided_navigation')->getString();
  }

  /**
   * Duplicate course.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   */
  public function courseDuplicate(Group $group) {
    $duplicate = $group->createDuplicate();
    $current_name = $duplicate->label();
    $duplicate->set('label', $this->t('Duplicate of @name', [
      '@name' => $current_name,
    ]));
    $current_time = $this->time->getCurrentTime();

    $duplicate->setOwnerId($this->currentUser->id());
    $duplicate->set('created', $current_time);
    $duplicate->set('changed', $current_time);
    $duplicate->save();
    $duplicate_id = $duplicate->id();

    $course_content = $group->getContentEntities();

    foreach ($course_content as $content) {
      if ($content instanceof OpignoModule) {
        $duplicate->addContent($content, 'opigno_module_group');

        $managed_content_array = OpignoGroupManagedContent::loadByProperties([
          'group_content_type_id' => 'ContentTypeModule',
          'entity_id' => $content->id(),
          'group_id' => $group->id(),
        ]);

        $managed_content = reset($managed_content_array);

        $parent_links = $managed_content->getParentsLinks();

        $new_content = OpignoGroupManagedContent::createWithValues(
          $duplicate->id(),
          $managed_content->getGroupContentTypeId(),
          $content->id(),
          $managed_content->getSuccessScoreMin(),
          $managed_content->isMandatory(),
          $managed_content->getCoordinateX(),
          $managed_content->getCoordinateY()
        );

        $new_content->save();

        foreach ($parent_links as $link) {
          $parent_old_content = OpignoGroupManagedContent::load($link->getParentContentId());
          $parent_module_id = $parent_old_content->getEntityId();

          $parent_new_content_array = OpignoGroupManagedContent::loadByProperties([
            'group_content_type_id' => 'ContentTypeModule',
            'entity_id' => $parent_module_id,
            'group_id' => $duplicate_id,
          ]);

          $parent_new_content = reset($parent_new_content_array);

          if ($parent_new_content) {
            OpignoGroupManagedLink::createWithValues(
              $duplicate_id,
              $parent_new_content->id(),
              $new_content->id(),
              $link->getRequiredScore(),
              serialize($link->getRequiredActivities())
            )->save();
          }
        }
      }
    }

    $duplicate->save();

    return $this->redirect('entity.group.edit_form', [
      'group' => $duplicate_id,
    ]);
  }

  /**
   * Export course.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   */
  public function courseExport(Group $group) {
    $course_content = $group->getContentEntities();
    $course_fields = $group->getFields();
    $files_to_export = [];

    foreach ($course_fields as $field_key => $field) {
      $data_structure[$group->id()][$field_key] = $field->getValue();
    }

    $course_name = $data_structure[$group->id()]['label'][0]['value'];
    $format = 'json';
    $dir = 'sites/default/files/opigno-export';
    $this->fileSystem->deleteRecursive($dir);
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);

    $content = $this->serializer->serialize($data_structure, $format);
    $filename = "export-course_{$course_name}_{$group->id()}.{$format}";
    $filename_path = "{$dir}/{$filename}";
    $files_to_export['course'] = $filename;

    $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

    $new_filename = "opigno-course_{$course_name}_{$group->id()}.opi";
    $zip = new \ZipArchive();
    $zip->open($dir . '/' . $new_filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFile($filename_path, $filename);

    foreach ($course_content as $entity) {
      if ($entity instanceof OpignoModule) {
        $activities = $entity->getModuleActivities();
        $module_fields = $entity->getFields();
        $data_structure = [];

        foreach ($module_fields as $field_key => $field) {
          $data_structure[$entity->id()][$field_key] = $field->getValue();
        }

        $managed_content_array = OpignoGroupManagedContent::loadByProperties([
          'group_content_type_id' => 'ContentTypeModule',
          'entity_id' => $entity->id(),
          'group_id' => $group->id(),
        ]);

        $managed_content = reset($managed_content_array);

        $parent_links = $managed_content->getParentsLinks();

        foreach ($parent_links as $link) {
          $parent_old_content = OpignoGroupManagedContent::load($link->getParentContentId());
          $parent_module_id = $parent_old_content->getEntityId();

          $parent_new_content_array = OpignoGroupManagedContent::loadByProperties([
            'group_content_type_id' => 'ContentTypeModule',
            'entity_id' => $parent_module_id,
            'group_id' => $group->id(),
          ]);

          $parent_new_content = reset($parent_new_content_array);

          if ($parent_new_content) {
            $link = [
              'group_id' => $group->id(),
              'parent_content_id' => $parent_new_content->id(),
              'child_content_id' => $entity->id(),
              'required_score' => $link->getRequiredScore(),
              'required_activities'  => $link->getRequiredActivities(),
            ];

            $data_structure[$entity->id()]['parent_links'][] = $link;
          }
        }

        $data_structure[$entity->id()]['managed_content'] = $managed_content;

        $module_name = $data_structure[$entity->id()]['name'][0]['value'];
        $content = $this->serializer->serialize($data_structure, $format);
        $filename = "export-module_{$module_name}_{$entity->id()}.{$format}";
        $filename_path = "{$dir}/{$filename}";
        $files_to_export['modules'][$module_name . '_' . $entity->id()] = $filename;

        $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

        $zip->addFile($filename_path, $filename);

        foreach ($activities as $activity) {
          $opigno_activity = OpignoActivity::load($activity->id);
          $fields = $opigno_activity->getFields();
          $data_structure = [];

          foreach ($fields as $field_key => $field) {
            $data_structure[$opigno_activity->id()][$field_key] = $field->getValue();
          }

          $activity_name = $data_structure[$opigno_activity->id()]['name'][0]['value'];
          $filename = "export-activity_{$activity_name}_{$opigno_activity->id()}.{$format}";
          $filename_path = "{$dir}/{$filename}";
          $files_to_export['activities'][$module_name . '_' . $entity->id()][] = $filename;

          switch ($opigno_activity->bundle()) {
            case 'opigno_scorm':
              if (isset($opigno_activity->get('opigno_scorm_package')->target_id)) {
                $file = $this->fileStorage->load($opigno_activity->get('opigno_scorm_package')->target_id);
                if ($file instanceof FileInterface) {
                  $file_uri = $file->getFileUri();
                  $file_path = $this->fileSystem->realpath($file_uri);
                  $scorm_filename = $file->id() . '-' . $file->getFilename();

                  $data_structure[$opigno_activity->id()]['files'][$scorm_filename] = [
                    'file_name' => $file->getFilename(),
                    'filemime' => $file->getMimeType(),
                    'status' => $file->get('status')->getValue()[0]['value'],
                  ];

                  $zip->addFile($file_path, $scorm_filename);
                }
              }
              break;

            case 'opigno_tincan':
              if (isset($opigno_activity->get('opigno_tincan_package')->target_id)) {
                $file = $this->fileStorage->load($opigno_activity->get('opigno_tincan_package')->target_id);
                if ($file instanceof FileInterface) {
                  $file_uri = $file->getFileUri();
                  $file_path = $this->fileSystem->realpath($file_uri);
                  $tincan_filename = $file->id() . '-' . $file->getFilename();

                  $data_structure[$opigno_activity->id()]['files'][$tincan_filename] = [
                    'file_name' => $file->getFilename(),
                    'filemime' => $file->getMimeType(),
                    'status' => $file->get('status')->getValue()[0]['value'],
                  ];

                  $zip->addFile($file_path, $tincan_filename);
                }
              }
              break;

            case 'opigno_slide':
              if (isset($opigno_activity->get('opigno_slide_pdf')->target_id)) {
                $media = $this->mediaStorage->load($opigno_activity->get('opigno_slide_pdf')->target_id);
                $file_id = $media->get('field_media_file')->getValue()[0]['target_id'];
                $file = $this->fileStorage->load($file_id);

                if ($file instanceof FileInterface) {
                  $file_uri = $file->getFileUri();
                  $file_path = $this->fileSystem->realpath($file_uri);
                  $pdf_filename = $file->id() . '-' . $file->getFilename();

                  $data_structure[$opigno_activity->id()]['files'][$pdf_filename] = [
                    'file_name' => $file->getFilename(),
                    'filemime' => $file->getMimeType(),
                    'status' => $file->get('status')->getValue()[0]['value'],
                    'bundle' => $media->bundle(),
                  ];

                  $zip->addFile($file_path, $pdf_filename);
                }
              }
              break;

            case 'opigno_video':
              if (isset($opigno_activity->get('field_video')->target_id)) {
                $file = $this->fileStorage->load($opigno_activity->get('field_video')->target_id);
                if ($file instanceof FileInterface) {
                  $file_uri = $file->getFileUri();
                  $file_path = $this->fileSystem->realpath($file_uri);
                  $video_filename = $file->id() . '-' . $file->getFilename();

                  $data_structure[$opigno_activity->id()]['files'][$video_filename] = [
                    'file_name' => $file->getFilename(),
                    'filemime' => $file->getMimeType(),
                    'status' => $file->get('status')->getValue()[0]['value'],
                  ];

                  $zip->addFile($file_path, $video_filename);
                }
              }
              break;
          }

          if ($opigno_activity->bundle() == 'opigno_h5p') {
            $hp5_id = $data_structure[$opigno_activity->id()]['opigno_h5p'][0]['h5p_content_id'] ?? FALSE;
            $h5p_content = $hp5_id ? H5PContent::load($hp5_id) : FALSE;
            if ($h5p_content instanceof H5PContent) {
              // @todo Unused returned filters, but keeps this call
              //   for backward compatibilities.
              $h5p_content->getFilteredParameters();
              $hp5_archive = "interactive-content-{$hp5_id}.h5p";
              $zip->addFile('sites/default/files/h5p/exports/' . $hp5_archive, $hp5_archive);
            }
            else {
              $this->addLogAndMessage($opigno_activity);
            }
          }

          $content = $this->serializer->serialize($data_structure, $format);
          $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

          $zip->addFile($filename_path, $filename);
        }
      }
    }

    $content = $this->serializer->serialize($files_to_export, $format);
    $filename = "list_of_files.{$format}";
    $filename_path = "{$dir}/{$filename}";
    $files_to_export['activities'][] = $filename;

    $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

    $zip->addFile($filename_path, $filename);
    $zip->close();

    $headers = [
      'Content-Type' => 'application/opi',
      'Content-Disposition' => 'attachment; filename="' . $new_filename . '"',
    ];

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
      $headers['Cache-Control'] = 'must-revalidate, post-check=0, pre-check=0';
      $headers['Pragma'] = 'public';
    }
    else {
      $headers['Pragma'] = 'no-cache';
    }

    return new BinaryFileResponse($dir . '/' . $new_filename, 200, $headers);
  }

  /**
   * Export training.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   */
  public function trainingExport(Group $group) {
    $group_content = $group->getContentEntities();
    $group_fields = $group->getFields();
    $files_to_export = [];
    $modules_in_courses = [];
    $activity_ids = [];
    $skipped_delta = [
      1 => 0,
      2 => 0,
      3 => 0,
    ];

    foreach ($group_content as $entity) {
      if ($entity instanceof Group && $entity->bundle() == 'opigno_course') {
        $course_content = $entity->getContentEntities('opigno_module_group');
        $group_content = array_merge($group_content, $course_content);

        foreach ($course_content as $module) {
          $modules_in_courses[$module->id()] = $entity->id();
        }
      }
      elseif (in_array($entity->bundle(), [
        'opigno_moxtra_meeting',
        'opigno_ilt',
      ])) {
        $skipped_entities[$entity->id()] = $entity;
      }
    }

    foreach ($group_fields as $field_key => $field) {
      $data_structure[$group->id()][$field_key] = $field->getValue();
    }

    $training_name = $data_structure[$group->id()]['label'][0]['value'];
    $format = 'json';
    $dir = 'sites/default/files/opigno-export';
    $this->fileSystem->deleteRecursive($dir);
    $this->fileSystem->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);

    // Load image data.
    $img_filename = '';
    if (!empty($data_structure[$group->id()]['field_learning_path_media_image'][0]['target_id'])) {

      $media = $this->mediaStorage->load($data_structure[$group->id()]['field_learning_path_media_image'][0]['target_id']);
      if (!empty($media)) {
        $file_id = $media->get('field_media_image')->getValue()[0]['target_id'];
        $file = $this->fileStorage->load($file_id);

        if ($file instanceof FileInterface) {
          $file_uri = $file->getFileUri();
          $file_path = $this->fileSystem->realpath($file_uri);
          $img_filename = $file->id() . '-' . $file->getFilename();

          $data_structure[$group->id()]['field_learning_path_media_image'] = [
            'file_name' => $file->getFilename(),
            'filemime' => $file->getMimeType(),
            'status' => $file->get('status')->getValue()[0]['value'],
            'bundle' => $media->bundle(),
            'media' => $media->get('field_media_image')->getValue(),

          ];
        }
      }
    }

    $content = $this->serializer->serialize($data_structure, $format);
    $filename = "export-training_{$training_name}_{$group->id()}.{$format}";
    $filename_path = "{$dir}/{$filename}";
    $files_to_export['training'] = $filename;

    $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

    $new_filename = "opigno-training_{$training_name}_{$group->id()}.opi";
    $zip = new \ZipArchive();
    $zip->open($dir . '/' . $new_filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFile($filename_path, $filename);

    if (!empty($img_filename) && !empty($file_path)) {
      $zip->addFile($file_path, $img_filename);
    }

    foreach ($group_content as $entity) {
      if ($entity instanceof OpignoModule || ($entity instanceof Group && $entity->bundle() == 'opigno_course')) {
        $eid = $entity->id();

        if ($entity instanceof OpignoModule) {
          $activities = $entity->getModuleActivities();
          $group_content_type_id = 'ContentTypeModule';
        }
        else {
          $activities = [];
          $group_content_type_id = 'ContentTypeCourse';
        }

        $module_fields = $entity->getFields();
        $data_structure = [];
        $data_structure[$eid]['parent_links'] = [];

        foreach ($module_fields as $field_key => $field) {
          $data_structure[$eid][$field_key] = $field->getValue();
        }

        $managed_content_array = OpignoGroupManagedContent::loadByProperties([
          'group_content_type_id' => $group_content_type_id,
          'entity_id' => $eid,
          'group_id' => $group->id(),
        ]);

        $managed_content = reset($managed_content_array);

        $link_group_id = $group->id();

        if (!$managed_content && isset($modules_in_courses[$entity->id()])) {
          $managed_content_array = OpignoGroupManagedContent::loadByProperties([
            'group_content_type_id' => $group_content_type_id,
            'entity_id' => $entity->id(),
            'group_id' => $modules_in_courses[$entity->id()],
          ]);

          $managed_content = reset($managed_content_array);

          $data_structure[$eid]['course_rel'] = $modules_in_courses[$eid];
          $link_group_id = $modules_in_courses[$eid];
        }

        if ($managed_content) {
          $parent_links = $managed_content->getParentsLinks();

          foreach ($parent_links as $link) {
            $parent_old_content = OpignoGroupManagedContent::load($link->getParentContentId());
            $parent_module_id = $parent_old_content->getEntityId();

            if (!empty($skipped_entities[$parent_module_id])) {
              // Recalculate position of module if some steps of Training were
              // skipped.
              $coordinate_x = $managed_content->getCoordinateX();
              $coordinate_y = $managed_content->getCoordinateY();
              $skipped_delta[$coordinate_x]++;
              $managed_content->setCoordinateY($coordinate_y - $skipped_delta[$coordinate_x]);
            }

            $parent_new_content_array = OpignoGroupManagedContent::loadByProperties([
              'group_content_type_id' => 'ContentTypeModule',
              'entity_id' => $parent_module_id,
              'group_id' => $link_group_id,
            ]);

            $parent_new_content = reset($parent_new_content_array);

            if ($parent_new_content) {
              $parent_id = $parent_new_content->id();

              $link = [
                'group_id' => $link_group_id,
                'parent_content_id' => $parent_id,
                'child_content_id' => $eid,
                'required_score' => $link->getRequiredScore(),
                'required_activities' => $link->getRequiredActivities(),
              ];

              $data_structure[$eid]['parent_links'][] = $link;
            }
          }
        }

        $data_structure[$eid]['managed_content'] = $managed_content;

        if ($group_content_type_id === 'ContentTypeModule') {
          $entity_name = $data_structure[$entity->id()]['name'][0]['value'];
          $entity_name = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', $entity_name);
          $filename = "export-module_{$entity_name}_{$entity->id()}.{$format}";
          $files_to_export['modules'][$entity_name . '_' . $entity->id()] = $filename;
        }
        else {
          $entity_name = $data_structure[$entity->id()]['label'][0]['value'];
          $entity_name = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', $entity_name);
          $filename = "export-course_{$entity_name}_{$entity->id()}.{$format}";
          $files_to_export['courses'][$entity_name . '_' . $entity->id()] = $filename;
        }

        $content = $this->serializer->serialize($data_structure, $format);
        $filename_path = "{$dir}/{$filename}";

        $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

        $zip->addFile($filename_path, $filename);

        foreach ($activities as $activity) {
          $opigno_activity = OpignoActivity::load($activity->id);
          $fields = $opigno_activity->getFields();
          $data_structure = [];
          $aid = $opigno_activity->id();

          $activity_ids[$eid . '-' . $aid] = $aid;

          foreach ($fields as $field_key => $field) {
            $data_structure[$eid . '-' . $aid][$field_key] = $field->getValue();
          }

          $max_score = [];

          if ($entity instanceof OpignoModule) {
            $max_score = $this->getMaxScore($entity, $activity);
            $max_score = $max_score['max_score'] ?? 10;
          }

          if (empty($data_structure[$eid . '-' . $aid]['max_score'])) {
            $data_structure[$eid . '-' . $aid]['max_score'] = $max_score;
          }

          $activity_name = $data_structure[$eid . '-' . $aid]['name'][0]['value'];
          $activity_name = preg_replace('/[^a-zA-Z0-9-_\.]/', '-', $activity_name);
          $filename = "export-activity_{$activity_name}_{$eid}_{$aid}.{$format}";
          $filename_path = "{$dir}/{$filename}";
          $files_to_export['activities'][$entity_name . '_' . $entity->id()][] = $filename;

          switch ($opigno_activity->bundle()) {
            case 'opigno_scorm':
              if (isset($opigno_activity->get('opigno_scorm_package')->target_id)) {
                $file = $this->fileStorage->load($opigno_activity->get('opigno_scorm_package')->target_id);
                if ($file instanceof FileInterface) {
                  $file_uri = $file->getFileUri();
                  $file_path = $this->fileSystem->realpath($file_uri);
                  $scorm_filename = $file->id() . '-' . $file->getFilename();

                  $data_structure[$eid . '-' . $aid]['files'][$scorm_filename] = [
                    'file_name' => $file->getFilename(),
                    'filemime' => $file->getMimeType(),
                    'status' => $file->get('status')->getValue()[0]['value'],
                  ];

                  $zip->addFile($file_path, $scorm_filename);
                }
              }
              break;

            case 'opigno_tincan':
              if (isset($opigno_activity->get('opigno_tincan_package')->target_id)) {
                $file = $this->fileStorage->load($opigno_activity->get('opigno_tincan_package')->target_id);
                if ($file instanceof FileInterface) {
                  $file_uri = $file->getFileUri();
                  $file_path = $this->fileSystem->realpath($file_uri);
                  $tincan_filename = $file->id() . '-' . $file->getFilename();

                  $data_structure[$eid . '-' . $aid]['files'][$tincan_filename] = [
                    'file_name' => $file->getFilename(),
                    'filemime' => $file->getMimeType(),
                    'status' => $file->get('status')->getValue()[0]['value'],
                  ];

                  $zip->addFile($file_path, $tincan_filename);
                }
              }
              break;

            case 'opigno_slide':
              if (isset($opigno_activity->get('opigno_slide_pdf')->target_id)) {
                $media = $this->mediaStorage->load($opigno_activity->get('opigno_slide_pdf')->target_id);
                $file_id = $media->get('field_media_file')->getValue()[0]['target_id'];
                $file = $this->fileStorage->load($file_id);
                if ($file instanceof FileInterface) {
                  $file_uri = $file->getFileUri();
                  $file_path = $this->fileSystem->realpath($file_uri);
                  $pdf_filename = $file->id() . '-' . $file->getFilename();

                  $data_structure[$eid . '-' . $aid]['files'][$pdf_filename] = [
                    'file_name' => $file->getFilename(),
                    'filemime' => $file->getMimeType(),
                    'status' => $file->get('status')->getValue()[0]['value'],
                    'bundle' => $media->bundle(),
                  ];

                  $zip->addFile($file_path, $pdf_filename);
                }
              }
              break;

            case 'opigno_video':
              if (isset($opigno_activity->get('field_video')->target_id)) {
                $file = $this->fileStorage->load($opigno_activity->get('field_video')->target_id);
                if ($file instanceof FileInterface) {
                  $file_uri = $file->getFileUri();
                  $file_path = $this->fileSystem->realpath($file_uri);
                  $video_filename = $file->id() . '-' . $file->getFilename();

                  $data_structure[$eid . '-' . $aid]['files'][$video_filename] = [
                    'file_name' => $file->getFilename(),
                    'filemime' => $file->getMimeType(),
                    'status' => $file->get('status')->getValue()[0]['value'],
                  ];

                  $zip->addFile($file_path, $video_filename);
                }
              }
              break;
          }

          if ($opigno_activity->bundle() === 'opigno_h5p') {
            $hp5_id = $data_structure[$eid . '-' . $aid]['opigno_h5p'][0]['h5p_content_id'] ?? FALSE;
            $h5p_content = $hp5_id ? H5PContent::load($hp5_id) : FALSE;
            if ($h5p_content instanceof H5PContent) {
              // @todo Unused returned filters, but keeps this call
              //   for backward compatibilities.
              $h5p_content->getFilteredParameters();
              $hp5_archive = "interactive-content-{$hp5_id}.h5p";
              $zip->addFile('sites/default/files/h5p/exports/' . $hp5_archive, $hp5_archive);
            }
            else {
              $this->addLogAndMessage($opigno_activity);
            }
          }

          $content = $this->serializer->serialize($data_structure, $format);
          $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

          $zip->addFile($filename_path, $filename);
        }
      }
    }

    // Export documents library.
    $main_tid = $group->get('field_learning_path_folder')->getString();
    $items_list = $this->documentsLibraryList($main_tid);
    $folder_library = $dir . '/library';
    $this->fileSystem->prepareDirectory($folder_library, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
    $zip->addEmptyDir('library');

    foreach ($items_list as $item) {
      if ($item['type'] === 'file') {
        $media = $this->mediaStorage->load($item['id']);

        $filename = $media->id() . '_media_' . $media->label() . '.' . $format;
        $filename_path = "{$folder_library}/{$filename}";
        $files_to_export['files'][$item['id']]['media'] = $filename;

        $content = $this->serializer->serialize($media, $format);
        $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);
        $zip->addFile($filename_path, 'library/' . $filename);

        $file_id = $media->get('tft_file')->getValue()[0]['target_id'];
        $file = $this->fileStorage->load($file_id);

        if ($file instanceof FileInterface) {
          $file_uri = $file->getFileUri();
          $filename = $file->id() . '-' . $file->getFilename();
          $file_path = $this->fileSystem->realpath($file_uri);

          $zip->addFile($file_path, 'library/' . $filename);

          $filename = $file->id() . '_file_' . $file->label() . '.' . $format;
          $files_to_export['files'][$item['id']]['file'] = $filename;
          $filename_path = "{$folder_library}/{$filename}";
          $content = $this->serializer->serialize($file, $format);
          $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);
          $zip->addFile($filename_path, 'library/' . $filename);
        }
      }
      elseif ($item['type'] === 'term') {
        $term = $this->termStorage->load($item['id']);

        if ($term) {
          $filename = $term->id() . '_' . $term->label() . '.' . $format;
          $filename_path = "{$folder_library}/{$filename}";
          $files_to_export['terms'][] = $filename;

          $content = $this->serializer->serialize($term, $format);
          $context['results']['file'] = $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

          $zip->addFile($filename_path, 'library/' . $filename);
        }
      }
    }

    $content = $this->serializer->serialize($files_to_export, $format);
    $filename = "list_of_files.{$format}";
    $filename_path = "{$dir}/{$filename}";
    $files_to_export['activities'][] = $filename;

    $this->fileSystem->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

    $zip->addFile($filename_path, $filename);

    if (file_exists($filename_path)) {
      $zip->close();
    }

    $headers = [
      'Content-Type' => 'application/opi',
      'Content-Disposition' => 'attachment; filename="' . $new_filename . '"',
    ];

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
      $headers['Cache-Control'] = 'must-revalidate, post-check=0, pre-check=0';
      $headers['Pragma'] = 'public';
    }
    else {
      $headers['Pragma'] = 'no-cache';
    }

    return new BinaryFileResponse($dir . '/' . $new_filename, 200, $headers);
  }

  /**
   * Gets the max score data.
   *
   * @param \Drupal\opigno_module\Entity\OpignoModuleInterface $module
   *   The module to get the score for.
   * @param \Drupal\opigno_module\Entity\OpignoActivityInterface|object $activity
   *   The activity to get the score for.
   *
   * @return array
   *   The max score data.
   */
  protected function getMaxScore(OpignoModuleInterface $module, $activity): array {
    if ($activity instanceof OpignoActivityInterface) {
      $activity_id = $activity->id();
      $activity_rid = $activity->getRevisionId();
    }
    else {
      $activity_id = $activity->id;
      $activity_rid = $activity->vid;
    }

    $query = $this->database->select('opigno_module_relationship', 'omr')
      ->fields('omr', [])
      ->condition('omr.parent_id', $module->id())
      ->condition('omr.parent_vid', $module->getRevisionId())
      ->condition('omr.child_id', $activity_id)
      ->condition('omr.child_vid', $activity_rid)
      ->condition('omr.activity_status', 1);
    $results = $query->execute()->fetchAll();
    $omr = $results[0] ?? [];
    return (array) $omr;
  }

  /**
   * Duplicate training.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the duplicated training page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function trainingDuplicate(Group $group): RedirectResponse {
    $duplicate = $group->createDuplicate();
    $current_name = $duplicate->label();
    $new_name = $this->t('Duplicate of @name', ['@name' => $current_name]);
    $duplicate->set('label', $new_name);
    $current_time = $this->time->getCurrentTime();
    $user_id = $this->currentUser->id();

    $duplicate->setOwnerId($user_id);
    $duplicate->set('created', $current_time);
    $duplicate->set('changed', $current_time);
    $duplicate->set('field_learning_path_enable_forum', 0);
    $duplicate->set('field_learning_path_forum', NULL);
    $duplicate->set('field_learning_path_folder', NULL);
    $duplicate->save();

    if ($this->moduleHandler->moduleExists('opigno_moxtra')) {
      // Create workspace.
      $moxtra_api = _opigno_moxtra_get_moxtra_api();
      $response = $moxtra_api->createWorkspace($user_id, $new_name);
      $binder_id = $response['data']['id'];

      $workspace = Workspace::create();
      $workspace->setName($new_name);
      $workspace->setBinderId($binder_id);
      $workspace->save();

      // Attach workspace to the training.
      $duplicate->set('field_workspace', [
        'target_id' => $workspace->id(),
      ]);
    }

    // Duplicate documents library.
    $tid = $group->get('field_learning_path_folder')->getString();
    $items_list = $this->documentsLibraryList($tid);

    $main_tid = $duplicate->get('field_learning_path_folder')->getString();
    $tids_relashionships = [];
    $tids_relashionships[$tid] = $main_tid;

    foreach ($items_list as $item) {
      if ($item['type'] === 'file') {
        $media = $this->mediaStorage->load($item['id']);
        $media_copy = $media->createDuplicate();

        $old_tid = $media->get('tft_folder')->getValue()[0]['target_id'];
        if (isset($tids_relashionships[$old_tid])) {
          $new_tid = $tids_relashionships[$old_tid];
        }
        else {
          $new_tid = $main_tid;
        }

        $media_copy->set('tft_folder', $new_tid);
        $media_copy->save();
      }
      elseif ($item['type'] === 'term') {
        $old_term = $this->termStorage->load($item['id']);

        if ($old_term) {
          $legacy_parent_id = $old_term->get('parent')->getValue()[0]['target_id'];
          if ($tids_relashionships[$legacy_parent_id]) {
            $parent = $tids_relashionships[$legacy_parent_id];
          }
          else {
            $parent = $main_tid;
          }

          $new_term = Term::create([
            'vid' => 'tft_tree',
            'name' => $item['name'],
            'parent' => $parent,
          ]);
          $new_term->save();
          $new_term_id = $new_term->id();

          $tids_relashionships[$item['id']] = $new_term_id;
        }
      }
    }

    $duplicate_id = $duplicate->id();

    $group_content = $group->getContentEntities();

    foreach ($group_content as $content) {
      if ($content instanceof OpignoModule || $content instanceof Group) {

        if ($content instanceof OpignoModule) {
          $duplicate->addContent($content, 'opigno_module_group');
          $group_content_type_id = 'ContentTypeModule';
        }
        else {
          $duplicate->addContent($content, 'subgroup:opigno_course');
          $group_content_type_id = 'ContentTypeCourse';
        }

        $managed_content_array = OpignoGroupManagedContent::loadByProperties([
          'group_content_type_id' => $group_content_type_id,
          'entity_id' => $content->id(),
          'group_id' => $group->id(),
        ]);

        $managed_content = reset($managed_content_array);

        if (!($managed_content instanceof OpignoGroupManagedContent)) {
          continue 1;
        }

        $new_content = OpignoGroupManagedContent::createWithValues(
          $duplicate->id(),
          $managed_content->getGroupContentTypeId(),
          $content->id(),
          $managed_content->getSuccessScoreMin(),
          $managed_content->isMandatory(),
          $managed_content->getCoordinateX(),
          $managed_content->getCoordinateY()
        );

        $new_content->save();

        $parent_links = [];
        try {
          $parent_links = $managed_content->getParentsLinks();
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException $exception) {
          watchdog_exception('error', $exception);
        }

        foreach ($parent_links as $link) {
          $parent_old_content = OpignoGroupManagedContent::load($link->getParentContentId());
          $parent_module_id = $parent_old_content->getEntityId();

          $parent_new_content_array = OpignoGroupManagedContent::loadByProperties([
            'group_content_type_id' => 'ContentTypeModule',
            'entity_id' => $parent_module_id,
            'group_id' => $duplicate_id,
          ]);

          $parent_new_content = reset($parent_new_content_array);

          if ($parent_new_content) {
            OpignoGroupManagedLink::createWithValues(
              $duplicate_id,
              $parent_new_content->id(),
              $new_content->id(),
              $link->getRequiredScore(),
              $link->getRequiredActivities() ? serialize($link->getRequiredActivities()) : NULL
            )->save();
          }
        }
      }
    }

    $duplicate->save();

    return $this->redirect('entity.group.edit_form', [
      'group' => $duplicate_id,
    ]);
  }

  /**
   * Helper function.
   *
   * Get a list of all files and folders from the documents library.
   */
  public function documentsLibraryList($tid) {
    $main_content = _tft_folder_content($tid);
    $all_content = [];

    foreach ($main_content as $content) {
      $all_content[] = $content;

      if ($content['type'] === 'term') {
        $term_content = $this->documentsLibraryList($content['id']);
        $all_content = array_merge($all_content, $term_content);
      }
    }

    return $all_content;
  }

  /**
   * Check if step is required meeting.
   *
   * @param array $step
   *   Step options.
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return bool
   *   If the step is required meeting or not.
   */
  public static function mustBeVisitedMeeting(array $step, Group $group): bool {
    if (self::getGuidedNavigation($group)
      && $step['typology'] === 'Meeting'
      && $step['mandatory'] == 1
      && empty($step['presence'])
    ) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Returns default image url.
   *
   * @param string $bundle
   *   The bundle name.
   *
   * @return string
   *   The default image url.
   */
  public static function getDefaultBundleImageUrl(string $bundle): string {
    if ($bundle === 'opigno_course') {
      $module = 'opigno_course';
      $image = 'img_course.svg';
    }
    else {
      $module = 'opigno_module';
      $image = 'img_module.svg';
    }

    $path = \Drupal::moduleHandler()->getModule($module)->getPath();
    return \Drupal::request()->getBasePath() . '/' . $path . '/img/' . $image;
  }

  /**
   * Logging an export/import process.
   *
   * @param \Drupal\Core\Entity\EntityInterface $opigno_activity
   *   The Opigno activity entity.
   */
  private function addLogAndMessage(EntityInterface $opigno_activity): void {
    $log_msg = $this->t('H5P Content @id has not been loaded during a @type export.', [
      '@id' => $opigno_activity->id(),
      '@type' => $opigno_activity->bundle(),
    ], ['context' => 'opigno_export_import']);

    $this->logger->notice($log_msg);
    $this->messenger->addWarning($log_msg);
  }

}
