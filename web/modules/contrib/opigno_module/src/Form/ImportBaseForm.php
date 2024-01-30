<?php

namespace Drupal\opigno_module\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\opigno_module\Traits\FileSecurity;
use Drupal\opigno_module\Traits\UnsafeFileValidation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Defines the best class for Opigno LP entities import.
 *
 * @package Drupal\opigno_module\Form
 */
abstract class ImportBaseForm extends FormBase {

  use UnsafeFileValidation;
  use FileSecurity;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The H5P config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Temporary folder uri.
   *
   * @var string
   */
  protected $tmp = 'public://opigno-import';

  /**
   * Path to the temporary folder.
   *
   * @var string
   */
  protected $folder = DRUPAL_ROOT . '/sites/default/files/opigno-import';

  /**
   * ImportActivityForm constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    FileSystemInterface $file_system,
    TimeInterface $time,
    Connection $database,
    SerializerInterface $serializer,
    ConfigFactoryInterface $config_factory,
    MessengerInterface $messenger
  ) {
    $this->fileSystem = $file_system;
    $this->time = $time;
    $this->database = $database;
    $this->serializer = $serializer;
    $this->config = $config_factory->get('h5p.settings');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('datetime.time'),
      $container->get('database'),
      $container->get('serializer'),
      $container->get('config.factory'),
      $container->get('messenger')
    );
  }

}
