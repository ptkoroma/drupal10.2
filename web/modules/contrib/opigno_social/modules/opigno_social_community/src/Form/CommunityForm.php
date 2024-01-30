<?php

namespace Drupal\opigno_social_community\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\opigno_learning_path\LearningPathMembersManager;
use Drupal\opigno_learning_path\Plugin\LearningPathMembers\RecipientsPlugin;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Services\CommunityManagerService;
use Drupal\user\UserInterface;
use Drupal\views\Ajax\ScrollTopCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the base Opigno community form.
 *
 * @package Drupal\opigno_social_community\Form
 */
class CommunityForm extends ContentEntityForm {

  /**
   * The Opigno members plugin.
   */
  protected object $membersPlugin;

  /**
   * The current user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    LearningPathMembersManager $lp_members_manager,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $account,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->membersPlugin = $lp_members_manager->createInstance('recipients_plugin');
    $uid = $account->id();
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $entity_type_manager->getStorage('user')->load($uid);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_learning_path.members.manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->getEntity();
    $is_new = $entity->isNew();

    // Add members selection tool.
    if ($this->membersPlugin instanceof RecipientsPlugin
      && $this->currentUser instanceof UserInterface
      && $entity instanceof CommunityInterface
      && $is_new
    ) {
      $this->membersPlugin->getMembersForm($form, $form_state, $this->currentUser, function ($current_user) use ($entity) {
        // Exclude the already invited users from the general list.
        return CommunityManagerService::getAvailableCommunityInvitees($current_user, $entity);
      });

      $form['users_to_send']['#title'] = $this->t('Invite users');
      $form['users_to_send']['#default_value'] = $entity->getMembers();
      $form['users_to_send']['#weight'] = 10;
    }

    // Update the action buttons.
    $form['actions']['submit']['#weight'] = 20;
    $form['actions']['submit']['#value'] = $is_new
      ? $this->t('Create the community')
      : $this->t('Save changes');

    if (isset($form['actions']['delete'])) {
      $form['actions']['delete']['#title'] = $this->t('Delete the community');
      // Hide the delete action button if the form is rendered via AJAX.
      if ($form_state->get('ajax_modal')) {
        $form['actions']['delete']['#access'] = FALSE;
      }
    }

    // Ajaxify the form if it's needed.
    if ($form_state->get('ajax_modal')) {
      // Add the placeholder for the status messages.
      $form['status_messages_container'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['opigno-status-messages-container'],
        ],
        '#weight' => -50,
      ];

      $url = Url::fromRoute('<current>')
        ->setOption('query', [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE])
        ->toString();
      $form['#action'] = $url;
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
        'url' => $url,
      ];
    }

    return $form;
  }

  /**
   * The AJAX submit callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response to close the modal window.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    // Display error messages if the form is not validated.
    if ($form_state->getErrors()) {
      $status_messages = [
        '#type' => 'status_messages',
        '#weight' => -50,
      ];
      $response->addCommand(new HtmlCommand('.modal-body .opigno-status-messages-container', $status_messages));
      $response->addCommand(new ScrollTopCommand('.opigno-status-messages-container'));

      return $response->setStatusCode(400, $this->t('The form is not validated.'));
    }

    // Redirect to the community page is the entity has been created with ajax.
    $cid = $this->entity->id();
    if ($cid && $this->operation === 'add') {
      $redirect_url = Url::fromRoute('entity.opigno_community.canonical', [
        'opigno_community' => $cid,
      ])->toString();
      $response->addCommand(new RedirectCommand($redirect_url));
    }
    else {
      $info = $this->entityTypeManager->getViewBuilder('opigno_community')->view($this->entity, 'info_block');
      $response->addCommand(new ReplaceCommand('.opigno-community-info-block', $info));
      $response->addCommand(new InvokeCommand('.modal', 'modal', ['hide']));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Set the owner as a member.
    $community = $this->getEntity();
    if ($community->isNew() && $community instanceof CommunityInterface) {
      $community->addMember($this->currentUser());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    // Send an invitation to all new members.
    $members = $form_state->getValue('users_to_send', []);
    $id = $this->entity->id();
    if (!$members || !$id || !$this->entity instanceof CommunityInterface) {
      return $status;
    }

    $invitor = (int) $this->currentUser->id();
    foreach ($members as $member) {
      $this->entity->inviteMember((int) $member, $invitor);
    }

    return $status;
  }

}
