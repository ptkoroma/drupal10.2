<?php

namespace Drupal\opigno_social_community\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\opigno_social_community\Entity\Community;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Services\CommunityManagerService;
use Drupal\opigno_social_community\Services\CommunityStatistics;
use Drupal\user\UserInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the community controller.
 *
 * @package Drupal\opigno_social_community\Controller
 */
class CommunityController extends ControllerBase {

  /**
   * The community join link ID prefix.
   */
  const JOIN_LINK_PREFIX = 'opigno-community-join-link-';

  /**
   * The Opigno community invitation storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $invitationStorage;

  /**
   * The Opigno community entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $communityStorage;

  /**
   * The community manager service.
   *
   * @var \Drupal\opigno_social_community\Services\CommunityManagerService
   */
  protected CommunityManagerService $communityManager;

  /**
   * The route access service.
   *
   * @var \Drupal\Core\Routing\AccessAwareRouterInterface
   */
  protected AccessAwareRouterInterface $router;

  /**
   * The community statistics manager service.
   *
   * @var \Drupal\opigno_social_community\Services\CommunityStatistics
   */
  protected CommunityStatistics $statsManager;

  /**
   * CommunityController constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder service.
   * @param \Drupal\opigno_social_community\Services\CommunityManagerService $community_manager
   *   The community manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\Core\Routing\AccessAwareRouterInterface $router
   *   The route access service.
   * @param \Drupal\opigno_social_community\Services\CommunityStatistics $statistics
   *   The community statistics manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder,
    CommunityManagerService $community_manager,
    FormBuilderInterface $form_builder,
    AccessAwareRouterInterface $router,
    CommunityStatistics $statistics
  ) {
    $this->currentUser = $account;
    $this->invitationStorage = $entity_type_manager->getStorage('opigno_community_invitation');
    $this->communityStorage = $entity_type_manager->getStorage('opigno_community');
    $this->entityFormBuilder = $entity_form_builder;
    $this->communityManager = $community_manager;
    $this->formBuilder = $form_builder;
    $this->router = $router;
    $this->statsManager = $statistics;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('opigno_social_community.manager'),
      $container->get('form_builder'),
      $container->get('router'),
      $container->get('opigno_social_community.statistics')
    );
  }

  /**
   * Implements the "Join community" route callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The community to join.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function joinCommunity(Request $request, CommunityInterface $opigno_community): AjaxResponse {
    $response = new AjaxResponse();
    if (!$opigno_community->isPublic()) {
      return $response->setStatusCode(403, $this->t('You can join only public communities.'));
    }

    $cid = $opigno_community->id();
    $opigno_community->addMember($this->currentUser);

    try {
      $opigno_community->save();
      // Remove all pending invitations if the user joins the community.
      $invitations = $opigno_community->getUserPendingInvitations($this->currentUser->id());

      if ($invitations) {
        $this->invitationStorage->delete($invitations);
      }
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
      return $response->setStatusCode(400, $this->t('An error occurred, the user can not be added as a member to the selected community.'));
    }

    // Replace the "join" link with the "see" if the user isn't on the community
    // page. Otherwise - reload to update the page content.
    $referer = $request->server->get('HTTP_REFERER');
    $route_info = $this->router->match($referer);
    $referer_route = $route_info['_route'] ?? '';
    if ($referer_route === 'entity.opigno_community.canonical') {
      $redirect_url = Url::fromRoute('entity.opigno_community.canonical', ['opigno_community' => $cid])->toString();
      $response->addCommand(new RedirectCommand($redirect_url));
    }
    else {
      $see_link = Link::createFromRoute($this->t('See'),
        'entity.opigno_community.canonical',
        ['opigno_community' => $cid],
        ['attributes' => ['class' => ['btn', 'btn-rounded', 'btn-bg']]]
      )->toRenderable();

      $response->addCommand(new ReplaceCommand('#' . static::JOIN_LINK_PREFIX . $cid, $see_link));
    }

    return $response;
  }

  /**
   * Implements the callback to send a community join request.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The Opigno community to send the join request to.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function joinRequest(CommunityInterface $opigno_community): AjaxResponse {
    $response = new AjaxResponse();
    $uid = (int) $this->currentUser->id();

    if ($opigno_community->getVisibility() !== Community::VISIBILITY_RESTRICTED
      || $opigno_community->isMember($uid)
    ) {
      return $response->setStatusCode(403, $this->t("You can send request to join only restricted communities you're not a member of."));
    }

    $cid = (int) $opigno_community->id();
    // Check if the invitation already exists.
    $existing = $this->invitationStorage->loadByProperties([
      'invitee' => $uid,
      'community' => $cid,
    ]);

    if (!$existing) {
      $request = $this->invitationStorage->create([
        'uid' => $opigno_community->getOwnerId(),
        'invitee' => $uid,
        'community' => $cid,
        'is_join_request' => TRUE,
      ]);
      try {
        $request->save();
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_social_community_exception', $e);
        return $response->setStatusCode(400, $this->t('An error occurred, the join request can not be created.'));
      }
    }

    // Replace the "request to join" link with the "pending approval" message.
    $approval = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('Pending approval'),
      '#attributes' => [
        'class' => ['btn', 'btn-rounded', 'inactive'],
      ],
    ];
    $response->addCommand(new ReplaceCommand('#' . static::JOIN_LINK_PREFIX . $cid, $approval));

    return $response;
  }

  /**
   * Prepares the render array for the "Join communities" page.
   *
   * @return array
   *   The render array for the "Join communities" page.
   */
  public function joinCommunitiesPage(): array {
    $uid = $this->currentUser->id();

    return [
      '#theme' => 'opigno_communities_join_page',
      '#listing' => Views::getView('communities')->executeDisplay('join'),
      '#pending' => Views::getView('community_invitations')->executeDisplay('pending', [$uid]),
      '#create_btn' => $this->communityManager->getCreateCommunityLink(),
      '#cache' => [
        'tags' => ['user:' . $uid, 'config:' . Community::ADMIN_CONFIG_NAME],
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Opens the community create form in AJAX modal window.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function openAjaxCommunityCreateForm(): AjaxResponse {
    $community = $this->communityStorage->create([
      'uid' => $this->currentUser->id(),
    ]);

    return $this->openAjaxCommunityForm($community, 'add', $this->t('Create community'));
  }

  /**
   * AJAX callback to render the "Edit community" form.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The community entity to be edited.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function openAjaxCommunityEditForm(CommunityInterface $opigno_community): AjaxResponse {
    return $this->openAjaxCommunityForm($opigno_community, 'edit', $this->t('Edit community'));
  }

  /**
   * AJAX callback to render the "Delete community" form.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The community entity to be edited.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function openAjaxCommunityDeleteForm(CommunityInterface $opigno_community): AjaxResponse {
    return $this->openAjaxCommunityForm($opigno_community,
      'delete',
      $this->t('Do you want delete the community?'),
      ['community-modal-actions']
    );
  }

  /**
   * The general AJAX callback to display the community entity form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $community
   *   The community entity to render the form for.
   * @param string $operation
   *   The operation to render the form for.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The form title.
   * @param array $classes
   *   The list of extra classes for the modal wrapper.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to display the community entity form.
   */
  protected function openAjaxCommunityForm(
    EntityInterface $community,
    string $operation,
    TranslatableMarkup $title,
    array $classes = []
  ): AjaxResponse {
    $response = new AjaxResponse();
    if (!$community instanceof CommunityInterface) {
      return $response->setStatusCode(400, $this->t('Can not render the form for the given entity.'));
    }

    $params = ['ajax_modal' => TRUE];
    // Prepare the popup data.
    $build = [
      '#theme' => 'opigno_community_modal',
      '#title' => $title,
      '#body' => $this->entityFormBuilder->getForm($community, $operation, $params),
      '#classes' => $classes,
    ];

    // Close all previously opened modals.
    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $build));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));

