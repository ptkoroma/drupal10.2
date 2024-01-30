<?php

namespace Drupal\opigno_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\h5p\Entity\H5PContent;
use Drupal\media\Entity\Media;
use Drupal\opigno_module\Entity\OpignoModule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\Core\TypedData\Plugin\DataType\Uri;

/**
 * Class LearningPathController.
 */
class LearningPathController extends ControllerBase {

  /**
   * Add index.
   */
  public function addIndex() {
    $opigno_module = OpignoModule::create();
    $form = \Drupal::service('entity.form_builder')->getForm($opigno_module);
    return $form;
  }

  /**
   * Edit index.
   */
  public function editIndex($opigno_module) {
    return \Drupal::service('entity.form_builder')->getForm($opigno_module);
  }

  /**
   * Duplicate index.
   */
  public function duplicateModule($opigno_module) {
    $duplicate = $opigno_module->createDuplicate();
    $current_name = $duplicate->label();
    $duplicate->setName($this->t('Duplicate of ') . $current_name);
    $activities = $opigno_module->getModuleActivities();
    $current_time = \Drupal::time()->getCurrentTime();
    $add_activities = [];

    foreach ($activities as $activity) {
      $add_activities[] = OpignoActivity::load($activity->id);
    }

    $duplicate->setOwnerId(\Drupal::currentUser()->id());
    $duplicate->set('created', $current_time);
    $duplicate->set('changed', $current_time);
    $duplicate->save();
    $duplicate_id = $duplicate->id();
    $opigno_module_obj = \Drupal::service('opigno_module.opigno_module');
    $opigno_module_obj->activitiesToModule($add_activities, $duplicate);

    return $this->redirect('opigno_module.edit', [
      'opigno_module' => $duplicate_id,
    ]);
  }

  /**
   * Export activity index.
   */
  public function exportActivity($opigno_activity) {
    $bundle = $opigno_activity->bundle();
    $fields = $opigno_activity->getFields();

    $ignore_fields = ['id', 'uuid', 'vid', 'revision_created', 'revision_user', 'evision_log_message', 'uid',
      'revision_default', 'revision_translation_affected'];
    $data_structure[$opigno_activity->id()]['entity_type'] = (string) $opigno_activity->getEntityTypeId();
    $data_structure[$opigno_activity->id()]['bundle'] = (string) $opigno_activity->bundle();

    foreach ($fields as $field_key => $field) {
      if (!isset($opigno_activity->{$field_key}) || in_array($field_key, $ignore_fields)) {
        continue;
      }

      $data_structure[$opigno_activity->id()][$field_key] = $field->getValue();
    }

    $activity_name = $data_structure[$opigno_activity->id()]['name'][0]['value'];
    $format = 'json';
    $dir = 'sites/default/files/opigno-export';
    \Drupal::service('file_system')->deleteRecursive($dir);
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);

    $filename = "export-{$opigno_activity->getEntityTypeId()}.{$format}";
    $filename_path = "{$dir}/{$filename}";

