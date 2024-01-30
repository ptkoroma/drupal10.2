<?php

namespace Drupal\opigno_moxtra;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\Entity\User;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements Moxtra REST API.
 */
class MoxtraService implements MoxtraServiceInterface {

  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Moxtra connector service.
   *
   * @var \Drupal\opigno_moxtra\MoxtraConnector
   */
  protected $moxtraConnector;

  /**
   * Creates a MoxtraService instance.
   */
  public function __construct(
    TranslationInterface $translation,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger,
    ClientInterface $http_client,
    MoxtraConnector $opigno_connector
  ) {
    $this->setStringTranslation($translation);
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('opigno_moxtra');
    $this->messenger = $messenger;
    $this->httpClient = $http_client;
    $this->moxtraConnector = $opigno_connector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('http_client'),
      $container->get('opigno_moxtra.connector')
    );
  }

  /**
   * Returns URL to list the binders.
   *
   * @param int $owner_id
   *   User ID.
   *
   * @return string
   *   URL.
   */
  protected function getBinderListUrl($owner_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/me/binders?access_token={$token}";
  }

  /**
   * Returns URL to create the binder.
   *
   * @param int $owner_id
   *   User ID.
   *
   * @return string
   *   URL.
   */
  protected function getCreateBinderUrl($owner_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/me/binders?access_token={$token}";
  }

  /**
   * Returns URL to update the binder.
   *
   * @param string $binder_id
   *   Binder ID.
   *
   * @return string
   *   URL.
   */
  protected function getUpdateBinderUrl($owner_id, $binder_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/$binder_id?access_token={$token}";
  }

  /**
   * Returns URL to delete the binder.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $binder_id
   *   Binder ID.
   *
   * @return string
   *   URL.
   */
  protected function getDeleteBinderUrl($owner_id, $binder_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl(). "/v1/$binder_id?access_token={$token}";
  }

  /**
   * Returns URL to send a message to the the binder.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $binder_id
   *   Binder ID.
   *
   * @return string
   *   URL.
   */
  protected function getSendMessageUrl($owner_id, $binder_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/$binder_id/comments?access_token={$token}";
  }

  /**
   * Returns URL to add the users to the binder.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $binder_id
   *   Binder ID.
   *
   * @return string
   *   URL.
   */
  protected function getAddUsersUrl($owner_id, $binder_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/$binder_id/addorguser?access_token={$token}";
  }

  /**
   * Returns URL to remove the user from the binder.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $binder_id
   *   Binder ID.
   *
   * @return string
   *   URL.
   */
  protected function getRemoveUserUrl($owner_id, $binder_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/$binder_id/removeuser?access_token={$token}";
  }

  /**
   * Returns URL to get the meeting info.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $session_key
   *   Session key of the Live Meeting.
   *
   * @return string
   *   URL.
   */
  protected function getMeetingInfoUrl($owner_id, $session_key) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/meets/$session_key?access_token={$token}";
  }

  /**
   * Returns URL to schedule a meeting.
   *
   * @param int $owner_id
   *   User ID.
   *
   * @return string
   *   URL.
   */
  protected function getCreateMeetingUrl($owner_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/meets/schedule?access_token={$token}";
  }

  /**
   * Returns URL to update the meeting.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $session_key
   *   Session key of the Live Meeting.
   *
   * @return string
   *   URL.
   */
  protected function getUpdateMeetingUrl($owner_id, $session_key) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/meets/$session_key?access_token={$token}";
  }

  /**
   * Returns URL to delete a meeting.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $session_key
   *   Session key of the Live Meeting.
   *
   * @return string
   *   URL.
   */
  protected function getDeleteMeetingUrl($owner_id, $session_key) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/meets/$session_key?access_token={$token}";
  }

  /**
   * Returns URL to get a meeting files list.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $binder_id
   *   Binder ID of the Binder related to the Live Meeting.
   *
   * @return string
   *   URL.
   */
  protected function getMeetingFilesListUrl($owner_id, $binder_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/$binder_id/files?access_token={$token}";
  }

  /**
   * Returns URL to get a meeting file info.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $binder_id
   *   Binder ID of the Binder related to the Live Meeting.
   * @param string $file_id
   *   File ID.
   *
   * @return string
   *   URL.
   */
  protected function getMeetingFileInfoUrl($owner_id, $binder_id, $file_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/$binder_id/files/$file_id?access_token={$token}";
  }

  /**
   * Returns URL to get a meeting recording info.
   *
   * @param int $owner_id
   *   User ID.
   * @param string $session_key
   *   Session ID of the related to the Live Meeting.
   *
   * @return string
   *   URL.
   */
  protected function getMeetingRecordingInfoUrl($owner_id, $session_key) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/meets/recordings/$session_key?access_token={$token}";
  }


  /**
   * Returns URL to add the users to the meeting.
   *
   * @param int $owner_id
   *   User ID.
   *
   * @return string
   *   URL.
   */
  protected function getAddUsersToMeetingUrl($owner_id) {
    $token = $this->moxtraConnector->getToken($owner_id);
    return $this->moxtraConnector->getUrl() . "/v1/meets/inviteuser?access_token={$token}";
  }

  /**
   * Add the users to the meeting.
   *
   * @param int $owner_id
   *   User ID.
   * @param int $session_key
   *   Meeting session key.
   * @param array $users
   *   List of options for users.
   */
  public function AddUsersToMeeting($owner_id, $session_key, $users) {
    $data = [
      'session_key' => $session_key,
      'users' => $users,
      'message' => $this->t('Please join the Meet'),
    ];

    $url = $this->getAddUsersToMeetingUrl($owner_id);
    return $this->moxtraConnector->request($url, $data);
  }

  /**
   * Check if user is manager.
   */
  public function isManager($account) {
    return $this->moxtraConnector->isManager($account);
  }

  /**
   * Build Moxtra users prefix.
   */
  public function prefix($account) {
    return $this->moxtraConnector->prefix($account);
  }

  /**
   * Create / Update Moxtra user.
   * @param mixed $account
   *   User account.
   */
  public function setUser($account = NULL) {
    if (!empty($account)) {
      $prefix = $this->prefix($account);
      $is_moxtra_admin = $account->getEmail() == $this->moxtraConnector->getEmail();

      $user_data = [
        'unique_id' => $prefix . $account->id(),
        'first_name' => $account->getDisplayName(),
        'user_type' => $this->isManager($account) || $is_moxtra_admin ? 'Internal' : 'Client',
        'admin' => $is_moxtra_admin,
        'email' => $prefix . $account->getEmail(),
        'timezone' => $account->getTimeZone(),
      ];

      $uri = implode('/', [$this->moxtraConnector->getUrl(), 'v1', $this->moxtraConnector->getOrgId(), 'user']);
      $uri .= '?access_token=' . $token = $this->moxtraConnector->getToken(1);

      $this->moxtraConnector->request($uri, $user_data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createWorkspace($owner_id, $name) {
    $data = [
      'name' => $name,
      'restricted' => TRUE,
      'conversation' => TRUE,
    ];

    $url = $this->getCreateBinderUrl($owner_id);
    return $this->moxtraConnector->request($url, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function updateWorkspace($owner_id, $binder_id, $name) {
    $data = [
      'name' => $name,
    ];

    $url = $this->getUpdateBinderUrl($owner_id, $binder_id);
    return $this->moxtraConnector->request($url, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteWorkspace($owner_id, $binder_id) {
    $url = $this->getDeleteBinderUrl($owner_id, $binder_id);
    return $this->moxtraConnector->request($url, [], 'DELETE');
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessage($owner_id, $binder_id, $message) {
    $data = [
      'text' => $message,
    ];

    $url = $this->getSendMessageUrl($owner_id, $binder_id);
    return $this->moxtraConnector->request($url, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function addUsersToWorkspace($owner_id, $binder_id, $users_ids) {
    $users = array_map(function ($id) {
      return [
        'user' => [
          'unique_id' => $id,
        ],
      ];
    }, $users_ids);

    $data = [
      'users' => $users,
      'suppress_feed' => TRUE,
    ];

    $url = $this->getAddUsersUrl($owner_id, $binder_id);
    $response = $this->moxtraConnector->request($url, $data);

    if (!empty($response) && $response['http_code'] == 200) {
      $owner = User::load($owner_id);
      /** @var \Drupal\user\Entity\User[] $users */
      $users = User::loadMultiple($users_ids);
      foreach ($users as $user) {
        $message = $this->t('@owner invited @user to join this conversation.', [
          '@owner' => $owner->getDisplayName(),
          '@user' => $user->getDisplayName(),
        ]);
        $this->sendMessage($owner_id, $binder_id, $message);
      }
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function removeUserFromWorkspace($owner_id, $binder_id, $user_id) {
    $data = [
      'unique_id' => $user_id,
      'suppress_feed' => TRUE,
    ];

    $url = $this->getRemoveUserUrl($owner_id, $binder_id);
    $response = $this->moxtraConnector->request($url, $data);
    if (!empty($response) && $response['http_code'] == 200) {
      $owner = User::load($owner_id);
      /** @var \Drupal\user\Entity\User $user */
      $user = User::load($user_id);
      $message = $this->t('@owner removed @user from this conversation.', [
        '@owner' => $owner->getDisplayName(),
        '@user' => $user->getDisplayName(),
      ]);
      $this->sendMessage($owner_id, $binder_id, $message);
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getMeetingInfo($owner_id, $session_key) {
    $url = $this->getMeetingInfoUrl($owner_id, $session_key);
    return $this->moxtraConnector->request($url, [], 'GET');
  }

  /**
   * {@inheritdoc}
   */
  public function createMeeting($owner_id, $title, $starts, $ends) {
    $data = [
      'name' => $title,
      'starts' => $starts,
      'ends' => $ends,
      'auto_recording' => TRUE,
    ];

    $url = $this->getCreateMeetingUrl($owner_id);
    return $this->moxtraConnector->request($url, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function updateMeeting($owner_id, $session_key, $title, $starts, $ends = NULL) {
    $data = [
      'name' => $title,
      'starts' => $starts,
      'auto_recording' => TRUE,
    ];

    if (isset($ends)) {
      $data['ends'] = $ends;
    }

    $url = $this->getUpdateMeetingUrl($owner_id, $session_key);
    return $this->moxtraConnector->request($url, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMeeting($owner_id, $session_key) {
    $url = $this->getDeleteMeetingUrl($owner_id, $session_key);
    return $this->moxtraConnector->request($url, [], 'DELETE');
  }

  /**
   * {@inheritdoc}
   */
  public function getMeetingFilesList($owner_id, $binder_id) {
    $url = $this->getMeetingFilesListUrl($owner_id, $binder_id);
    return $this->moxtraConnector->request($url, [], 'GET');
  }

  /**
   * {@inheritdoc}
   */
  public function getMeetingFileInfo($owner_id, $binder_id, $file_id) {
    $url = $this->getMeetingFileInfoUrl($owner_id, $binder_id, $file_id);
    return $this->moxtraConnector->request($url, [], 'GET');
  }

  /**
   * {@inheritdoc}
   */
  public function getMeetingRecordingInfo($owner_id, $binder_id) {
    $url = $this->getMeetingRecordingInfoUrl($owner_id, $binder_id);
    return $this->moxtraConnector->request($url, [], 'GET');
  }

}
