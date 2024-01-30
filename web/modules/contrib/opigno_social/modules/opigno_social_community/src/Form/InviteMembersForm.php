<?php

namespace Drupal\opigno_social_community\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_learning_path\LearningPathMembersManager;
use Drupal\opigno_learning_path\Plugin\LearningPathMembers\RecipientsPlugin;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Services\CommunityManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the "Invite community members" form.
 *
 * @package Drupal\opigno_social_community\Form
 */
class InviteMembersForm extends FormBase {

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
   * InviteMembersForm constructor.
   *
   * @param \Drupal\opigno_learning_path\LearningPathMembersManager $lp_members_manager
   *   The Opigno LP members manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    LearningPathMembersManager $lp_members_manager,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $account
  ) {
    $this->membersPlugin = $lp_members_manager->createInstance('recipients_plugin');
    $uid = $account->id();
    $this->currentUser = $entity_type_manager->getStorage('user')->load($uid);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_learning_path.members.manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_invite_community_members_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CommunityInterface $community = NULL) {
    if (!$this->membersPlugin instanceof RecipientsPlugin || !$community instanceof CommunityInterface) {
      return [];
    }

    $form_state->set('community', $community);
    $this->membersPlugin->getMembersForm($form, $form_state, $this->currentUser, function ($current_user) use ($community) {
      // Exclude the already invited users from the general list.
      return CommunityManagerService::getAvailableCommunityInvitees($current_user, $community);
    });

    $form['users_to_send']['#title'] = $this->t('Invite users');
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Invite'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

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
    $response->addCommand(new InvokeCommand('.modal', 'modal', ['hide']));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $members = $form_state->getValue('users_to_send', []);
    $community = $form_state->get('community');

    if (!$members || !$community instanceof CommunityInterface) {
      return;
    }

    $invitor = (int) $this->currentUser->id();
    foreach ($members as $member) {
      $community->inviteMember((int) $member, $invitor);
    }
  }

}
