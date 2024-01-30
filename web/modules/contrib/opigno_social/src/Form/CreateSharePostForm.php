<?php

namespace Drupal\opigno_social\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\opigno_social\Services\OpignoPostsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a post form.
 *
 * @package Drupal\opigno_social\Form
 */
class CreateSharePostForm extends FormBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Opigno posts manager.
   *
   * @var \Drupal\opigno_social\Services\OpignoPostsManager
   */
  protected $postsManager;

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * CreateSharePostForm constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\opigno_social\Services\OpignoPostsManager $posts_manager
   *   The Opigno posts manager service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator service.
   */
  public function __construct(RendererInterface $renderer, OpignoPostsManager $posts_manager, CsrfTokenGenerator $csrf_token) {
    $this->renderer = $renderer;
    $this->postsManager = $posts_manager;
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('opigno_posts.manager'),
      $container->get('csrf_token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_create_share_post_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $type = '',
    int $id = 0,
    string $entity_type = '',
    string $text = ''
  ) {
    $form_state->set('attachment_type', $type);
    $form_state->set('attachment_id', $id);
    $form_state->set('attachment_entity_type', $entity_type);
    $form_state->set('post_text', $text);

    $form['#theme'] = 'opigno_create_share_post_form';
    $form['#attributes']['class'] = [
      'views-exposed-form',
      'opigno-create-share-post-form',
    ];

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Write something'),
      '#rows' => 3,
      '#cols' => 0,
      '#default_value' => $text ?? '',
      '#required' => TRUE,
      '#attributes' => [
        'id' => 'create-share-post-textfield',
        'class' => ['form-text'],
      ],
    ];

    // Add the placeholder for the status messages.
    $form['status_messages_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['opigno-status-messages-container'],
      ],
      '#weight' => -50,
    ];

    // Add attached content (training/badge/certificate).
    $attachment = $this->postsManager->renderPostAttachment(NULL, $type, $id, $entity_type);
    if ($attachment) {
      $form['attachment'] = [
        '#markup' => $this->renderer->renderRoot($attachment),
      ];
      $form['hint_text'] = $this->t('Only visible to connections');
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Post'),
      '#attributes' => [
        'class' => ['opigno-create-shared-post', 'btn', 'btn-rounded'],
      ],
    ];

    $form['#attached'] = [
      'library' => ['opigno_social/post_sharing'],
    ];

    $url = Url::fromRoute(static::getCreatePostRouteName());
    $internal = $url->getInternalPath();
    $url = $url->setOption('query', ['token' => $this->csrfToken->get($internal)])->toString();
    $form['actions']['submit']['#attributes'] += [
      'data-opigno-post-attachment-id' => $form_state->get('attachment_id'),
      'data-opigno-attachment-type' => $form_state->get('attachment_type'),
      'data-opigno-attachment-entity-type' => $form_state->get('attachment_entity_type'),
      'data-ajax-url' => $url instanceof GeneratedUrl ? $url->getGeneratedUrl() : $url,
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
    // Do nothing, the form is submitted with the custom ajax.
  }

}
