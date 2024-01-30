<?php

namespace Drupal\opigno_social_community\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\opigno_social_community\Entity\Community;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the community administration settings form.
 *
 * @package Drupal\opigno_social_community\Form
 */
class CommunityAdministrationForm extends ConfigFormBase {

  /**
   * The user entity storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->userStorage = $entity_type_manager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [Community::ADMIN_CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_social_community.admin_form';
  }

  /**
   * Gets the list of allowed operations.
   *
   * @param bool $only_keys
   *   If only operation keys should be returned or not.
   *
   * @return array
   *   The list of operations.
   */
  private function getOperations(bool $only_keys = FALSE): array {
    $operations = [
      'allow_view' => $this->t('Allow the following users to view any community'),
      'allow_create' => $this->t('Allow the following users to create a community'),
      'allow_update' => $this->t('Allow the following users to edit any community'),
      'allow_delete' => $this->t('Allow the following users to delete any community'),
      'allow_invite_member' => $this->t('Allow the following users to invite users to any community'),
      'allow_cancel_invitation' => $this->t('Allow the following users to cancel any pending community invitation'),
    ];

    return $only_keys ? array_keys($operations) : $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $is_socials_enabled = (bool) $this->config(Community::ADMIN_CONFIG_NAME)->get('enable_communities') ?? FALSE;
    if (!$is_socials_enabled) {
      $form['markup'] = [
        '#markup' => $this->t('Community features disabled on the site. <br> To use communities functionality please enable these features <a href="@link">here</a>.', [
          '@link' => Url::fromRoute('opigno_class.social_settings_form')->toString(),
        ]),
      ];

      return $form;
    }

    $form = parent::buildForm($form, $form_state);
    $operations = $this->getOperations();

    foreach ($operations as $operation => $title) {
      $default = $this->config(Community::ADMIN_CONFIG_NAME)->get($operation) ?? [];
      $form[$operation] = [
        '#type' => 'entity_autocomplete',
        '#title' => $title,
        '#target_type' => 'user',
        '#default_value' => $default ? $this->userStorage->loadMultiple($default) : [],
        '#tags' => TRUE,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $fields = $this->getOperations(TRUE);
    $config = $this->config(Community::ADMIN_CONFIG_NAME);
    foreach ($fields as $field) {
      $values = $form_state->getValue($field, []);
      $uids = [];
      if ($values) {
        foreach ($values as $value) {
          $uids[] = $value['target_id'];
        }
      }

      $config->set($field, $uids);
    }

    $config->save();
  }

}
