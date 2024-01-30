<?php

namespace Drupal\user_csv_import\Controller;

use Drupal\user\Entity\User;
use Drupal\file\Entity\File;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Provides methods to import CSV files and convert to users.
 */
class UserCsvImportController {

  /**
   * Show import page.
   */
  public static function importPage() {

    $form = \Drupal::formBuilder()->getForm('Drupal\user_csv_import\Form\UserCsvImportForm');

    return $form;

  }

  /**
   * Processes an uploaded CSV file, creating a new user for each row of values.
   *
   * @param \Drupal\file\Entity\File $file
   *   The File entity to process.
   *
   * @param array $config
   *   An array of configuration containing fields and roles.
   *
   * @return array
   *   An associative array of values from the filename keyed by new uid.
   */
  public static function processUpload(File $file, array $config) {

    // Open the uploaded file.
    $handle = fopen($file->getFileUri(), 'r');

    $created = [];
    $i = 0;
    $row_positions = [];
    $sepchar = $config['separator'];

    // Iterate hover it and store the values to a new User.
    while ($row = fgetcsv($handle, 0, $sepchar)) {
      $rows = count($row);
      if (1 >= $rows) {
        \Drupal::messenger()->addError(t('No separation character found. Please check your CSV-file.'));
        break;
      }

      // If is the first row, compare the header values with
      // selected fields if config.
      if ($i == 0) {

        // Iterate over the selected fields to find their position.
        foreach ($config['fields'] as $key => $value) {

          // Search in the file for the position of the selected fields.
          $row_positions[$key] = array_search($key, $row);

        }

      }
      else {

        if ($values = self::prepareRow($row, $config, $row_positions)) {

          // Create the user. Receive a user object.
          if ($user = self::createUser($values)) {

            // Get the email type selected in Settings.
            $registration_email_type = $config['registration_email_type'];

            // Send email of selected type to user, if so desired.
            if ($registration_email_type != 'none') {
              _user_mail_notify($registration_email_type, $user);
            }

            $uid = $user->id();
            $created[$uid] = $values;
          }
        }

      }

      $i++;

    }

    return $created;

  }

  /**
   * Prepares a new user from an upload row and current config.
   *
   * @param array $row
   *   A row from the currently uploaded file.
   *
   * @param array $config
   *   An array of configuration containing:
   *   - roles: an array of role ids to assign to the user
   *   - password: a password for the imported users extracted from settings
   *   - status: the status to be assigned to the new users extracted from settings
   *
   * @param array $fields_position
   *   An array with the position of the selected fields.
   *
   * @return array
   *   New user values suitable for User::create().
   */
  public static function prepareRow(array $row, array $config, array $fields_position) {

    // Prepare username.
    $preferred_username = (strtolower($row[0]));

    // Check if the username exists.
    $i = 0;

    while (self::usernameExists($i ? $preferred_username . $i : $preferred_username)) {

      $i++;

    }

    // If exists, assign a number to the name.
    $username = $i ? $preferred_username . $i : $preferred_username;

    $user_data = [
      'uid' => NULL,
      'name' => $username,
      'pass' => $config['password'],
      'timezone' => $config['timezone'] ?? \Drupal::config('system.date')->get('timezone.default'),
      'status' => $config['status'],
      'created' => \Drupal::time()->getRequestTime(),
      'roles' => array_values($config['roles']),
    ];

    // Add selected fields with their values to user data for store un database.
    foreach ($fields_position as $index => $position) {
      // if it's the password field
      if ($index == 'pass') {
        // make sure the CSV pass field is populated
        if (strlen($row[$position]) > 0) {
          $user_data[$index] = $row[$position];
	} else {
          // if it wasn't populated in CSV, fallback to the form's Default Password
          $user_data[$index] = $config['password'];
	}
      }
      // else, it's not the password field, process as normal
      else {
        $user_data[$index] = $row[$position];
      }
    }

    return $user_data;

  }

  /**
   * Returns user whose name matches $username.
   *
   * @param string $username
   *   Username to check.
   *
   * @return array
   *   Users whose names match username.
   */
  private static function usernameExists($username) {

    return \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('name', $username)
      ->execute();

  }

  /**
   * Creates a new user from prepared values.
   *
   * @param array $values
   *   Values prepared from prepareRow().
   *
   * @return \Drupal\user\Entity\User
   */
  private static function createUser($values) {
    if (user_load_by_mail($values['mail']) === false ) {
      $user = User::create($values);
      try {
        // If new user stores well, return the user object.
        if ($user->save()) {
          return $user;
        }
      }
      // Show error on user creation.
      catch (EntityStorageException $e) {
        \Drupal::messenger()->addMessage(t('Could not create user (username: %uname) (email: %email); exception: %e',
          [
            '%e' => $e->getMessage(),
            '%uname' => $values['name'],
            '%email' => $values['mail'],
          ]), 'error');
      }
    } else {
      $message = t('Could not create user (username: %uname) (email: %email). Email already in use',
        [
          '%uname' => $values['name'],
          '%email' => $values['mail'],
        ]);
      // put in logs
      \Drupal::logger('usercsvimport')->notice($message);
      // output to user submitting
      \Drupal::messenger()->addError($message);
    }

    return false;

  }

}
