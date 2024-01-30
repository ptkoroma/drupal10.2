<?php

namespace Drupal\opigno_social_community\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Entity\CommunityInvitationInterface;
use Drupal\opigno_social_community\Form\InviteMembersForm;
use Drupal\user\UserInterface;

/**
 * Defines the community connections controller.
 *
 * @package Drupal\opigno_social_community\Controller
 */
class CommunityInvitationController extends CommunityController {

  /**
   * The community invitation link ID prefix.
   */
  const INVITATION_LINK_PREFIX = 'opigno-community-invitation-link-';

  /**
   * Implements "Accept invitation" route callback.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInvitationInterface $invitation
   *   The invitation to be accepted.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function accept(CommunityInvitationInterface $invitation): AjaxResponse {
    return $this->updateInvitation($invitation, TRUE);
  }

  /**
   * Implements "Decline invitation" route callback.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInvitationInterface $invitation
   *   The invitation to be declined.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function decline(CommunityInvitationInterface $invitation): AjaxResponse {
    return $this->updateInvitation($invitation, FALSE);
  }

  /**
   * Updates the invitation (accept/decline option).
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInvitationInterface $invitation
   *   The invitation to be accepted or declined.
   * @param bool $accepted
   *   If TRUE, the invitation will be accepted, otherwise declined.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  private function updateInvitation(CommunityInvitationInterface $invitation, bool $accepted): AjaxResponse {
    $response = new AjaxResponse();
    $is_join_request = $invitation->isJoinRequest();
    $community = $invitation->getCommunity();
    $community_title = $community->getTitle();
    $cid = $invitation->getCommunityId();
    $invitee = $invitation->getInviteeId();

    try {
      if ($accepted) {
        $invitor = $invitation->getOwnerId();
        $invitee_name = $invitation->getInvitee()->getDisplayName();
        $invitee_url = Url::fromRoute('entity.user.canonical', ['user' => $invitee])->toString();

        // Delete all user's invitations to the same community.
        $invitations = $this->invitationStorage->getQuery()
          ->condition('invitee', $invitee)
          ->condition('community', $cid)
          ->execute();
        if ($invitations) {
          $invitations = $this->invitationStorage->loadMultiple($invitations);
          $this->invitationStorage->delete($invitations);
        }

        // When the invitee accepts the invitation to the community, they should
        // be added as a member and the notification should be sent to the
        // invitor.
        $msg = $this->t('@user accepted your invitation to the community "@community"', [
          '@user' => $invitee_name,
          '@community' => $community_title,
        ]);

        try {
          $community->addMember($invitee)->save();
          if (!$is_join_request) {
            opigno_set_message($invitor, $msg, $invitee_url);
          }
        }
        catch (EntityStorageException $e) {
          watchdog_exception('opigno_social_community_exception', $e);
        }

        // Send the notification to the invitee in case if the join request has
        // been approved.
        if ($is_join_request && $community instanceof CommunityInterface) {
          $url = Url::fromRoute('entity.opigno_community.canonical', ['opigno_community' => $cid])->toString();
          $msg = $this->t('Your request to join the community "@community" has been approved', [
            '@community' => $community_title,
          ]);
          opigno_set_message($invitee, $msg, $url);
        }
      }
      else {
        $this->invitationStorage->delete([$invitation]);
        // Send the notification to the invitee in case if the join request has
        // been declined.
        if ($is_join_request && $community instanceof CommunityInterface) {
          $url = Url::fromRoute('entity.opigno_community.canonical', ['opigno_community' => $cid])->toString();
          $msg = $this->t('Your request to join the community "@community" has been denied', [
            '@community' => $community->getTitle(),
          ]);
          opigno_set_message($invitee, $msg, $url);
        }
      }
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
      $response->setStatusCode(400, $this->t('An error occurred, the invitation can not be updated.'));
      return $response;
    }

    $response->addCommand(new RemoveCommand('#opigno-community-invitation-' . $cid));
    $response->addCommand(new RemoveCommand('#user-community-invitation-wrapper-' . $invitee));

    return $response;
  }

  /**
   * AJAX callback to delete the community invitation.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInvitationInterface $invitation
   *   The invitation to be deleted.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   AJAX response to delete the given community invitation.
   */
  public function deleteInvitation(CommunityInvitationInterface $invitation): AjaxResponse {
    $response = new AjaxResponse();
    $invitee = $invitation->getInviteeId();
    $community = $invitation->getCommunity();
    $cid = $community->id();
    $html_id = static::generateInvitationLinkHtmlId($invitee, $cid);
    try {
      $invitations = $community->getUserPendingInvitations($invitee);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
      return $response->setStatusCode(400, $this->t('An error occurred, the invitations can not be loaded.'));
    }

    try {
      if ($invitations) {
        $this->invitationStorage->delete($invitations);
      }
    }
    catch (EntityStorageException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
      return $response->setStatusCode(400, $this->t('An error occurred, the invitation can not be deleted.'));
    }

    // Replace the button with "Invite" link.
    $link = Link::createFromRoute($this->t('Invite'),
      'opigno_social_community.invite_user',
      ['user' => $invitee, 'opigno_community' => $cid],
      [
        'attributes' => [
          'id' => $html_id,
          'class' => ['use-ajax', 'btn-connection', 'invite'],
        ],
      ]
    )->toRenderable();

    return $response->addCommand(new ReplaceCommand('#' . $html_id, $link));
  }

