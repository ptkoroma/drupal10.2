<?php

namespace Drupal\opigno_class\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class Social settings.
 */
class SocialSettingsForm extends ConfigFormBase {

  use StringTranslationTrait;

  const NONE_USERS = 0;
  const SAME_CLASS = 1;
  const SAME_TRAINING = 2;
  const SAME_USERS = 3;
  const ALL_USERS = 4;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'opigno_class.socialsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('opigno_class.socialsettings');
    $form['social'] = [
      '#type' => 'radios',
      '#title' => $this->t('Social sharing'),
      '#options' => [
        self::NONE_USERS => 'no other student (only global managers. Drupal roles: user manager or content manager or administrator)',
        self::SAME_CLASS => 'the students sharing the same class(es)',
        self::SAME_TRAINING => 'the students sharing the same training(s)',
        self::SAME_USERS => 'the students sharing the class(es) + the training(s)',
        self::ALL_USERS => 'all the students',
      ],
      '#default_value' => $form_state->hasValue('social') ? $form_state->getValue('social') : $config->get('social'),
      '#description' => $this->t("Setting to define which users students can connect with."),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('opigno_class.socialsettings')
      ->set('social', (int) $form_state->getValue('social'))
      ->save();
  }

}
