<?php

namespace Drupal\opigno_module\Traits;

/**
 * Provides file security functions.
 */
trait FileSecurity {

  /**
   * Writes an .htaccess file in the given directory, if it doesn't exist.
   *
   * @param string $directory
   *   The directory.
   * @param bool $force
   *   (optional) Set to TRUE to force overwrite an existing file.
   *
   * @return bool
   *   TRUE if the file already exists or was created. FALSE otherwise.
   */
  public static function writeHtaccess(string $directory, bool $force = FALSE): bool {
    return self::writeFile($directory, '.htaccess', self::htaccessPreventExecution(), $force);
  }

  /**
   * Returns htaccess directives to deny execution in a given directory.
   *
   * @return string
   *   Apache htaccess directives to prevent execution of files in a location.
   */
  protected static function htaccessPreventExecution(): string {
    return <<<EOF
  # If we know how to do it safely, disable the PHP engine entirely.
  <IfModule mod_php7.c>
    php_flag engine off
  </IfModule>

  # Case insensitive check to prevent script execution.
  <FilesMatch "(?i)\.(phps|pht|phtm|phtml|pgif|shtml|htaccess|phar|incphps|php7|php5|php4|php|php3|php2|pl|py|jsp|asp|htm|sh|cgi)$">
    Order Allow,Deny
    Deny from all
  </FilesMatch>
  EOF;
  }

  /**
   * Writes the contents to the file in the given directory.
   *
   * @param string $directory
   *   The directory to write to.
   * @param string $filename
   *   The file name.
   * @param string $contents
   *   The file contents.
   * @param bool $force
   *   TRUE if we should force the write over an existing file.
   *
   * @return bool
   *   TRUE if writing the file was successful.
   */
  protected static function writeFile(string $directory, string $filename, string $contents, bool $force) {
    $file_path = $directory . DIRECTORY_SEPARATOR . $filename;
    // Don't overwrite if the file exists unless forced.
    if (file_exists($file_path) && !$force) {
      return TRUE;
    }
    // Writing the file can fail if:
    // - concurrent requests are both trying to write at the same time.
    // - $directory does not exist or is not writable.
    // Testing for these conditions introduces windows for concurrency issues to
    // occur.
    if (@file_put_contents($file_path, $contents)) {
      return @chmod($file_path, 0444);
    }
    return FALSE;
  }

}
