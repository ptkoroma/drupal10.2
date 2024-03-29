<?php

namespace Drupal\h5p\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\h5p\Event\FinishedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class H5PAJAX extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Symfony Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new H%PAJAX controller.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The Symfony Event dispatcher.
   */
  public function __construct(Connection $database, EventDispatcherInterface $event_dispatcher) {
    $this->database = $database;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    $controller = new static(
      $container->get('database'),
      $container->get('event_dispatcher')
    );
    return $controller;
  }

  /**
   * Access callback for the setFinished feature
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The AJAX response.
   */
  public function setFinished() {
    // Inputs are found as POST parameters
    $content_id = filter_input(INPUT_POST, 'contentId', FILTER_VALIDATE_INT);
    $score = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);
    $max_score = filter_input(INPUT_POST, 'maxScore', FILTER_VALIDATE_INT);
    $opened = filter_input(INPUT_POST, 'opened', FILTER_VALIDATE_INT);
    $token = filter_input(INPUT_GET, 'token');

    $uid = $this->currentUser()->id();

    // Validate input - all parameters needed
    if (! ($content_id && $score !== NULL && $score !== FALSE && $max_score !== NULL && $max_score !== FALSE && $opened && $token && $uid)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => t('Wrong usage'),
      ]);
    }

    // Check token
    if (! \H5PCore::validToken('result', $token)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => t('Invalid security token'),
      ]);
    }

    // Everything is OK - let's update db
    // Check if it exists
    $exists = $this->database->query('SELECT 1 FROM {h5p_points} WHERE content_id = :content_id AND uid = :uid', [
        ':content_id' => $content_id,
        ':uid' => $uid,
      ])->fetchField();

    // which fields to update
    $fields = [
      'content_id' => $content_id,
      'uid' => $uid,
      'finished' => time(),
      'points' => $score,
      'max_points' => $max_score,
    ];

    // Only set started time if it does not exist
    if (! $exists) {
      $fields['started'] = $opened;
    }

    $this->database->merge('h5p_points')
      ->key([
        'uid' => $uid,
        'content_id' => $content_id,
      ])
      ->fields($fields)
      ->execute();

    $this->eventDispatcher->dispatch(new FinishedEvent($fields), FinishedEvent::FINISHED_EVENT);

    return new JsonResponse(['success' => TRUE]);
  }

  /**
   * Handles insert, updating and deleteing content user data through AJAX.
   *
   * @param string $content_main_id
   * @param string $data_id
   * @param string $sub_content_id
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The AJAX response.
   */
  public function contentUserData($content_main_id, $data_id, $sub_content_id) {
    $user = $this->currentUser();

    $response = (object) array(
      'success' => TRUE
    );

    $data = filter_input(INPUT_POST, 'data');
    $preload = filter_input(INPUT_POST, 'preload');
    $invalidate = filter_input(INPUT_POST, 'invalidate');
    if ($data !== NULL && $preload !== NULL && $invalidate !== NULL) {
      if (! \H5PCore::validToken('contentuserdata', filter_input(INPUT_GET, 'token'))) {
        $response->success = FALSE;
        $response->message = t('Invalid security token.');
        return new JsonResponse($response);
      }

      if ($data === '0') {
        // Remove data
        $this->database->delete('h5p_content_user_data')
          ->condition('content_main_id', $content_main_id)
          ->condition('data_id', $data_id)
          ->condition('user_id', $user->id())
          ->condition('sub_content_id', $sub_content_id)
          ->execute();
      } else {
        // Wash values to ensure 0 or 1.
        $preload = ($preload === '0' ? 0 : 1);
        $invalidate = ($invalidate === '0' ? 0 : 1);

        // Determine if we should update or insert
        $update = $this->database->query("
          SELECT content_main_id
          FROM {h5p_content_user_data}
          WHERE content_main_id = :content_main_id
          AND user_id = :user_id
          AND data_id = :data_id
          AND sub_content_id = :sub_content_id",
          [
            ':content_main_id' => $content_main_id,
            ':user_id' => $user->id(),
            ':data_id' => $data_id,
            ':sub_content_id' => $sub_content_id,
          ])->fetchField();

        if ($update === FALSE) {
          // Insert new data
          $this->database->insert('h5p_content_user_data')
            ->fields([
              'user_id' => $user->id(),
              'content_main_id' => $content_main_id,
              'sub_content_id' => $sub_content_id,
              'data_id' => $data_id,
              'timestamp' => time(),
              'data' => $data,
              'preloaded' => $preload,
              'delete_on_content_change' => $invalidate
            ])
            ->execute();
        }
        else {
          // Update old data
          $this->database->update('h5p_content_user_data')
            ->fields([
              'timestamp' => time(),
              'data' => $data,
              'preloaded' => $preload,
              'delete_on_content_change' => $invalidate
            ])
            ->condition('user_id', $user->id())
            ->condition('content_main_id', $content_main_id)
            ->condition('data_id', $data_id)
            ->condition('sub_content_id', $sub_content_id)
            ->execute();
        }
      }

      Cache::invalidateTags(['h5p_content:' . $content_main_id]);
    } else {
      // Fetch data
      $response->data = $this->database->query("
        SELECT data FROM {h5p_content_user_data}
        WHERE user_id = :user_id
        AND content_main_id = :content_main_id
        AND data_id = :data_id
        AND sub_content_id = :sub_content_id",
        [
          ':user_id' => $user->id(),
          ':content_main_id' => $content_main_id,
          ':sub_content_id' => $sub_content_id,
          ':data_id' => $data_id,
        ])->fetchField();
    }

    return new JsonResponse($response);
  }
}
