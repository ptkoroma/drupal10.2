<?php

namespace Drupal\opigno_module\H5PImportClasses;

/**
 * This class is used for validating H5P files.
 */
class H5PValidatorImport extends \H5PValidator {

  /**
   * Validates a .h5p file.
   *
   * @param bool $skipContent
   *   Skip package content.
   * @param bool $upgradeOnly
   *   Only update library flag.
   *
   * @return bool
   *   TRUE if the .h5p file is valid.
   */
  public function isValidPackage($skipContent = FALSE, $upgradeOnly = FALSE) {
    // Check dependencies, make sure Zip is present.
    if (!class_exists('ZipArchive')) {
      $this->h5pF->setErrorMessage($this->h5pF->t('Your PHP version does not support ZipArchive.'), 'zip-archive-unsupported');
      return FALSE;
    }

    // Create a temporary dir to extract package in.
    $tmpDir = $this->h5pF->getUploadedH5pFolderPath();
    $tmpPath = $this->h5pF->getUploadedH5pPath();

    // Extract and then remove the package file.
    $zip = new \ZipArchive();

    // Only allow files with the .h5p extension:
    if (strtolower(substr($tmpPath, -3)) !== 'h5p') {
      $this->h5pF->setErrorMessage($this->h5pF->t('The file you uploaded is not a valid HTML5 Package (It does not have the .h5p file extension)'), 'missing-h5p-extension');
      H5PStorageImport::deleteFileTree($tmpDir);
      return FALSE;
    }

    list($contentWhitelist, $contentRegExp) = $this->getWhitelistRegExp(FALSE);

    if ($zip->open($tmpPath) === TRUE) {
      // Check for valid file types, JSON files + file sizes before continuing
      // to unpack.
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $fileStat = $zip->statIndex($i);

        $fileName = mb_strtolower($fileStat['name']);
        if (preg_match('/(^[\._]|\/[\._])/', $fileName) !== 0) {
          // Skip any file or folder starting with a . or _.
          continue;
        }

        // This is a content file, check that the file type is allowed.
        if ((strpos($fileName, 'content/') === 0) && $skipContent === FALSE && $this->h5pC->disableFileCheck !== TRUE && !preg_match($contentRegExp, $fileName)) {
          $this->h5pF->setErrorMessage($this->h5pF->t('File "%filename" not allowed. Only files with the following extensions are allowed: %files-allowed.', [
            '%filename' => $fileStat['name'],
            '%files-allowed' => $contentWhitelist,
          ]), 'not-in-whitelist');
          H5PStorageImport::deleteFileTree($tmpDir);
          $zip->close();
          return FALSE;
        }
      }

      $zip->extractTo($tmpDir);
      $zip->close();
    }
    else {
      $this->h5pF->setErrorMessage($this->h5pF->t('The file you uploaded is not a valid HTML5 Package (We are unable to unzip it)'), 'unable-to-unzip');
      H5PStorageImport::deleteFileTree($tmpDir);
      return FALSE;
    }
    unlink($tmpPath);

    // Process content and libraries.
    $valid = TRUE;
    $libraries = [];
    $files = scandir($tmpDir);
    foreach ($files as $file) {
      if (in_array(substr($file, 0, 1), ['.', '_'])) {
        continue;
      }
      $filePath = $tmpDir . DIRECTORY_SEPARATOR . $file;

      if (strtolower($file) != 'h5p.json' && $file != 'content') {
        $libraryH5PData = $this->getLibraryData($file, $filePath, $tmpDir);
        if ($libraryH5PData !== FALSE) {
          $libraryH5PData['uploadDirectory'] = $filePath;
          $libraries[self::libraryToString($libraryH5PData)] = $libraryH5PData;
        }
        else {
          $valid = FALSE;
        }
      }
    }
    if ($valid) {
      $this->h5pC->librariesJsonData = $libraries;
    }
    if (!$valid) {
      H5PStorageImport::deleteFileTree($tmpDir);
    }
    return $valid;
  }

  /**
   * Writes library data as string {machineName} {majorVersion}.{minorVersion}.
   *
   * @param array $library
   *   With keys machineName, majorVersion and minorVersion.
   * @param bool $folderName
   *   Use hyphen instead of space in returned string.
   *
   * @return string
   *   On the form {machineName} {majorVersion}.{minorVersion}
   */
  public static function libraryToString(array $library, $folderName = FALSE) {
    return (isset($library['machineName']) ? $library['machineName'] : $library['name']) . ($folderName ? '-' : ' ') . $library['majorVersion'] . '.' . $library['minorVersion'];
  }

  /**
   * Help retrieve file type regexp whitelist from plugin.
   *
   * @param bool $isLibrary
   *   Separate list with more allowed file types.
   *
   * @return string RegExp.
   */
  private function getWhitelistRegExp($isLibrary) {
    $whitelist = $this->h5pF->getWhitelist($isLibrary, \H5PCore::$defaultContentWhitelist, \H5PCore::$defaultLibraryWhitelistExtras);
    return [
      $whitelist,
      '/\.(' . preg_replace('/ +/i', '|', preg_quote($whitelist)) . ')$/i',
    ];
  }

}
