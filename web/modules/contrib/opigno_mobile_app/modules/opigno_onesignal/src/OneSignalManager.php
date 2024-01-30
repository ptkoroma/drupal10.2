<?php

namespace Drupal\opigno_onesignal;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\opigno_onesignal\Config\ConfigManager;
use Drupal\user\Entity\User;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OneSignalManager.
 */
class OneSignalManager implements OneSignalManagerInterface {


  const ONE_SIGNAL_API = 'https://onesignal.com/api/v1/notifications';

  /**
   * Http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;


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
   * The onesignal manager.
   *
   * @var \Drupal\opigno_onesignal\Config\ConfigManager
   */
  private $configManager;

  /**
   * Constructs a new OneSignalManager object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\opigno_onesignal\Config\ConfigManager $onesignal_config_manager
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger,
    ConfigManager $onesignal_config_manager
  ) {
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('opigno_onesignal');
    $this->messenger = $messenger;
    $this->configManager = $onesignal_config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('opigno_onesignal.config_manager')
    );
  }

  public function sendNotificationForUsers($params) {
    global $base_url;
    // Get recipients.
    $users_ids = (isset($params['users']) && is_array($params['users'])) ? $params['users'] : [];
    // Get users prefer langcode.
    $langcode = (isset($params['langcode']) && $params['langcode'] != 'und')
      ? $params['langcode'] : \Drupal::languageManager()->getCurrentLanguage()->getId();
    // Filter users by langcode.
    $recipient_ids = [];
    if ($recipients = User::loadMultiple($users_ids)) {
      /* @var \Drupal\user\Entity\User $recipient */
      foreach ($recipients as $uid => $recipient) {
        $user_language = $recipient->getPreferredLangcode();
        if ($langcode == $user_language) {
          $recipient_ids[] = $uid;
        }
      }
    }

    // Get logo.
    $logo_path = $base_url . '/' . \Drupal::service('extension.list.theme')->getPath('aristotle') . "/logo.png";
    // Build title for notification.
    $title = [
      $langcode => isset($params['title']) ? $params['title'] : '',
    ];
    // Build content for notification.
    $content = [
      $langcode => isset($params['content']) ? $params['content'] : '',
    ];
    // Build data for notification.
    $data = isset($params['data']) && is_array($params['data']) ? $params['data'] : [];
    //    // Get One signal recipient ids.
    //    $player_ids = [];
    //    if ($recipient_ids) {
    //      foreach ($recipient_ids as $uid) {
    //        $db_connection = \Drupal::database();
    //        $query = $db_connection->select('opigno_onesignal_users', 'osu');
    //        $query->condition('osu.uid', intval($uid));
    //        $query->fields('osu', ['player_id']);
    //        $result = $query->execute()->fetchField();
    //
    //        if (!empty($result)) {
    //          $player_ids[] = $result;
    //        }
    //      }
    //    }

    // Get One signal recipient ids.
    $users_uuids = [];
    if ($recipient_ids) {
      foreach ($recipient_ids as $uid) {
        $user = User::load($uid);
        if (!is_null($user)) {
          $users_uuids[] = $user->uuid();
        }
      }
    }
    $this->logger->notice(json_encode($users_uuids));
    //    $player_ids = ['6d703d72-4237-4000-af72-0569d70548cc'];
    //    $users_uuids = ['7e83d1c0-527a-4294-ba09-cdb97144c14f'];
    $fields = [
      'app_id' => $this->configManager->getAppId(),
      //      'include_player_ids' => $player_ids,
      'include_external_user_ids' => $users_uuids,
      'headings' => $title,
      'contents' => $content,
      'data' => $data,
      'chrome_web_icon' => $logo_path,
      'small_icon' => 'ic_stat_onesignal_default',
    ];

    // Build request headers.
    $headers = [
      'Content-Type' => 'application/json; charset=utf-8',
      'Authorization' => 'Basic ' . $this->configManager->getRestApiKey(),
    ];

    try {
      $response = $this->httpClient->request('POST', self::ONE_SIGNAL_API, [
        'json' => $fields,
        'headers' => $headers,
      ]);
    }
    catch (ClientException $exception) {
      $this->logger->error($exception);
      $response = $exception->getResponse();
    }
    catch (Exception $exception) {
      $this->logger->error($exception);
    }

    $data = [];
    if (isset($response)) {
      $data['http_code'] = $response->getStatusCode();
      $response_body = $response->getBody()->getContents();
      if (!empty($response_body) && $response_body !== 'null') {
        $json_data = Json::decode($response_body);
        if (is_array($json_data) && !empty($json_data)) {
          $data = array_merge($data, $json_data);
        }
      }
    }
    $this->logger->notice(json_encode($data));
    return isset($data) ? $data : [];
  }

  public function checkApiConfigs() {
    // TODO: Implement checkConnection() method.
    $api_id = $this->configManager->getAppId();
    $rest_api_key = $this->configManager->getRestApiKey();

    return !empty($api_id) && !empty($rest_api_key);
  }
}
