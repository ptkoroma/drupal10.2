<?php

namespace Drupal\opigno_module\H5PImportClasses;

/**
 * Class H5PEditorAjaxMigrate.
 */
class H5PEditorAjaxImport extends \H5PEditorAjax {

  /**
   * Validates the package. Sets error messages if validation fails.
   *
   * @param bool $skipContent
   *   Will not validate cotent if set to TRUE.
   *
   * @return bool
   *   Valid package flag.
   */
  public function isValidPackage($skipContent = FALSE) {
    $validator = new H5PValidatorImport($this->core->h5pF, $this->core);
    if (!$validator->isValidPackage($skipContent, FALSE)) {
      $this->storage->removeTemporarilySavedFiles($this->core->h5pF->getUploadedH5pPath());
      \Drupal::logger('opigno_groups_migration')->error('Validating h5p package failed.');

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if Content Type Cache is up to date. Immediately tries to fetch
   * a new Content Type Cache if it is outdated.
   * Sets error message if fetching new Content Type Cache fails.
   *
   * @return bool
   */
  private function isContentTypeCacheUpdated() {

    // Update content type cache if enabled and too old
    $ct_cache_last_update = $this->core->h5pF->getOption('content_type_cache_updated_at', 0);
    $outdated_cache       = $ct_cache_last_update + (60 * 60 * 24 * 7); // 1 week
    if (time() > $outdated_cache) {
      $success = $this->core->updateContentTypeCache();
      if (!$success) {
        return false;
      }
    }
    return true;
  }

  /**
   * Gets content type cache for globally available libraries and the order
   * in which they have been used by the author
   *
   * @param bool $cacheOutdated The cache is outdated and not able to update
   */
  private function getContentTypeCache($cacheOutdated = FALSE) {
    $canUpdateOrInstall = ($this->core->h5pF->hasPermission(\H5PPermission::INSTALL_RECOMMENDED) ||
      $this->core->h5pF->hasPermission(\H5PPermission::UPDATE_LIBRARIES));
    return array(
      'outdated' => $cacheOutdated && $canUpdateOrInstall,
      'libraries' => $this->editor->getLatestGlobalLibrariesData(),
      'recentlyUsed' => $this->editor->ajaxInterface->getAuthorsRecentlyUsedLibraries(),
      'apiVersion' => array(
        'major' => \H5PCore::$coreApi['majorVersion'],
        'minor' => \H5PCore::$coreApi['minorVersion']
      ),
      'details' => $this->core->h5pF->getMessages('info')
    );
  }

  /**
   * Checks if H5P Hub is enabled. Sets error message on fail.
   *
   * @return bool
   */
  private function isHubOn() {
    if (!$this->core->h5pF->getOption('hub_is_enabled', TRUE)) {
      \H5PCore::ajaxError(
        $this->core->h5pF->t('The hub is disabled. You can enable it in the H5P settings.'),
        'HUB_DISABLED',
        403
      );
      return false;
    }
    return true;
  }

  /**
   * Gets the list of libraries.
   *
   * @return array
   *   Array of libraries.
   */
  public function h5pLibariesList() {
    if (!$this->isHubOn()) return;
    return $this->getContentTypeCache(!$this->isContentTypeCacheUpdated());
  }

}
