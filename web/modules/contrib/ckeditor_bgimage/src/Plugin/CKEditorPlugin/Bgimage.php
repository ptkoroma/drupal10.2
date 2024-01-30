<?php

namespace Drupal\ckeditor_bgimage\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "bgimage" plugin.
 *
 * @CKEditorPlugin(
 *   id = "bgimage",
 *   label = @Translation("Background Image")
 * )
 */
class Bgimage extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    if ($module_path = drupal_get_path('module', 'ckeditor_bgimage')) {
      return $module_path . '/src/js/bgimage/plugin.js';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'core/drupal.ajax',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'bgimage_dialogTitleAdd' => $this->t('Background Image'),
      'bgimage_dialogTitleEdit' => $this->t('Edit File'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'bgimage' => [
        'label' => $this->t('Background Image'),
        'image' => drupal_get_path('module', 'ckeditor_bgimage') . '/src/js/bgimage/icons/background.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\editor\Form\EditorFileDialog
   * @see ckeditor_bgimage_upload_settings_form()
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $form_state->loadInclude('ckeditor_bgimage', 'admin.inc');
    $form['file_upload'] = ckeditor_bgimage_upload_settings_form($editor);
    $form['file_upload']['#attached']['library'][] = 'ckeditor_bgimage/drupal.ckeditor.ckeditor_bgimage.admin';
    $form['file_upload']['#element_validate'][] = [$this, 'validateFileUploadSettings'];
    return $form;
  }

  /**
   * Element_validate handler for the "file_upload" element in settingsForm().
   *
   * Moves the text editor's file upload settings
   * from the bgimage plugin's
   * own settings into $editor->file_upload.
   *
   * @see \Drupal\editor\Form\EditorFileDialog
   * @see ckeditor_bgimage_upload_settings_form()
   */
  public function validateFileUploadSettings(array $element, FormStateInterface $form_state) {
    $settings = &$form_state->getValue([
      'editor', 'settings', 'plugins', 'bgimage', 'file_upload',
    ]);
    $editor = $form_state->get('editor');
    foreach ($settings as $key => $value) {
      if (!empty($value)) {
        $editor->setThirdPartySetting('ckeditor_bgimage', $key, $value);
      }
      else {
        $editor->unsetThirdPartySetting('ckeditor_bgimage', $key);
      }
    }
    $form_state->unsetValue(['editor', 'settings', 'plugins', 'bgimage']);

  }

}
