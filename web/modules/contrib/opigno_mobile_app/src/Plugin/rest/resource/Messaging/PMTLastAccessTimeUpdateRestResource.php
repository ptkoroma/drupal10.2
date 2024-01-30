<?php

namespace Drupal\opigno_mobile_app\Plugin\rest\resource\Messaging;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageThread;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to update PrivateMessageThreadAccessTime entity.
 *
 * @RestResource(
 *   id = "private_message_thread_update_resource",
 *   label = @Translation("Opigno: Update Private Message Thead"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/private_message_thread/{private_message_thread}/update",
 *   }
 * )
 */
class PMTLastAccessTimeUpdateRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new DefaultRestResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('opigno_mobile_app'),
      $container->get('current_user')
    );
  }

  /**
   * Update private_message_thread entity.
   *
   * @param $private_message_thread
   * @param $data
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function patch($private_message_thread, $data) {
    if (empty($data['body'])) {
      throw new BadRequestHttpException('Missing data.');
    }
    $private_message_thread = PrivateMessageThread::load($private_message_thread);
    if (empty($private_message_thread)) {
      throw new BadRequestHttpException('Can not load Private message thread entity.');
    }

    // Update last access time for current thread.
    if (isset($data['body']['last_access_time'])) {
      $private_message_thread->updateLastAccessTime($this->currentUser);
    }

    $private_message_thread->save();
    // Return updated entity.
    $response = new ResourceResponse($private_message_thread, 200);
    // Disable caching.
    $response->addCacheableDependency(['#cache' => ['max-age' => 0]]);
    return $response;
  }

}
