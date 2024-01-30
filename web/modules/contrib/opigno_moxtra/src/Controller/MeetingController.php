<?php

namespace Drupal\opigno_moxtra\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\group\Entity\Group;
use Drupal\opigno_moxtra\MeetingInterface;
use Drupal\opigno_moxtra\MoxtraServiceInterface;
use Drupal\opigno_moxtra\MoxtraConnector;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\opigno_learning_path\LearningPathAccess;

/**
 * Class MeetingController.
 */
class MeetingController extends ControllerBase {

  /**
   * Opigno service.
   *
   * @var \Drupal\opigno_moxtra\MoxtraConnector
   */
  protected $moxtraConnector;

  /**
   * Moxtra service.
   *
   * @var \Drupal\opigno_moxtra\MoxtraServiceInterface
   */
  protected $moxtraService;

  /**
   * Creates new MeetingController instance.
   *
   * @param \Drupal\opigno_moxtra\MoxtraConnector $opigno_service
   *   Opigno API service.
   * @param \Drupal\opigno_moxtra\MoxtraServiceInterface $moxtra_service
   *   Moxtra API service.
   */
  public function __construct(
    MoxtraConnector $moxtra_connector,
    MoxtraServiceInterface $moxtra_service
  ) {
    $this->moxtraConnector = $moxtra_connector;
    $this->moxtraService = $moxtra_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_moxtra.connector'),
      $container->get('opigno_moxtra.moxtra_api')
    );
  }

  /**
   * Returns render array for the navigation.
   *
   * @param \Drupal\opigno_moxtra\MeetingInterface $opigno_moxtra_meeting
   *   Moxtra meeting.
   *
   * @return array
   *   Render array.
   */
  protected function buildNavigation(MeetingInterface $opigno_moxtra_meeting) {
    $gid = $opigno_moxtra_meeting->getTrainingId();
    if (empty($gid)) {
      return [];
    }

    $actions = [];
    $actions['form-actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-actions','action-wrapper'],
        'id' => 'edit-actions',
      ],
      '#title' => 'test',
    ];

    $title = $this->t('Back to training homepage');
    $route = 'entity.group.canonical';
    $route_params = [
      'group' => $gid,
    ];
    $options = [
      'attributes' => [
        'class' => [
          'btn',
          'btn-rounded',
        ],
        'id' => 'edit-submit',
      ],
    ];

    $actions['form-actions'][] = Link::createFromRoute(
      $title,
      $route,
      $route_params,
      $options
    )->toRenderable();

    return $actions;
  }

  /**
   * Returns render array for the scheduled live meeting.
   *
   * @param \Drupal\opigno_moxtra\MeetingInterface $opigno_moxtra_meeting
   *   The Live Meeting.
   *
   * @return array
   *   Render array.
   */
  protected function buildMeetingScheduled(MeetingInterface $opigno_moxtra_meeting) {
    $training_id = $opigno_moxtra_meeting->getTrainingId();
    $user = $this->currentUser();

    if (!$user->hasPermission('start meeting')) {
      return [
        '#type' => 'container',
        'message' => [
          '#markup' => '<div class="meeting-ended content-box">' . $this->t('This live meeting has not started yet<br />Come back later...') . '<i class="icon-checked-circle"></i></div>',
        ],
        'navigation' => $this->buildNavigation($opigno_moxtra_meeting),
      ];
    }

    $config = $this->config('opigno_moxtra.settings');
    $client_id = $config->get('client_id');
    $org_id = $config->get('org_id');

    $access_token = $this->moxtraConnector->getToken();
    $binder_id = $opigno_moxtra_meeting->getBinderId();
    $session_key = $opigno_moxtra_meeting->getSessionKey();
    $topic = $opigno_moxtra_meeting->getTitle();
    $invitees = [];

    $members = $opigno_moxtra_meeting->getMembers();
    foreach ($members as $member) {
      $prefix = $this->moxtraConnector->prefix($member);
      $invitees[] = ['unique_id' => $prefix . $member->id()];
    }

    $url = $this->moxtraConnector->getUrl();

    return [
      '#type' => 'container',
      'meeting_wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['content-box', 'meeting-start-container'],
        ],
        'meeting_container' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'live-meeting-container',
          ],
        ],
        'start_btn' => [
          '#markup' => '<div class="start-meeting"><a href="#" id="start-meeting">' . $this->t('Start the live meeting') . '<i class="fi fi-rr-play-alt"></i></div></a>',
        ],
      ],
      'navigation' => $this->buildNavigation($opigno_moxtra_meeting),
      '#attached' => [
        'library' => [
          'opigno_moxtra/moxtra.js',
          'opigno_moxtra/meeting_scheduled',
        ],
        'drupalSettings' => [
          'opignoMoxtra' => [
            'mode' => 'production',
            'clientId' => $client_id,
            'orgId' => $org_id,
            'accessToken' => $access_token,
            'binderId' => $binder_id,
            'sessionKey' => $session_key,
            'topic' => $topic,
            'baseDomain' => $url,
            'invitees' => $invitees,
          ],
        ],
      ],
    ];
  }

  /**
   * Returns render array for the started live meeting.
   *
   * @param \Drupal\opigno_moxtra\MeetingInterface $opigno_moxtra_meeting
   *   The Live Meeting.
   *
   * @return array
   *   Render array.
   */
  protected function buildMeetingStarted(MeetingInterface $opigno_moxtra_meeting) {

    $config = $this->config('opigno_moxtra.settings');
    $client_id = $config->get('client_id');
    $org_id = $config->get('org_id');

    $access_token = $this->moxtraConnector->getToken();
    $binder_id = $opigno_moxtra_meeting->getBinderId();
    $topic = $opigno_moxtra_meeting->getTitle();
    $invitees = [];
    $members = $opigno_moxtra_meeting->getMembers();
    foreach ($members as $member) {
      $prefix = $this->moxtraConnector->prefix($member);
      $invitees[] = ['unique_id' => $prefix . $member->id()];
    }
    $url = $this->moxtraConnector->getUrl();

    $session_key = $opigno_moxtra_meeting->getSessionKey();

    return [
      '#type' => 'container',
      'meeting_wrapper' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['content-box', 'meeting-started'],
        ],
        'meeting_container' => [
          '#type' => 'container',
          '#attributes' => [
            'id' => 'live-meeting-container',
          ],
        ],
        'max_reached' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'id' => 'max_reached',
            'style' => 'display: none;',
          ],
          '#value' => $this->t('The maximum number of users for this meeting is reached.'),
        ],
      ],
      'navigation' => $this->buildNavigation($opigno_moxtra_meeting),
      '#attached' => [
        'library' => [
          'opigno_moxtra/moxtra.js',
          'opigno_moxtra/meeting_started',
        ],
        'drupalSettings' => [
          'opignoMoxtra' => [
            'mode' => 'production',
            'clientId' => $client_id,
            'orgId' => $org_id,
            'accessToken' => $access_token,
            'binderId' => $binder_id,
            'sessionKey' => $session_key,
            'topic' => $topic,
            'baseDomain' => $url,
            'invitees' => $invitees,
          ],
        ],
      ],
    ];
  }

  /**
   * Returns render array for the ended live meeting.
   *
   * @param \Drupal\opigno_moxtra\MeetingInterface $opigno_moxtra_meeting
   *   The Live Meeting.
   *
   * @return array
   *   Render array.
   */
  protected function buildMeetingEnded(MeetingInterface $opigno_moxtra_meeting) {
    return [
      '#type' => 'container',
      'message' => [
        '#markup' => '<div class="meeting-ended content-box">' . $this->t('This live meeting has ended.') . '<i class="icon-checked-circle"></i></div>',
      ],
      'navigation' => $this->buildNavigation($opigno_moxtra_meeting),
    ];
  }

  /**
   * Returns index page for the live meeting.
   *
   * @param \Drupal\opigno_moxtra\MeetingInterface $opigno_moxtra_meeting
   *   The Live Meeting.
   *
   * @return array
   *   Render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function index(MeetingInterface $opigno_moxtra_meeting) {
    $owner_id = $opigno_moxtra_meeting->getOwnerId();
    $session_key = $opigno_moxtra_meeting->getSessionKey();
    $info = $this->moxtraService->getMeetingInfo($owner_id, $session_key);

    $content = [];
    $status = !empty($info['data']) ? $info['data']['status'] : FALSE;

    switch ($status) {
      case 'SESSION_SCHEDULED':
        $content[] = $this->buildMeetingScheduled($opigno_moxtra_meeting);
        break;

      case 'SESSION_STARTED':
        $content[] = $this->buildMeetingStarted($opigno_moxtra_meeting);
        break;

      case 'SESSION_ENDED':
        $uid = $this->currentUser()->id();
        $gid = $opigno_moxtra_meeting->getTrainingId();
        if (isset($gid) && $opigno_moxtra_meeting->isMember($uid)) {
          // Update user achievements.
          $step = opigno_learning_path_get_meeting_step(
            $gid,
            $uid,
            $opigno_moxtra_meeting
          );
          opigno_learning_path_save_step_achievements($gid, $uid, $step, 0);
          opigno_learning_path_save_achievements($gid, $uid);
        }

        $content[] = $this->buildMeetingEnded($opigno_moxtra_meeting);
        break;
    }

    return $content;
  }

  /**
   * Returns title for the live meeting.
   *
   * @param \Drupal\opigno_moxtra\MeetingInterface $opigno_moxtra_meeting
   *   The Live Meeting.
   *
   * @return string
   *   Meeting title.
   */
  public function title(MeetingInterface $opigno_moxtra_meeting) {
    return $opigno_moxtra_meeting->getTitle();
  }

  /**
   * Returns response for the autocompletion.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function membersAutocompleteSelect(Group $group) {
    $matches = [];
    $search = \Drupal::request()->query->get('q');
    if (!isset($search)) {
      $search = '';
    }

    if ($group !== NULL) {
      $training_members = $group->getMembers();
      $training_users = array_map(function ($member) {
        /** @var \Drupal\group\GroupMembership $member */
        return $member->getUser();
      }, $training_members);
      foreach ($training_users as $user) {
        $id = $user->id();
        $name = $user->getDisplayName();
        $label = $this->t("@name (User #@id)", [
          '@name' => $name,
          '@id' => $id,
        ]);

        $matches['user_' . $id] = [
          'value' => $label,
          'label' => $label,
          'type' => 'user',
          'id' => 'user_' . $id,
          'name' => $name,
        ];
      }

      /** @var \Drupal\group\Entity\Group[] $classes */
      $classes = $group->getContentEntities('subgroup:opigno_class');
      foreach ($classes as $class) {
        $id = $class->id();
        $name = $class->label();
        $label = $this->t("@name (Group #@id)", [
          '@name' => $name,
          '@id' => $id,
        ]);

        $matches['class_' . $id] = [
          'value' => $label,
          'label' => $label,
          'type' => 'group',
          'id' => 'class_' . $id,
          'name' => $name,
        ];
      }

      $search = strtoupper($search);
      if($search) {
        $matches = array_filter($matches, function ($match) use ($search) {
          $name = strtoupper($match['name']);
          return strpos($name, $search) !== FALSE;
        });
      }
    }

    return [$matches, NULL];
  }


  /**
   * Returns response for the autocompletion.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function membersAutocomplete(Group $group) {
    [$matches, $default] = $this->membersAutocompleteSelect($group);
    return new JsonResponse($matches);
  }
}
