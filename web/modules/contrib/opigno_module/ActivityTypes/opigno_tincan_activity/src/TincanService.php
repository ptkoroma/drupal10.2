<?php

namespace Drupal\opigno_tincan_activity;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\Entity\File;
use Drupal\opigno_module\Traits\FileSecurity;
use Drupal\opigno_module\Traits\UnsafeFileValidation;

/**
 * Defines the service to work with Tincan packages.
 *
 * @package Drupal\opigno_tincan_activity
 */
class TincanService implements TincanServiceInterface {

  use UnsafeFileValidation;
  use FileSecurity;

  const PATH_PUBLIC_PACKAGE_FOLDER = 'public://opigno_tincan/';

  const PATH_PUBLIC_EXTRACTED_PACKAGE_FOLDER = 'public://opigno_tincan_extracted/';

  const SCORE_MAX = 50;

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new TincanService object.
   */
  public function __construct(Connection $connection, LoggerChannelFactoryInterface $logger) {
    $this->connection = $connection;
    $this->logger = $logger->get('opigno_tincan_activity');
  }

  /**
   * {@inheritdoc}
   */
  public function saveTincanPackageInfo(File $file) {

    if (!$file) {
      return FALSE;
    }

    // Unzip file.
    if (!file_exists(self::getExtractPath($file) . 'tincan.xml')) {
      try {
        self::unzipPackage($file);
      }
      catch (\Exception $e) {
        return $e;
      }
    }

    $package_info = self::getInfoFromExtractedPackage($file);
    if ($package_info === FALSE) {
      $this->logger->error('The package does not contain an activity ID or a launch file');
      return;
    }
    // Record data from extracted zip to DB.
    $fid = $file->id();
    $activity_id = $package_info['id'];
    $launch_filename = $package_info['launch'];

    $connection = $this->connection;
    try {
      $connection->insert('opigno_tincan_activity_type_properties')
        ->fields([
          'fid' => $fid,
          'activity_id' => $activity_id,
          'launch_filename' => $launch_filename,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      return $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore() {
    return self::SCORE_MAX;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTincanPackage() {
    // @todo Implement deleteTincanPackage() method.
  }

  /**
   * {@inheritdoc}
   */
  public function tincanLoadByFileEntity(File $file) {
    $connection = $this->connection;
    return $connection->select('opigno_tincan_activity_type_properties', 'otn')
      ->fields('otn', ['activity_id', 'launch_filename'])
      ->condition('fid', $file->id())
      ->execute()
      ->fetchObject();
  }

  /**
   * This method will return the Activity ID and the launch file.
   *
   * These information must be in the tincan.xml file that is in the extracted
   *   package. You can find more information in the README.md file of this
   *   module.
   *
   * @param object $file
   *   The file that was unzipped.
   *
   * @return array|bool
   *   An array('id', 'launch') if all the information are found, FALSE if not.
   */
  public static function getInfoFromExtractedPackage($file) {
    $tincan_file = self::getExtractPath($file) . 'tincan.xml';

    if (!file_exists(self::getExtractPath($file) . 'tincan.xml')) {
      return FALSE;
    }

    $xml = simplexml_load_file($tincan_file);
    if (!$xml) {
      return FALSE;
    }

    // Check if the launch exists.
    if (!isset($xml->activities->activity->launch)) {
      return FALSE;
    }

    // Check if the activity ID exists.
    if (!isset($xml->activities->activity['id'])) {
      return FALSE;
    }

    return [
      'launch' => (string) $xml->activities->activity->launch,
      'id' => (string) $xml->activities->activity['id'],
    ];
  }

  /**
   * This method gives the path to the extracted package.
   *
   * @param object $file
   *   The extracted file.
   *
   * @return string
   *   The path to the extracted package.
   */
  public static function getExtractPath($file) {
    $filename = self::getPackageName($file);
    $filename = preg_replace(["/[^a-zA-Z0-9]/"], "_", $filename);
    return self::PATH_PUBLIC_EXTRACTED_PACKAGE_FOLDER . $filename . '/';
  }

  /**
   * Gives the package name, after being renamed by the system if it happened.
   *
   * @param object $file
   *   The package file.
   *
   * @return string
   *   The package name.
   */
  public static function getPackageName($file) {
    $file_full_name = $file->getFileName();
    $file_name_parts = explode('.', $file_full_name);
    return $file_name_parts[0];
  }

  /**
   * Public directories will be protected by adding an .htaccess.
   *
   * @param string $directory
   *   A string reference containing the name of a directory path or URI.
   *
   * @return bool
   *   TRUE if the directory exists (or was created), is writable and is
   *   protected (if it is public). FALSE otherwise.
   */
  protected static function prepareDirectory(string $directory): bool {
    if (0 === strpos($directory, 'public://')) {
      return static::writeHtaccess($directory);
    }
    return TRUE;
  }

  /**
   * This method will unzip the package to the public extracted folder.
   *
   * It will use the constants self::PATH_PUBLIC_EXTRACTED_PACKAGE_FOLDER.
   *
   * @param object $file
   *   The file to unzip.
   *
   * @return bool
   *   TRUE if success, else FALSE.
   *
   * @throws \Exception
   *   If the file is unsupported.
   */
  public static function unzipPackage($file) {
    $path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $zip = new \ZipArchive();
    $result = $zip->open($path);
    if ($result === TRUE) {
      if (!static::validate($zip)) {
        \Drupal::messenger()->addMessage(t('Unsafe files detected.'), 'error');
        $zip->close();
        \Drupal::service('file_system')->delete($path);

        return FALSE;
      }
      static::prepareDirectory(self::PATH_PUBLIC_EXTRACTED_PACKAGE_FOLDER);
      $extract_dir = self::getExtractPath($file);
      $zip->extractTo($extract_dir);
      $zip->close();
    }
    else {
      $error = 'none';
      switch ($result) {
        case \ZipArchive::ER_EXISTS:
          $error = 'ER_EXISTS';
          break;

        case \ZipArchive::ER_INCONS:
          $error = 'ER_INCONS';
          break;

        case \ZipArchive::ER_INVAL:
          $error = 'ER_INVAL';
          break;

        case \ZipArchive::ER_NOENT:
          $error = 'ER_NOENT';
          break;

        case \ZipArchive::ER_NOZIP:
          $error = 'ER_NOZIP';
          break;

        case \ZipArchive::ER_OPEN:
          $error = 'ER_OPEN';
          break;

        case \ZipArchive::ER_READ:
          $error = 'ER_READ';
          break;

        case \ZipArchive::ER_SEEK:
          $error = 'ER_SEEK';
          break;
      }
      \Drupal::logger('opigno_tincan_activity')->error(
        "An error occurred when unzipping the TINCAN package data. Error: %error", [
          '%error' => $error,
        ]
      );

      return FALSE;
    }
  }

}
