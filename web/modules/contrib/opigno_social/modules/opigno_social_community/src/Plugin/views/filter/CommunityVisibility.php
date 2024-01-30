<?php

namespace Drupal\opigno_social_community\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social_community\Entity\Community;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the view filter to display the communities depending on visibility.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_community_visibility")
 *
 * @package Drupal\opigno_social_community\Plugin\views\filter
 */
class CommunityVisibility extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public $operator = 'IN';

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $account, ...$default) {
    parent::__construct(...$default);
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_user'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function operatorOptions() {
    return [
      'IN' => $this->t('Is one of'),
      'NOT IN' => $this->t('Is not one of'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function operatorForm(&$form, FormStateInterface $form_state) {
    parent::operatorForm($form, $form_state);
    $form['operator']['#description'] = $this->t('The filter with selected options will be applied only if the user does not have "%permission" permission', [
      '%permission' => 'view any opigno_community',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value'] = [
      '#type' => 'select',
      '#options' => Community::getVisibilityOptions(),
      '#multiple' => TRUE,
      '#default_value' => $this->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return is_array($this->value) ? $this->operator . ' ' . implode(', ', $this->value) : parent::adminSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Filter communities by visibility.
    if (!$this->query instanceof Sql
      || $this->account->hasPermission('view any opigno_community')
      || !$this->value
    ) {
      return;
    }

    // Prepare query.
    $this->ensureMyTable();
    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $this->value, $this->operator);
  }

}
