<?php

namespace Drupal\opigno_module\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\h5peditor\H5PEditor\H5PEditorUtilities;

/**
 * Class LearningPathAdminSettingsForm.
 */
class ModuleH5PAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'opigno_module.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'survey_admin_settings_form';
  }

  /**
   * Returns disabled H5P libraries by default.
   *
   * @return array
   */
  public static function disabledByDefault() {
    return [
      'H5P.Dialogcards',
      'H5P.MarkTheWords',
      'H5P.Audio',
      'H5P.Summary',
      'H5P.TwitterUserFeed',
      'H5P.AppearIn',
      'H5P.SingleChoiceSet',
      'H5P.Accordion',
      'H5P.Agamotto',
      'H5P.Collage',
      'H5P.ImageMultipleHotspotQuestion',
      'H5P.IFrameEmbed',
      'H5P.MemoryGame',
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $editor = H5PEditorUtilities::getInstance();
    $list = $editor->getLatestGlobalLibrariesData();

    $options = [];
    foreach ($list as $item) {
      $options[$item['machineName']] = $item['title'] . ' (' . $item['machineName'] . ')';
    }

    $form['h5p_disabled'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Disabled H5P types'),
      '#options' => $options,
    );

    $not_recommended = static::disabledByDefault();
    foreach ($not_recommended as $item) {
      $form['h5p_disabled'][$item] = ['#disabled' => TRUE];
    }

    $config = $this->config('opigno_module.settings');
    $disabled = $config->get('disabled_h5p');

    $form['h5p_disabled']['#default_value'] = array_merge($not_recommended, $disabled);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $form_values = $form_state->getValues();

    $disabled = array_filter($form_values['h5p_disabled'], function ($item) {
      if ($item !== 0) return $item;
    });

    $not_recommended = static::disabledByDefault();

    $config = array_merge($disabled, $not_recommended);

    $this->config('opigno_module.settings')
      ->set('disabled_h5p', array_values($config))
      ->save();
  }
}
