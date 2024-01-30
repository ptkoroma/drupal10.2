<?php

namespace Drupal\opigno_social\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a post form.
 *
 * @package Drupal\opigno_social\Form
 */
class CreatePostForm extends FormBase {

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * CreatePostForm constructor.
   *
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator service.
   */
  public function __construct(CsrfTokenGenerator $csrf_token) {
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('csrf_token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_create_post_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'] = ['comment-form'];
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Write something'),
      '#rows' => 3,
      '#cols' => 0,
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'create-post-textfield',
        'class' => ['form-text'],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Post'),
      '#attributes' => [
        'class' => ['opigno-create-shared-post', 'main-wall'],
      ],
    ];

    $url = Url::fromRoute(static::getCreatePostRouteName());
    $internal = $url->getInternalPath();
    $url = $url->setOption('query', ['token' => $this->csrfToken->get($internal)])->toString();

    $form['actions']['submit']['#attributes']['data-ajax-url'] = $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url;
    $form['#attached'] = [
      'library' => ['opigno_social/post_sharing'],
    ];

    return $form;
  }

  /**
   * Gets the "Create post" route name.
   *
   * @return string
   *   The "Create post" route name.
   */
  protected static function getCreatePostRouteName(): string {
    return 'opigno_social.create_post';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing, the form is submitted with ajax.
  }

}
