<?php

namespace Drupal\opigno_tincan_activity\Plugin\Field\FieldType;

use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;

/**
 * Represents a configurable entity file field.
 */
class OpignoTincanPackageItemList extends FileFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    parent::postSave($update);

    $tincan_content_service = \Drupal::service('opigno_tincan_activity.tincan');
    // Extract tincan per each archive.
    foreach ($this->referencedEntities() as $file) {
      if (!$update) {
        $tincan_content_service->saveTincanPackageInfo($file);
      }
      else {
        $package_info = $tincan_content_service->getInfoFromExtractedPackage($file);
        if ($package_info === FALSE) {
          $tincan_content_service->saveTincanPackageInfo($file);
        }
      }
    }
  }

}
