<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\h5p\Entity\H5PContent;
use Drupal\h5p\H5PDrupal\H5PDrupal;
use Drupal\h5peditor\H5PEditor\H5PEditorUtilities;
use Drupal\media\Entity\Media;
use Drupal\opigno_module\Controller\OpignoModuleController;
use Drupal\opigno_module\Entity\OpignoActivity;
use Drupal\opigno_module\Entity\OpignoModule;
use Drupal\opigno_module\H5PImportClasses\H5PEditorAjaxImport;
use Drupal\opigno_module\H5PImportClasses\H5PStorageImport;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Defines the form for the module import.
 *
 * @package Drupal\opigno_module\Form
 */
class ImportModuleForm extends ImportBaseForm {

  /**
   * The Opigno module service.
   *
   * @var \Drupal\opigno_module\Controller\OpignoModuleController
   */
  protected $moduleService;

  /**
   * {@inheritdoc}
   */
  public function __construct(OpignoModuleController $module_service, ...$default) {
    parent::__construct(...$default);
    $this->moduleService = $module_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_module.opigno_module'),
      $container->get('file_system'),
      $container->get('datetime.time'),
      $container->get('database'),
      $container->get('serializer'),
      $container->get('config.factory'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'import_module_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $mode = NULL) {
    $form['module_opi'] = [
      '#title' => $this->t('Module'),
      '#type' => 'file',
      '#description' => $this->t('Here you can import module. Allowed extension: opi'),
    ];

    $ajax_id = "ajax-form-entity-external-package";
    $form['#attributes']['class'][] = $ajax_id;
    $form['#attached']['library'][] = 'opigno_module/ajax_form';

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
    $files = $this->getRequest()->files->get('files', []);
    $uploaded = $files['module_opi'] ?? NULL;

    if (!$uploaded instanceof UploadedFile || !$uploaded->getClientOriginalName()) {
      // Only need to validate if the field actually has a file.
      $form_state->setError(
        $form['module_opi'],
        $this->t("The file was not uploaded.")
      );
    }
  }

  /**
   * Checks that the directory exists and is writable.
   *
   * Public directories will be protected by adding an .htaccess.
   *
   * @param string $directory
   *   A string reference containing the name of a directory path or URI.
   *
   * @return bool
   *   TRUE if the directory exists (or was created), is writable and is
   *   protected (if it is public). FALSE otherwise.
   */
  protected function prepareDirectory(string $directory): bool {
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY)) {
      return FALSE;
    }
    if (0 === strpos($directory, 'public://')) {
      return static::writeHtaccess($directory);
    }

