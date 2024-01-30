<?php

namespace Drupal\opigno_scorm;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the service to work with a SCORM player.
 *
 * @package Drupal\opigno_scorm
 */
class OpignoScormPlayer {

  use StringTranslationTrait;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Opigno SCORM service.
   *
   * @var \Drupal\opigno_scorm\OpignoScorm
   */
  protected $scormService;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * OpignoScormPlayer constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Drupal\opigno_scorm\OpignoScorm $scorm_service
   *   The Opigno SCORM service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger service.
   */
  public function __construct(
    Connection $database,
    OpignoScorm $scorm_service,
    AccountInterface $account,
    MessengerInterface $messenger,
    LoggerChannelFactoryInterface $logger
  ) {
    $this->database = $database;
    $this->scormService = $scorm_service;
    $this->account = $account;
    $this->messenger = $messenger;
    $this->logger = $logger->get('opigno_scorm');
  }

  /**
   * Build rendarable array for scorm package output.
   */
  public function toRendarableArray($scorm) {
    $error_msg = $this->t('Invalid SCORM package.');
    if (!$scorm) {
      $this->messenger->addError($error_msg);
      $this->logger->error($error_msg);
      return [];
    }

    $uid = $this->account->id();
    // Get SCORM API version.
    $metadata = unserialize($scorm->metadata, ['allowed_classes' => FALSE]);
    $scorm_version = str_contains($metadata['schemaversion'], '1.2') ? '1.2' : '2004';

    // Get the SCO tree.
    $tree = $this->opignoScormPlayerScormTree($scorm);
    $flat_tree = $this->opignoScormPlayerFlattenTree($tree);

    // Get the start SCO.
    $start_sco = $this->opignoScormPlayerStartSco($flat_tree);
    if (!$start_sco) {
      $this->messenger->addError($error_msg);
      $this->logger->error($error_msg);
      return NULL;
    }

    // @todo Replace with custom event subscriber implementation.
    // Get implemented CMI paths.
    $paths = opigno_scorm_add_cmi_paths($scorm_version);

    // Get CMI data for each SCO.
    $data = opigno_scorm_add_cmi_data($scorm, $flat_tree, $scorm_version);

    // Disallow SCORM package resuming for anonymous.
    if ($uid === 0 && $scorm_version == '1.2') {
      $data['cmi.core.lesson_location'] = '';
      $data['cmi.core.lesson_status'] = '';
      $data['cmi.core.exit'] = '';
      $data['cmi.core.entry'] = '';
      $data['cmi.core.student_id'] = '';
      $data['cmi.core.student_name'] = '';
      $data['cmi.student_preference._children'] = '';
      $data['cmi.student_preference.audio'] = '';
      $data['cmi.student_preference.language'] = '';
      $data['cmi.student_preference.speed'] = '';
      $data['cmi.student_preference.text'] = '';
      $data['cmi.core.score._children'] = '';
      $data['cmi.suspend_data'] = '';
      if (!empty($data['cmi.objectives'])) {
        foreach ($data['cmi.objectives'] as &$objective) {
          $objective['score']->raw = 0;
          $objective['score']->min = 0;
          $objective['score']->max = 0;
          $objective['status'] = '';
        }
      }
    }
    if ($uid === 0 && $scorm_version == '2004') {
      $data['cmi.location'] = '';
      $data['cmi.completion_status'] = 'unknown';
      $data['cmi.exit'] = '';
      $data['cmi.entry'] = '';
      $data['cmi.learner_id'] = '';
      $data['cmi.learner_name'] = '';
      $data['cmi.learner_preference._children'] = '';
      $data['cmi.learner_preference.audio_level'] = '';
      $data['cmi.learner_preference.language'] = '';
      $data['cmi.learner_preference.delivery_speed'] = '';
      $data['cmi.learner_preference.audio_captioning'] = '';
      $data['cmi.success_status'] = '';
      $data['cmi.suspend_data'] = '';

      if (!empty($data['cmi.objectives'])) {
        foreach ($data['cmi.objectives'] as &$objective) {
          $objective['score']->scaled = 0;
          $objective['score']->raw = 0;
          $objective['score']->min = 0;
          $objective['score']->max = 0;
          $objective['success_status'] = '';
          $objective['completion_status'] = '';
          $objective['progress_measure'] = '';
        }
      }
    }

    $sco_identifiers = [];
    $scos_suspend_data = [];
    foreach ($flat_tree as $sco) {
      if ($sco->scorm_type == 'sco') {
        $sco_identifiers[$sco->identifier] = $sco->id;
        $scos_suspend_data[$sco->id] = opigno_scorm_scorm_cmi_get($uid, $scorm->id, 'cmi.suspend_data.' . $sco->id, '');
      }
    }
    $last_user_sco = opigno_scorm_scorm_cmi_get($uid, $scorm->id, 'user.sco', '');
    if ($last_user_sco != '') {
      foreach ($flat_tree as $sco) {
        if ($last_user_sco == $sco->id && !empty($sco->launch)) {
          $start_sco = $sco;
        }
      }
    }
    // Add base path for player link.
    global $base_path;
    $start_sco->base_path = $base_path;
    return [
      '#theme' => 'opigno_scorm__player',
      '#scorm_id' => $scorm->id,
      '#tree' => count($flat_tree) == 2 ? NULL : $tree,
      '#start_sco' => $start_sco,
      '#attached' => [
        'library' => ['opigno_scorm/opigno-scorm-player'],
        'drupalSettings' => [
          'opignoScormUIPlayer' => [
            'cmiPaths' => $paths,
            'cmiData' => $data,
            'scoIdentifiers' => $sco_identifiers,
            'cmiSuspendItems' => $scos_suspend_data,
          ],
          'scormVersion' => $scorm_version,
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Traverse the SCORM package data and construct a SCO tree.
   *
   * @param object $scorm
   *   Scorm object.
   * @param int|string $parent_identifier
   *   Parent identifier.
   *
   * @return array
   *   SCO tree.
   */
  private function opignoScormPlayerScormTree(object $scorm, int|string $parent_identifier = 0): array {
    $tree = [];

    $result = $this->database->select('opigno_scorm_package_scos', 'sco')
      ->fields('sco', ['id'])
      ->condition('sco.scorm_id', $scorm->id)
      ->condition('sco.parent_identifier', $parent_identifier)
      ->execute();

    while ($sco_id = $result->fetchField()) {
      $sco = $this->scormService->scormLoadSco($sco_id);
      $children = $this->opignoScormPlayerScormTree($scorm, $sco->identifier);
      $sco->children = $children;
      $tree[] = $sco;
    }

    return $tree;
  }

  /**
   * Helper function to flatten the SCORM tree.
   *
   * @param array $tree
   *   Tree.
   *
   * @return array
   *   SCORM tree.
   */
  private function opignoScormPlayerFlattenTree(array $tree): array {
    $items = [];

    if (!empty($tree)) {
      foreach ($tree as $sco) {
        $items[] = $sco;
        if (!empty($sco->children)) {
          $items = array_merge($items, $this->opignoScormPlayerFlattenTree($sco->children));
        }
      }
    }

    return $items;
  }

  /**
   * Determine the start SCO for the SCORM package.
   *
   * @todo Get last viewed SCO.
   *
   * @param array $flat_tree
   *   Flat tree.
   *
   * @return object
   *   Start SCO.
   */
  private function opignoScormPlayerStartSco(array $flat_tree) {
    foreach ($flat_tree as $sco) {
      if (!empty($sco->launch)) {
        return $sco;
      }
    }

    // Failsafe. Just get the first element.
    return array_shift($flat_tree);
  }

}
