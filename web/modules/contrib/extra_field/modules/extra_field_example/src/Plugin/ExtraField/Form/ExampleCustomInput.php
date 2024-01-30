<?php

namespace Drupal\extra_field_example\Plugin\ExtraField\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\extra_field\Plugin\ExtraFieldFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Example Extra field form display.
 *
 * This plugin provides a textual input field that is used to provide input for
 * a hidden entity reference field. The field can be used in the user
 * registration form. An autocomplete widget can not be used for a user facing
 * voucher code field.
 *
 * @ExtraFieldForm(
 *   id = "example_custom_input",
 *   label = @Translation("Custom input for entity reference"),
 *   description = @Translation("An extra field with its own validation and submit handler."),
 *   bundles = {
 *     "user.user"
 *   },
 *   visible = true
 * )
 */
class ExampleCustomInput extends ExtraFieldFormBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The name of the voucher code input field.
   */
  const VOUCHER_CODE_FIELD = 'voucher_code';

  /**
   * The name of the field that references a voucher code.
   */
  const VOUCHER_REFERENCE_FIELD = 'field_voucher';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ExampleCustomInput constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(array &$form, FormStateInterface $form_state) {
    $form[self::VOUCHER_CODE_FIELD] = [
      '#type' => 'textfield',
      '#title' => $this->t('Voucher code'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['#validate'][] = [$this, 'validateVoucherCode'];
  }

  /**
   * Field validation callback for voucher code.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateVoucherCode(array $form, FormStateInterface $form_state) {

    $voucherCode = $this->lookupVoucherCodeEntity($form_state->getValue('voucher_code_text'));
    if (empty($voucherCode)) {
      $form_state->setErrorByName(self::VOUCHER_CODE_FIELD, $this->t('Invalid voucher code'));
      return;
    }

    if ($voucherCode->isExpired()) {
      $form_state->setErrorByName(self::VOUCHER_CODE_FIELD, $this->t('This voucher code is no longer valid'));
      return;
    }

    // Set the entity reference to match the voucher code input.
    $form_state->setValue(self::VOUCHER_REFERENCE_FIELD, [['target_id' => $voucherCode->id()]]);
  }

  /**
   * Lookup the voucher entity that corresponds with a given code.
   *
   * @param string $code
   *   The voucher code.
   *
   * @return \Drupal\my_voucher_code\VoucherCodeInterface|null
   *   The voucher entity. Null if no entity exists for this
   *   code.
   */
  private function lookupVoucherCodeEntity($code) {

    if (empty($code)) {
      return NULL;
    }

    /** @var \Drupal\my_voucher\VoucherInterface[] $vouchers */
    $vouchers = $this->entityTypeManager->getStorage('voucher')->loadByProperties(['code' => trim($code)]);

    return empty($vouchers) ? NULL : reset($vouchers);
  }

}