    return TRUE;
  }

  /**
   * Prepare temporary folder.
   */
  protected function prepareTemporary() {
    // Prepare folder.
    $this->fileSystem->deleteRecursive($this->tmp);
    return $this->prepareDirectory($this->tmp);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Prepare folder.
    if (!$this->prepareTemporary()) {
      $this->messenger->addError($this->t('Failed to create directory.'));
      return;
    }

    // Prepare file validators.
    $extensions = ['opi'];
    $validators = [
      'file_validate_extensions' => $extensions,
    ];

    $files = [];
    $file = file_save_upload('module_opi', $validators, $this->tmp, NULL, FileSystemInterface::EXISTS_REPLACE);
    if (!empty($file[0])) {
      $path = $this->fileSystem->realpath($file[0]->getFileUri());

      $zip = new \ZipArchive();
      $result = $zip->open($path);

      if (!static::validate($zip)) {
        $this->messenger->addError($this->t('Unsafe files detected.'));
        $zip->close();
        $this->fileSystem->delete($path);
        $this->fileSystem->deleteRecursive($this->tmp);
        return;
      }

      if ($result === TRUE) {
        $zip->extractTo($this->folder);
        $zip->close();
      }

      $this->fileSystem->delete($path);
      $files = scandir($this->folder);
    }
    if (in_array('list_of_files.json', $files)) {
      $file_path = $this->tmp . '/list_of_files.json';
      $real_path = $this->fileSystem->realpath($file_path);
      $file = file_get_contents($real_path);

      $format = 'json';
      $content = $this->serializer->decode($file, $format);

      if (empty($content['module'])) {
        $this->messenger->addError($this->t('Incorrect archive structure.'));
        return;
      }

      $file_path = $this->tmp . '/' . $content['module'];
      $real_path = $this->fileSystem->realpath($file_path);
      $file = file_get_contents($real_path);
      $module_content_array = $this->serializer->decode($file, $format);
      $module_content = reset($module_content_array);

      $new_module = OpignoModule::create([
        'type' => 'opigno_module',
        'langcode' => $module_content['langcode'][0]['value'],
        'name' => $module_content['name'][0]['value'],
        'status' => $module_content['status'][0]['value'],
        'random_activity_score' => $module_content['random_activity_score'][0]['value'],
        'allow_resume' => $module_content['allow_resume'][0]['value'],
        'backwards_navigation' => $module_content['backwards_navigation'][0]['value'],
        'randomization' => $module_content['randomization'][0]['value'],
        'random_activities' => $module_content['random_activities'][0]['value'],
        'takes' => $module_content['takes'][0]['value'],
        'show_attempt_stats' => $module_content['show_attempt_stats'][0]['value'],
        'keep_results' => $module_content['keep_results'][0]['value'],
        'hide_results' => $module_content['hide_results'][0]['value'],
        'badge_active' => $module_content['badge_active'][0]['value'],
        'badge_criteria' => $module_content['badge_criteria'][0]['value'],
      ]);

      if ($module_content['badge_active'][0]['value'] == 1) {
        $new_module->badge_name->value = $module_content['badge_name'][0]['value'];
        $new_module->badge_description->value = $module_content['badge_description'][0]['value'];
      }

      if (!empty($module_content['description'][0])) {
        $new_module->description->value = $module_content['description'][0]['value'];
        $new_module->description->format = $module_content['description'][0]['format'];
      }

      $new_module->save();

      $add_activities = [];

      opigno_module_prepare_directory_structure_for_import();

      foreach ($content['activities'] as $activity_file_name) {
        $file_path = $this->tmp . '/' . $activity_file_name;
        $real_path = $this->fileSystem->realpath($file_path);
        $file = file_get_contents($real_path);

        try {
          $activity_content_array = $this->serializer->decode($file, $format);
          $activity_content = reset($activity_content_array);
        }
        catch (\Exception $e) {
          continue;
        }

        $new_activity = OpignoActivity::create([
          'type' => $activity_content['type'][0]['target_id'],
        ]);

        $new_activity->setName($activity_content['name'][0]['value']);
        $new_activity->set('langcode', $activity_content['langcode'][0]['value']);
        $new_activity->set('status', $activity_content['status'][0]['value']);

        switch ($activity_content['type'][0]['target_id']) {
          case 'opigno_long_answer':
            $new_activity->opigno_body->value = $activity_content['opigno_body'][0]['value'];
            $new_activity->opigno_body->format = $activity_content['opigno_body'][0]['format'];
            $new_activity->opigno_evaluation_method->value = $activity_content['opigno_evaluation_method'][0]['value'];
            break;

          case 'opigno_file_upload':
            $new_activity->opigno_body->value = $activity_content['opigno_body'][0]['value'];
            $new_activity->opigno_body->format = $activity_content['opigno_body'][0]['format'];
            $new_activity->opigno_evaluation_method->value = $activity_content['opigno_evaluation_method'][0]['value'];
            $new_activity->opigno_allowed_extension->value = $activity_content['opigno_allowed_extension'][0]['value'];
            break;

          case 'opigno_scorm':
            foreach ($activity_content['files'] as $file_key => $file_content) {
              $scorm_file_path = $this->tmp . '/' . $file_key;
              $uri = $this->fileSystem->copy($scorm_file_path, 'public://opigno_scorm/' . $file_content['file_name'], FileSystemInterface::EXISTS_RENAME);

              $file = File::create([
                'uri' => $uri,
                'uid' => $this->currentUser()->id(),
                'status' => $file_content['status'],
              ]);
              $file->save();
              $fid = $file->id();
              $new_activity->opigno_scorm_package->target_id = $fid;
              $new_activity->opigno_scorm_package->display = 1;
            }
            break;

          case 'opigno_tincan':
            foreach ($activity_content['files'] as $file_key => $file_content) {
              $tincan_file_path = $this->tmp . '/' . $file_key;
              $uri = $this->fileSystem->copy($tincan_file_path, 'public://opigno_tincan/' . $file_content['file_name'], FileSystemInterface::EXISTS_RENAME);

              $file = File::create([
                'uri' => $uri,
                'uid' => $this->currentUser()->id(),
                'status' => $file_content['status'],
              ]);
              $file->save();
              $fid = $file->id();
              $new_activity->opigno_tincan_package->target_id = $fid;
              $new_activity->opigno_tincan_package->display = 1;
            }
            break;

          case 'opigno_slide':
            foreach ($activity_content['files'] as $file_key => $file_content) {
              $slide_file_path = $this->tmp . '/' . $file_key;
              $current_timestamp = $this->time->getCurrentTime();
              $date = date('Y-m', $current_timestamp);
              $uri = $this->fileSystem->copy($slide_file_path, 'public://' . $date . '/' . $file_content['file_name'], FileSystemInterface::EXISTS_RENAME);

              $file = File::create([
                'uri' => $uri,
                'uid' => $this->currentUser()->id(),
                'status' => $file_content['status'],
              ]);
              $file->save();

              $media = Media::create([
                'bundle' => $file_content['bundle'],
                'name' => $file_content['file_name'],
                'field_media_file' => [
                  'target_id' => $file->id(),
                ],
              ]);

              $media->save();

              $new_activity->opigno_slide_pdf->target_id = $media->id();
              $new_activity->opigno_slide_pdf->display = 1;
            }
            break;

          case 'opigno_video':
            foreach ($activity_content['files'] as $file_key => $file_content) {
              $video_file_path = $this->tmp . '/' . $file_key;
              $current_timestamp = $this->time->getCurrentTime();
              $date = date('Y-m', $current_timestamp);
              $uri = $this->fileSystem->copy($video_file_path, 'public://video-thumbnails/' . $date . '/' . $file_content['file_name'], FileSystemInterface::EXISTS_RENAME);

              $file = File::create([
                'uri' => $uri,
                'uid' => $this->currentUser()->id(),
                'status' => $file_content['status'],
              ]);
              $file->save();

              $new_activity->field_video->target_id = $file->id();
            }
            break;

          case 'opigno_h5p':
            $h5p_content_id = $activity_content['opigno_h5p'][0]['h5p_content_id'];
            $file = $this->folder . "/interactive-content-{$h5p_content_id}.h5p";
            $interface = H5PDrupal::getInstance();

            if ($file) {
              $dir = $this->fileSystem->realpath($this->tmp . '/h5p');
              $interface->getUploadedH5pFolderPath($dir);
              $interface->getUploadedH5pPath($this->folder . "/interactive-content-{$h5p_content_id}.h5p");

              $editor = H5PEditorUtilities::getInstance();
              $h5pEditorAjax = new H5PEditorAjaxImport($editor->ajax->core, $editor, $editor->ajax->storage);

              if ($h5pEditorAjax->isValidPackage()) {
                // Add new libraries from file package.
                $storage = new H5PStorageImport($h5pEditorAjax->core->h5pF, $h5pEditorAjax->core);

                // Serialize metadata array in libraries.
                if (!empty($storage->h5pC->librariesJsonData)) {
                  foreach ($storage->h5pC->librariesJsonData as &$library) {
                    if (array_key_exists('metadataSettings', $library) && is_array($library['metadataSettings'])) {
                      $metadataSettings = serialize($library['metadataSettings']);
                      $library['metadataSettings'] = $metadataSettings;
                    }
                  }
                }

                $storage->saveLibraries();

                $h5p_json = $dir . '/h5p.json';
                $real_path = $this->fileSystem->realpath($h5p_json);
                $h5p_json = file_get_contents($real_path);

                $format = 'json';
                $h5p_json = $this->serializer->decode($h5p_json, $format);
                $dependencies = $h5p_json['preloadedDependencies'];

                // Get ID of main library.
                foreach ($h5p_json['preloadedDependencies'] as $dependency) {
                  if ($dependency['machineName'] == $h5p_json['mainLibrary']) {
                    $h5p_json['majorVersion'] = $dependency['majorVersion'];
                    $h5p_json['minorVersion'] = $dependency['minorVersion'];
                  }
                }

                $query = $this->database->select('h5p_libraries', 'h_l');
                $query->condition('machine_name', $h5p_json['mainLibrary'], '=');
                $query->condition('major_version', $h5p_json['majorVersion'], '=');
                $query->condition('minor_version', $h5p_json['minorVersion'], '=');
                $query->fields('h_l', ['library_id']);
                $query->orderBy('patch_version', 'DESC');
                $main_library_id = $query->execute()->fetchField();

                if (!$main_library_id) {
                  $query = $this->database->select('h5p_libraries', 'h_l');
                  $query->condition('machine_name', $h5p_json['mainLibrary'], '=');
                  $query->fields('h_l', ['library_id']);
                  $query->orderBy('major_version', 'DESC');
                  $query->orderBy('minor_version', 'DESC');
                  $query->orderBy('patch_version', 'DESC');
                  $main_library_id = $query->execute()->fetchField();
                }

                $content_json = $dir . '/content/content.json';
                $real_path = $this->fileSystem->realpath($content_json);
                $content_json = file_get_contents($real_path);

                $fields = [
                  'library_id' => $main_library_id,
                  'title' => $h5p_json['title'],
                  'parameters' => $content_json,
                  'filtered_parameters' => $content_json,
                  'disabled_features' => 0,
                  'authors' => '[]',
                  'changes' => '[]',
                  'license' => 'U',
                ];

                $h5p_content = H5PContent::create($fields);
                $h5p_content->save();
                $new_activity->set('opigno_h5p', $h5p_content->id());

                $h5p_dest_path = $this->config->get('h5p_default_path');
                $h5p_dest_path = $h5p_dest_path ?: 'h5p';

                $dest_folder = DRUPAL_ROOT . '/sites/default/files/' . $h5p_dest_path . '/content/' . $h5p_content->id();
                $source_folder = DRUPAL_ROOT . '/sites/default/files/opigno-import/h5p/content/*';
                $this->fileSystem->prepareDirectory($dest_folder, FileSystemInterface::MODIFY_PERMISSIONS | FileSystemInterface::CREATE_DIRECTORY);
                shell_exec('rm ' . $dest_folder . '/content.json');
                shell_exec('cp -r ' . $source_folder . ' ' . $dest_folder);

                // Clean up.
                $h5pEditorAjax->storage->removeTemporarilySavedFiles($h5pEditorAjax->core->h5pF->getUploadedH5pFolderPath());

                foreach ($dependencies as $dependency_key => $dependency) {
                  $query = $this->database->select('h5p_libraries', 'h_l');
                  $query->condition('machine_name', $dependency['machineName'], '=');
                  $query->condition('major_version', $dependency['majorVersion'], '=');
                  $query->condition('minor_version', $dependency['minorVersion'], '=');
                  $query->fields('h_l', ['library_id']);
                  $query->orderBy('patch_version', 'DESC');
                  $library_id = $query->execute()->fetchField();

                  if (!$library_id) {
                    $query = $this->database->select('h5p_libraries', 'h_l');
                    $query->condition('machine_name', $dependency['machineName'], '=');
                    $query->fields('h_l', ['library_id']);
                    $query->orderBy('major_version', 'DESC');
                    $query->orderBy('minor_version', 'DESC');
                    $query->orderBy('patch_version', 'DESC');
                    $library_id = $query->execute()->fetchField();
                  }

                  if ($h5p_json['mainLibrary'] == $dependency['machineName']) {
                    $main_library_values = [
                      'content_id' => $h5p_content->id(),
                      'library_id' => $library_id,
                      'dependency_type' => 'preloaded',
                      'drop_css' => 0,
                      'weight' => count($dependencies) + 1,
                    ];

                    continue;
                  }

                  if ($library_id) {
                    $this->database->insert('h5p_content_libraries')
                      ->fields([
                        'content_id',
                        'library_id',
                        'dependency_type',
                        'drop_css',
                        'weight',
                      ])
                      ->values([
                        'content_id' => $h5p_content->id(),
                        'library_id' => $library_id,
                        'dependency_type' => 'preloaded',
                        'drop_css' => 0,
                        'weight' => $dependency_key + 1,
                      ])
                      ->execute();
                  }
                }

                if (!empty($main_library_values)) {
                  $this->database->insert('h5p_content_libraries')
                    ->fields([
                      'content_id',
                      'library_id',
                      'dependency_type',
                      'drop_css',
                      'weight',
                    ])
                    ->values($main_library_values)
                    ->execute();
                }
              }
            }
            break;
        }

        $new_activity->save();
        $add_activities[] = $new_activity;
      }

      $this->moduleService->activitiesToModule($add_activities, $new_module);
      $route_parameters = [
        'opigno_module' => $new_module->id(),
      ];

      $this->messenger->addMessage($this->t('Imported module %module', [
        '%module' => Link::createFromRoute($new_module->label(), 'entity.opigno_module.canonical', $route_parameters)->toString(),
      ]));

      $form_state->setRedirect('entity.opigno_module.collection');
    }
    $this->fileSystem->deleteRecursive($this->tmp);
  }

}
