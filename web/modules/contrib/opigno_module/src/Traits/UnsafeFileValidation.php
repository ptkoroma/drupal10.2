<?php

namespace Drupal\opigno_module\Traits;

/**
 * Provides validation security functions.
 */
trait UnsafeFileValidation {

  /**
   * Checks if there are any files start with ".".
   *
   * @param \ZipArchive $zip
   *   The .zip archive to be validated.
   *
   * @return bool
   *   Returns TRUE if pass validation, otherwise FALSE.
   */
  public static function validate(\ZipArchive $zip): bool {
    // Get all files list.
    for ($i = 0; $i < $zip->numFiles; $i++) {
      if (preg_match('/(^[\._]|\/[\._])/', $zip->getNameIndex($i)) !== 0) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