    $new_filename = "opigno-activity_{$activity_name}.opi";
    $zip = new \ZipArchive();
    $zip->open($dir . '/' . $new_filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

    switch ($opigno_activity->bundle()) {
      case 'opigno_scorm':
        if (isset($opigno_activity->get('opigno_scorm_package')->target_id)) {
          $file = File::load($opigno_activity->get('opigno_scorm_package')->target_id);

          if ($file) {
            $file_uri = $file->getFileUri();
            $file_path = \Drupal::service('file_system')->realpath($file_uri);
            $scorm_filename = $file->id() . '-' . $file->getFilename();

            $data_structure[$opigno_activity->id()]['files'][$scorm_filename] = [
              'file_name' => $file->getFilename(),
              'filemime' => $file->getMimeType(),
              'status' => $file->get('status')->getValue()[0]['value'],
            ];

            $zip->addFile($file_path, $scorm_filename);
          }
        }
        break;

      case 'opigno_tincan':
        if (isset($opigno_activity->get('opigno_tincan_package')->target_id)) {
          $file = File::load($opigno_activity->get('opigno_tincan_package')->target_id);

          if ($file) {
            $file_uri = $file->getFileUri();
            $file_path = \Drupal::service('file_system')->realpath($file_uri);
            $tincan_filename = $file->id() . '-' . $file->getFilename();

            $data_structure[$opigno_activity->id()]['files'][$tincan_filename] = [
              'file_name' => $file->getFilename(),
              'filemime' => $file->getMimeType(),
              'status' => $file->get('status')->getValue()[0]['value'],
            ];

            $zip->addFile($file_path, $tincan_filename);
          }
        }
        break;

      case 'opigno_slide':
        if (isset($opigno_activity->get('opigno_slide_pdf')->target_id)) {
          $media = Media::load($opigno_activity->get('opigno_slide_pdf')->target_id);
          $file_id = $media->get('field_media_file')->getValue()[0]['target_id'];
          $file = File::load($file_id);

          if ($file) {
            $file_uri = $file->getFileUri();
            $file_path = \Drupal::service('file_system')->realpath($file_uri);
            $pdf_filename = $file->id() . '-' . $file->getFilename();

            $data_structure[$opigno_activity->id()]['files'][$pdf_filename] = [
              'file_name' => $file->getFilename(),
              'filemime' => $file->getMimeType(),
              'status' => $file->get('status')->getValue()[0]['value'],
              'bundle' => $media->bundle(),
            ];

            $zip->addFile($file_path, $pdf_filename);
          }
        }
        break;

      case 'opigno_video':
        if (isset($opigno_activity->get('field_video')->target_id)) {
          $file = File::load($opigno_activity->get('field_video')->target_id);

          if ($file) {
            $file_uri = $file->getFileUri();
            $file_path = \Drupal::service('file_system')->realpath($file_uri);
            $video_filename = $file->id() . '-' . $file->getFilename();

            $data_structure[$opigno_activity->id()]['files'][$video_filename] = [
              'file_name' => $file->getFilename(),
              'filemime' => $file->getMimeType(),
              'status' => $file->get('status')->getValue()[0]['value'],
            ];

            $zip->addFile($file_path, $video_filename);
          }
        }
        break;
    }

    $serializer = \Drupal::service('serializer');
    $content = $serializer->serialize($data_structure, $format);

    $context['results']['file'] = \Drupal::service('file_system')->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

    if ($bundle == 'opigno_h5p') {
      $hp5_id = $data_structure[$opigno_activity->id()]['opigno_h5p'][0]['h5p_content_id'] ?? FALSE;
      $h5p_content = $hp5_id ? H5PContent::load($hp5_id) : FALSE;
      if ($h5p_content instanceof H5PContent) {
        // @todo Unused returned filters, but keeps this call
        //   for backward compatibilities.
        $h5p_content->getFilteredParameters();
        $hp5_archive = "interactive-content-{$hp5_id}.h5p";
        $zip->addFile('sites/default/files/h5p/exports/'. $hp5_archive, $hp5_archive);
      }
      else {
        $this->addLogAndMessage($opigno_activity);
      }
    }

    $zip->addFile($filename_path, $filename);
    $zip->close();

    $headers = [
      'Content-Type' => 'application/opi',
      'Content-Disposition' => 'attachment; filename="' . $new_filename . '"',
    ];

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
      $headers['Cache-Control'] = 'must-revalidate, post-check=0, pre-check=0';
      $headers['Pragma'] = 'public';
    }
    else {
      $headers['Pragma'] = 'no-cache';
    }