  /**
   * AJAX callback to invite the user to the community.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to be invited.
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The community to invite the user to.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response to create the invitation.
   */
  public function inviteUser(UserInterface $user, CommunityInterface $opigno_community): AjaxResponse {
    $response = new AjaxResponse();
    $cid = (int) $opigno_community->id();
    $uid = (int) $user->id();

    try {
      $invitation = $opigno_community->inviteMember($uid, (int) $this->currentUser->id(), TRUE);
    }
    catch (EntityStorageException | PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
      return $response->setStatusCode(400, $this->t('An error occurred, the invitation can not be created'));
    }

    if (!$invitation instanceof CommunityInvitationInterface) {
      return $response->setStatusCode(400, $this->t('An error occurred, the invitation was not created'));
    }

    // Replace the button with "Invited" link.
    $html_id = static::generateInvitationLinkHtmlId($uid, $cid, TRUE);
    $link = static::generateDeleteInvitationLink($invitation);

    return $response->addCommand(new ReplaceCommand($html_id, $link));
  }

  /**
   * Generates the render array to display the "Delete invitation" link.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInvitationInterface $invitation
   *   The community invitation to be deleted.
   *
   * @return array
   *   The render array to display the "Delete invitation" link.
   */
  public static function generateDeleteInvitationLink(CommunityInvitationInterface $invitation): array {
    $params = ['invitation' => $invitation->id()];
    $options = [
      'attributes' => [
        'id' => static::generateInvitationLinkHtmlId($invitation->getInviteeId(), $invitation->getCommunityId()),
        'class' => ['use-ajax', 'btn-connection', 'invited'],
      ],
    ];
    $url = Url::fromRoute('opigno_social_community.delete_invitation', $params, $options);
    $title = Markup::create(t('Invited') . '<i class="fi fi-rr-cross-small"></i>');

    return $url->access() ? Link::fromTextAndUrl($title, $url)->toRenderable() : [];
  }

  /**
   * Generates the invitation link html ID.
   *
   * @param int $uid
   *   The user ID to generate the link for.
   * @param int $cid
   *   The community ID to generate the link for.
   * @param bool $hash
   *   Whether the leading hash character needed or not.
   *
   * @return string
   *   The invitation link html ID.
   */
  private static function generateInvitationLinkHtmlId(int $uid, int $cid, bool $hash = FALSE): string {
    $html_id = static::INVITATION_LINK_PREFIX . $cid . '-' . $uid;
    return $hash ? '#' . $html_id : $html_id;
  }

  /**
   * Opens the "Invite members" form.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $opigno_community
   *   The community to invite members to.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function openInvitationForm(CommunityInterface $opigno_community): AjaxResponse {
    $build = [
      '#theme' => 'opigno_community_modal',
      '#title' => $this->t('Invite members'),
      '#body' => $this->formBuilder->getForm(InviteMembersForm::class, $opigno_community),
      '#classes' => ['community-modal-actions'],
    ];

    // Close all previously opened modals.
    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', $build));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));

    return $response;
  }

}
