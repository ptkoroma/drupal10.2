<?php

namespace Drupal\opigno_catalog\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\filter\StringFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter the user LP status considering the user ID.
 *
 * @ViewsFilter("opigno_user_lp_status")
 *
 * @package Drupal\opigno_catalog\Plugin\views\filter
 */
class OpignoUserLearningPathStatusFilter extends StringFilter {

  /**
   * The current user account.
   *
   * @var array|\Drupal\Core\Session\AccountInterface
   */
  protected $account;

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
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['current_user'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['current_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Consider the current user.'),
      '#description' => $this->t('Apply the filter only to trainings the current user belongs to.'),
      '#weight' => -10,
      '#default_value' => $this->options['current_user'] ?? FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $this->options['current_user'] = $form_state->getValue([
      'options',
      'current_user',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    parent::query();
    $consider_user = $this->options['current_user'] ?? FALSE;

    if (!$consider_user) {
      return;
    }

    // Filter by status considering the current user ID.
    $uid = (int) $this->account->id();
    // The filter should consider only the latest LP attempt.
    $attempts_query = $this->connection->select('user_lp_status', 'uls');
    $attempts_query->addExpression('MAX(uls.id)', 'latest');
    $attempts = $attempts_query
      ->condition('uls.uid', $uid)
      ->groupBy('uls.gid')
      ->execute()
      ->fetchCol();

    if ($attempts) {
      $this->ensureMyTable();
      $this->query->addWhere(NULL, "$this->tableAlias.id", $attempts, 'IN');
    }
  }

}