    return new BinaryFileResponse($dir . '/' . $new_filename, 200, $headers);
  }

  /**
   * Export module index.
   */
  public function exportModule($opigno_module) {
    $activities = $opigno_module->getModuleActivities();
    $module_fields = $opigno_module->getFields();
    $files_to_export = [];

    foreach ($module_fields as $field_key => $field) {
      $data_structure[$opigno_module->id()][$field_key] = $field->getValue();
    }

    $module_name = $data_structure[$opigno_module->id()]['name'][0]['value'];
    $format = 'json';
    $dir = 'sites/default/files/opigno-export';
    \Drupal::service('file_system')->deleteRecursive($dir);
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);

    $serializer = \Drupal::service('serializer');
    $content = $serializer->serialize($data_structure, $format);

    $filename = "export-module_{$module_name}.{$format}";
    $filename_path = "{$dir}/{$filename}";
    $files_to_export['module'] = $filename;

    $context['results']['file'] = \Drupal::service('file_system')->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

    $new_filename = "opigno-module_{$module_name}.opi";
    $zip = new \ZipArchive();
    $zip->open($dir . '/' . $new_filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $zip->addFile($filename_path, $filename);

    foreach ($activities as $activity) {
      $opigno_activity = OpignoActivity::load($activity->id);
      $fields = $opigno_activity->getFields();
      $data_structure = [];

      foreach ($fields as $field_key => $field) {
        $data_structure[$opigno_activity->id()][$field_key] = $field->getValue();
      }

      $activity_name = $data_structure[$opigno_activity->id()]['name'][0]['value'];
      $filename = "export-activity_{$activity_name}_{$opigno_activity->id()}.{$format}";
      $filename_path = "{$dir}/{$filename}";
      $files_to_export['activities'][] = $filename;

      switch ($opigno_activity->bundle()) {
        case 'opigno_scorm':
          if (isset($opigno_activity->get('opigno_scorm_package')->target_id)) {
            $file = File::load($opigno_activity->get('opigno_scorm_package')->target_id);

            if ($file) {
              $file_uri = $file->getFileUri();
              $file_path = \Drupal::service('file_system')->realpath($file_uri);
              $scorm_filename = $file->id() . '-' . $file->getFilename();

              $data_structure[$opigno_activity->id()]['files'][$scorm_filename] = [
                'file_name' => $file->getFilename(),
                'filemime' => $file->getMimeType(),
                'status' => $file->get('status')->getValue()[0]['value'],
              ];

              $zip->addFile($file_path, $scorm_filename);
            }
          }
          break;

        case 'opigno_tincan':
          if (isset($opigno_activity->get('opigno_tincan_package')->target_id)) {
            $file = File::load($opigno_activity->get('opigno_tincan_package')->target_id);

            if ($file) {
              $file_uri = $file->getFileUri();
              $file_path = \Drupal::service('file_system')->realpath($file_uri);
              $tincan_filename = $file->id() . '-' . $file->getFilename();

              $data_structure[$opigno_activity->id()]['files'][$tincan_filename] = [
                'file_name' => $file->getFilename(),
                'filemime' => $file->getMimeType(),
                'status' => $file->get('status')->getValue()[0]['value'],
              ];

              $zip->addFile($file_path, $tincan_filename);
            }
          }
          break;

        case 'opigno_slide':
          if (isset($opigno_activity->get('opigno_slide_pdf')->target_id)) {
            $media = Media::load($opigno_activity->get('opigno_slide_pdf')->target_id);
            $file_id = $media->get('field_media_file')->getValue()[0]['target_id'];
            $file = File::load($file_id);

            if ($file) {
              $file_uri = $file->getFileUri();
              $file_path = \Drupal::service('file_system')->realpath($file_uri);
              $pdf_filename = $file->id() . '-' . $file->getFilename();

              $data_structure[$opigno_activity->id()]['files'][$pdf_filename] = [
                'file_name' => $file->getFilename(),
                'filemime' => $file->getMimeType(),
                'status' => $file->get('status')->getValue()[0]['value'],
                'bundle' => $media->bundle(),
              ];

              $zip->addFile($file_path, $pdf_filename);
            }
          }
          break;

        case 'opigno_video':
          if (isset($opigno_activity->get('field_video')->target_id)) {
            $file = File::load($opigno_activity->get('field_video')->target_id);

            if ($file) {
              $file_uri = $file->getFileUri();
              $file_path = \Drupal::service('file_system')->realpath($file_uri);
              $video_filename = $file->id() . '-' . $file->getFilename();

              $data_structure[$opigno_activity->id()]['files'][$video_filename] = [
                'file_name' => $file->getFilename(),
                'filemime' => $file->getMimeType(),
                'status' => $file->get('status')->getValue()[0]['value'],
              ];

              $zip->addFile($file_path, $video_filename);
            }
          }
          break;
      }

      if ($opigno_activity->bundle() == 'opigno_h5p') {
        $hp5_id = $data_structure[$opigno_activity->id()]['opigno_h5p'][0]['h5p_content_id'] ?? FALSE;
        $h5p_content = $hp5_id ? H5PContent::load($hp5_id) : FALSE;
        if ($h5p_content instanceof H5PContent) {
          // @todo Unused returned filters, but keeps this call
          //   for backward compatibilities.
          $h5p_content->getFilteredParameters();
          $hp5_archive = "interactive-content-{$hp5_id}.h5p";
          $zip->addFile('sites/default/files/h5p/exports/'. $hp5_archive, $hp5_archive);
        }
        else {
          $this->addLogAndMessage($opigno_activity);
        }
      }

      $content = $serializer->serialize($data_structure, $format);
      \Drupal::service('file_system')
        ->prepareDirectory(pathinfo($filename_path)['dirname'], FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
      $context['results']['file'] = \Drupal::service('file_system')->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

      $zip->addFile($filename_path, $filename);
    }

    $content = $serializer->serialize($files_to_export, $format);
    $filename = "list_of_files.{$format}";
    $filename_path = "{$dir}/{$filename}";
    $files_to_export['activities'][] = $filename;

    $context['results']['file'] = \Drupal::service('file_system')->saveData($content, $filename_path, FileSystemInterface::EXISTS_REPLACE);

    $zip->addFile($filename_path, $filename);
    $zip->close();

    $headers = [
      'Content-Type' => 'application/opi',
      'Content-Disposition' => 'attachment; filename="' . $new_filename . '"',
    ];

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
      $headers['Cache-Control'] = 'must-revalidate, post-check=0, pre-check=0';
      $headers['Pragma'] = 'public';
    }
    else {
      $headers['Pragma'] = 'no-cache';
    }

    return new BinaryFileResponse($dir . '/' . $new_filename, 200, $headers);
  }

  /**
   * Modules index.
   */
  public function modulesIndex($opigno_module, Request $request) {
    return [
      '#theme' => 'opigno_learning_path_modules',
      '#attached' => ['library' => ['opigno_group_manager/manage_app']],
      '#base_path' => $request->getBasePath(),
      '#base_href' => $request->getPathInfo(),
      '#learning_path_id' => $opigno_module->id(),
      '#module_context' => 'true',
    ];
  }

  /**
   * Activities bank.
   */
  public function activitiesBank($opigno_module) {
    // Output activities bank view.
    $activities_bank['activities_bank'] = views_embed_view('opigno_activities_bank_lp_interface');

    $build[] = $activities_bank;

    return $build;
  }

  /**
   * Logging an export/import process.
   */
  private function addLogAndMessage(EntityInterface $opigno_activity) {
    \Drupal::logger('opigno_export_import')
      ->notice('H5p Content @id has not been loaded during a @type export.', [
        '@id' => $opigno_activity->id(),
        '@type' => $opigno_activity->bundle(),
      ]);
    \Drupal::messenger()
      ->addWarning(t('H5p Content @id has not been loaded during a @type export.', [
        '@id' => $opigno_activity->id(),
        '@type' => $opigno_activity->bundle(),
      ], ['context' => 'opigno_export_import']));
  }

}
