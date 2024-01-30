<?php

namespace Drupal\ckeditor_bgimage\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Component\Utility\Bytes;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Environment;

/**
 * Provides a link dialog for text editors.
 */
class EditorFileDialog extends FormBase implements BaseFormIdInterface {

  /**
   * The file storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * Constructs a form object for image dialog.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $file_storage
   *   The file storage service.
   */
  public function __construct(EntityStorageInterface $file_storage) {
    $this->fileStorage = $file_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('file')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'editor_bgimage_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    // Use the EditorLinkDialog form id to ease alteration.
    return 'editor_bgimage_link_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FilterFormat $filter_format = NULL) {

    $file_element = $form_state->get('file_element') ?: [];
    if (isset($form_state->getUserInput()['editor_object'])) {
      $file_element = $form_state->getUserInput()['editor_object'];
      $form_state->set('file_element', $file_element);
      $form_state->setCached(TRUE);
    }

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';
    $form['#prefix'] = '<div id="editor-bgimage-dialog-form">';
    $form['#suffix'] = '</div>';

    $editor = editor_load($filter_format->id());
    $file_upload = $editor->getThirdPartySettings('ckeditor_bgimage');
    $max_filesize = min(Bytes::toInt($file_upload['max_size']), Environment::getUploadMaxSize());

    $upload_directory = $file_upload['scheme'] . '://' . $file_upload['directory'];

    if (!empty($file_element["file"])) {
      $url = $file_element["file"];
      $pos = strrpos($url, '/');
      if ($pos !== FALSE) {
        $filename = substr($url, $pos + 1);
        $uri = $upload_directory . '/' . $filename;
        $file = $this->fileStorage->loadByProperties(['uri' => $uri]);

        if ($file) {
          $file = array_shift($file);
          $fid = $file->id();
        }
      }
    }

    $ext = (!empty($file_upload['extensions']))
      ? [$file_upload['extensions']]
      : ['jpg', 'jpeg', 'png'];

    $form['fid'] = [
      '#title' => $this->t('Background Image'),
      '#type' => 'managed_file',
      '#upload_location' => $upload_directory,
      '#default_value' => !empty($fid) ? [$fid] : NULL,
      '#upload_validators' => [
        'file_validate_extensions' => $ext,
        'file_validate_size' => [$max_filesize],
      ],
      '#access' => TRUE,
    ];

    $form['attributes']['href'] = [
      '#title' => $this->t('URL'),
      '#type' => 'textfield',
      '#default_value' => isset($file_element['href']) ? $file_element['href'] : '',
      '#maxlength' => 2048,
      '#access' => TRUE,
    ];

    $width = '';
    $height = '';
    if (!empty($file_element["style"])) {
      $size = explode(';', trim($file_element["style"]));
      if (!empty($size[0])) {
        $width = $size[0];
        $width = str_replace('width:', '', $width);
        $width_digits = preg_replace('/\D/', '', $width);
        $units_width = str_replace($width_digits, '', $width);
        $width = $width_digits;
      }

      if (!empty($size[1])) {
        $height = $size[1];
        $height = str_replace('height:', '', $height);
        $height_digits = preg_replace('/\D/', '', $height);
        $units_height = str_replace($height_digits, '', $height);
        $height = $height_digits;
      }
    }

    $color = !empty($file_element["color"]) ? trim($file_element["color"]) : '#ffffff';
    $form['background_color'] = [
      '#title' => $this->t('Background Color'),
      '#type' => 'color',
      '#default_value' => $color,
      '#description' => $this->t('Select a Color'),
      '#maxlength' => 10,
    ];

    $form['width_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Width settings'),
    ];
    $form['width_settings']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Set width digital value and width units in the field below.'),
      '#default_value' => $width,
      '#attributes' => [
        'type' => 'number',
      ],
      '#size' => 8,
    ];
    $form['width_settings']['units_width'] = [
      '#type' => 'select',
      '#title' => $this->t('Width units'),
      '#options' => [
        '%' => '%',
        'px' => 'px',
        'em' => 'em',
        'in' => 'in',
      ],
      '#default_value' => !empty($units_width) ? $units_width : '%',
    ];

    $form['height_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Height settings'),
    ];
    $form['height_settings']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Set height digital value and height units in the field below.'),
      '#default_value' => $height,
      '#attributes' => [
        'type' => 'number',
      ],
      '#size' => 8,
    ];

    $form['height_settings']['units_height'] = [
      '#type' => 'select',
      '#title' => $this->t('Height units'),
      '#options' => [
        'px' => 'px',
        'em' => 'em',
        'in' => 'in',
      ],
      '#default_value' => !empty($units_height) ? $units_height : 'px',
    ];

    $position = !empty($file_element["position"]) ? $file_element["position"] : 'left';

    $form['background_aling'] = [
      '#type' => 'select',
      '#title' => $this->t('Image align'),
      '#default_value' => $position,
      '#options' => [
        'left top' => $this->t('Left Top'),
        'left center' => $this->t('Left Center'),
        'left bottom' => $this->t('Left Bottom'),
        'right top' => $this->t('Right Top'),
        'right center' => $this->t('Right Center'),
        'right bottom' => $this->t('Right Bottom'),
        'center top' => $this->t('Center Top'),
        'center center' => $this->t('Center Center'),
        'center bottom' => $this->t('Center Bottom'),
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
    ];

    if ($file_upload['status']) {
      $form['attributes']['href']['#access'] = FALSE;
      $form['attributes']['href']['#required'] = FALSE;
    }
    else {
      $form['fid']['#access'] = FALSE;
      $form['fid']['#required'] = FALSE;
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['save_modal'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => [],
      '#ajax' => [
        'callback' => '::submitForm',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $width_settings = $form_state->getValue('width_settings', '');
    $height_settings = $form_state->getValue('height_settings', '');

    $width = $width_settings['width'] == 0 ? '' : $width_settings['width'];
    $height = $height_settings['height'] == 0 ? '' : $height_settings['height'];

    if (!empty($width) && !is_numeric($width)) {
      $form_state->setErrorByName('width', $this->t("Width field wrong format."));
    }

    if (!empty($height) && !is_numeric($height)) {
      $form_state->setErrorByName('height', $this->t("Height field wrong format."));
    }

    $units_width = $width_settings['units_width'];
    $units_height = $height_settings['units_height'];

    $form_state->setValue('width', $width . $units_width);
    $form_state->setValue('height', $height . $units_height);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $response = new AjaxResponse();
    $form_state->setValue(['attributes', 'idModal'], rand(1000000, 99999999));
    $fid = $form_state->getValue(['fid', 0]);

    if (!empty($fid)) {
      /** @var \Drupal\file\FileInterface */
      $file = $this->fileStorage->load($fid);
      $file_url = file_create_url($file->getFileUri());
      $file_url = file_url_transform_relative($file_url);
      $form_state->setValue(['attributes', 'image'], $file_url);
      $form_state->setValue(['attributes', 'data_entity_uuid'], $file->uuid());
      $form_state->setValue(['attributes', 'data_entity_type'], 'file');
    }

    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#editor-bgimage-dialog-form', $form));
      return $response;
    }

    $response->addCommand(new EditorDialogSave($form_state->getValues()));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

}