    return $response;
  }

  /**
   * AJAX callback to delete the community member.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The community to delete the member from.
   * @param \Drupal\user\UserInterface $user
   *   The user to be deleted from the list of community members.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function deleteMember(CommunityInterface $opigno_community, UserInterface $user): AjaxResponse {
    $response = new AjaxResponse();
    $uid = (int) $user->id();
    $opigno_community->deleteMember($uid);
    try {
      $opigno_community->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
      return $response->setStatusCode(400, $this->t('An error occurred, the community can not be updated.'));
    }

    return $response->addCommand(new RemoveCommand('#user-community-invitation-wrapper-' . $uid));
  }

  /**
   * AJAX callback to display the "Leave the community" confirmation popup.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The community to leave.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function leaveCommunityConfirmation(CommunityInterface $opigno_community): AjaxResponse {
    $response = new AjaxResponse();
    // Only community members can leave the community; the owner can't.
    if (!$opigno_community->isMember($this->currentUser)
      || (int) $this->currentUser->id() === $opigno_community->getOwnerId()
    ) {
      return $response->setStatusCode(404);
    }

    // Generate the confirmation popup.
    $confirmation = [
      '#theme' => 'opigno_community_leave_confirmation',
      '#confirm_url' => Url::fromRoute('opigno_social_community.leave_community', [
        'opigno_community' => $opigno_community->id(),
      ]),
    ];

    // Close all previously opened modals and show the confirmation message.
    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $confirmation));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));

    return $response;
  }

  /**
   * AJAX callback to leave the community.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The community to leave.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function leaveCommunity(CommunityInterface $opigno_community): AjaxResponse {
    $response = new AjaxResponse();
    $uid = (int) $this->currentUser->id();
    // Only community members can leave the community; the owner can't.
    if (!$opigno_community->isMember($this->currentUser) || $uid === $opigno_community->getOwnerId()) {
      return $response->setStatusCode(404);
    }

    // Delete the community member and close the popup.
    $opigno_community->deleteMember($uid);
    try {
      $opigno_community->save();
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
      return $response->setStatusCode(400, $this->t('The community member with ID @id can not be deleted.', [
        '@id' => $uid,
      ]));
    }

    // Redirect user to "Join communities" page.
    $redirect_url = Url::fromRoute('opigno_social_community.join_communities')->toString();
    $response->addCommand(new RedirectCommand($redirect_url));

    return $response;
  }

  /**
   * Redirects the user to the latest active community.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect to the latest active community page the current user is a
   *   member of. If there are no such communities, the user will be redirected
   *   to the "Join communities" page.
   */
  public function latestActiveCommunityPage(): RedirectResponse {
    $recent = $this->statsManager->getLatestActiveUserCommunities(1);

    if ($recent) {
      $latest_id = array_key_first($recent);
      $url = Url::fromRoute('entity.opigno_community.canonical', ['opigno_community' => $latest_id]);
    }
    else {
      $url = Url::fromRoute('opigno_social_community.join_communities');
    }

    return new RedirectResponse($url->toString());
  }

}
