<?php

namespace Drupal\opigno_social\Plugin\Block;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormAjaxException;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social\Form\CreateCommentForm;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the "Create comment" block.
 *
 * @Block(
 *  id = "opigno_social_comment_form_block",
 *  admin_label = @Translation("Opigno Social create comment block"),
 *  category = @Translation("Opigno Social"),
 * )
 */
class CommentFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The loaded current user.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $user = NULL;

  /**
   * The user view builder service.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(FormBuilderInterface $form_builder, AccountInterface $account, EntityTypeManagerInterface $entity_type_manager, ...$default) {
    parent::__construct(...$default);
    $this->formBuilder = $form_builder;
    $this->viewBuilder = $entity_type_manager->getViewBuilder('user');
    $uid = (int) $account->id();

    try {
      $this->user = $entity_type_manager->getStorage('user')->load($uid);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('form_builder'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // The form should contain additional wrappers and elements, whaich are
    // impossible to add in the form template.
    $pid = $this->configuration['pid'] ?? 0;
    if (!$this->user instanceof UserInterface || !$pid) {
      return [
        '#cache' => ['max-age' => 0],
      ];
    }

    // Render the form.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$pid]);
    try {
      $form = $this->formBuilder->buildForm(static::getCommentFormName(), $form_state);
    }
    catch (EnforcedResponseException | FormAjaxException $e) {
      watchdog_exception('opigno_social_exception', $e);
      $form = [];
    }

    // Check the form access.
    $form_access = $form['#access'] ?? TRUE;

    // Prepare the close form link.
    $title = Markup::create('<i class="fi fi-rr-cross-small"></i>');
    $options = [
      'attributes' => [
        'class' => ['comment-item__collapse-comments', 'use-ajax'],
      ],
    ];

    return [
      '#theme' => 'opigno_social_comment_form_block',
      '#user' => $this->viewBuilder->view($this->user, 'post_author'),
      '#form' => $form_access ? $form : [],
      '#close_link' => Link::createFromRoute($title, static::getHideCommentsRoute(), ['pid' => $pid], $options),
      '#attached' => $form['#attached'] ?? [],
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Gets the comment form name.
   *
   * @return string
   *   The comment form name.
   */
  protected static function getCommentFormName(): string {
    return CreateCommentForm::class;
  }

  /**
   * Gets the "Hide comments" route name.
   *
   * @return string
   *   The "Hide comments" route name.
   */
  protected static function getHideCommentsRoute(): string {
    return 'opigno_social.hide_post_comments';
  }

}
