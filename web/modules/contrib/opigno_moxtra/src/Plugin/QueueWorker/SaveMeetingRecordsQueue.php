<?php

namespace Drupal\opigno_moxtra\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\Entity\Media;
use Drupal\opigno_moxtra\MoxtraServiceInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Save meeting records on CRON run.
 *
 * @QueueWorker(
 *   id = "opigno_moxtra_save_meeting_records",
 *   title = @Translation("Save meeting records on CRON run"),
 *   cron = {"time" = 20}
 * )
 */
class SaveMeetingRecordsQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * Moxtra service.
   *
   * @var \Drupal\opigno_moxtra\MoxtraServiceInterface
   */
  protected $moxtraService;


  /**
   * Constructs a new MessageDeletionWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, MoxtraServiceInterface $moxtra_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->moxtraService = $moxtra_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('opigno_moxtra.moxtra_api')
    );
  }

  /**
   * Helper function that returns tid of the live meetings recordings folder.
   *
   * Creates folder if it is not exists.
   *
   * @param int $group_id
   *   Group ID.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   Taxonomy term ID of the recordings folder.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getRecordingsFolder($group_id) {
    $results = &drupal_static(__FUNCTION__);
    if (isset($results[$group_id])) {
      return $results[$group_id];
    }

    // Get the tid of the folder of the group.
    $group_folder_tid = _tft_get_group_tid($group_id);
    if ($group_folder_tid === NULL) {
      return NULL;
    }

    $recording_folder_name = $this->t('Recorded Live Meetings');

    // Try get folder for the live meetings recordings.
    $recording_folder = NULL;
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $children = $storage->loadChildren($group_folder_tid);
    foreach ($children as $child) {
      if ($child->label() === (string) $recording_folder_name) {
        $recording_folder = $child;
        break;
      }
    }

    // Create folder for the live meetings recordings if it is not exists.
    if (!isset($recording_folder)) {
      $recording_folder = Term::create([
        'vid' => 'tft_tree',
        'name' => $recording_folder_name,
        'parent' => $group_folder_tid,
      ]);
      $recording_folder->save();
    }

    return $results[$group_id] = $recording_folder;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    // Get group.
    $group = $this->entityTypeManager->getStorage('group')->load($data->gid);
    if (!isset($group)) {
      return;
    }

    // Check group type.
    if ($group->getGroupType()->id() !== 'learning_path') {
      return;
    }

    /** @var \Drupal\opigno_moxtra\MeetingInterface[] $meetings */
    $meetings = $group->getContentEntities('opigno_moxtra_meeting_group');
    foreach ($meetings as $meeting) {
      $owner_id = $meeting->getOwnerId();
      // Check the live meeting status.
      $session_key = $meeting->getSessionKey();
      if (empty($session_key)) {
        continue;
      }

      $info = $this->moxtraService->getMeetingInfo($owner_id, $session_key);
      $status = $info['data']['status'];
      if ($status !== 'SESSION_ENDED') {
        continue;
      }

      // Check live meeting has recordings.
      $info = $this->moxtraService->getMeetingRecordingInfo($owner_id, $session_key);
      if (((int) $info['data']['count']) === 0) {
        continue;
      }
      $recordings = array_map(function ($recording) {
        return $recording['download_url'];
      }, $info['data']['recordings']);

      // Get the recordings folder.
      $group_id = $group->id();
      $folder = $this->getRecordingsFolder($group_id);
      if (!isset($folder)) {
        continue;
      }

      // Get the files.
      $fids = \Drupal::entityQuery('media')
        ->condition('bundle', 'tft_file')
        ->condition('tft_folder.target_id', $folder->id())
        ->execute();

      /** @var \Drupal\media\MediaInterface[] $files */
      $files = Media::loadMultiple($fids);

      foreach ($recordings as $recording) {
        // Check that file for this live meeting recording
        // is not already exists.
        $exists = FALSE;
        foreach ($files as $file) {
          if (!$file->hasField('opigno_moxtra_recording_link')) {
            continue;
          }

          $link = $file->get('opigno_moxtra_recording_link')->getValue();
          if (!empty($link)) {
            $url = $link[0]['uri'];
            if ($url === $recording) {
              $exists = TRUE;
              break;
            }
          }
        }

        // Save the live meeting recording.
        if (!$exists) {
          $members = $meeting->getMembersIds();
          if (empty($members)) {
            $training = $meeting->getTraining();
            if (isset($training)) {
              $members = array_map(function ($membership) {
                /** @var \Drupal\group\GroupMembership $membership */
                return $membership->getUser()->id();
              }, $training->getMembers());
            }
          }

          $file = Media::create([
            'bundle' => 'tft_file',
            'name' => $meeting->label(),
            'uid' => $owner_id,
            'opigno_moxtra_recording_link' => [
              'uri' => $recording,
            ],
            'tft_folder' => [
              'target_id' => $folder->id(),
            ],
            'tft_members' => $members,
          ]);
           return $file->save();
        }
      }
    }
  }
}
