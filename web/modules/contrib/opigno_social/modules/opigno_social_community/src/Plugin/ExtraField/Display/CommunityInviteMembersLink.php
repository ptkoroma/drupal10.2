<?php

namespace Drupal\opigno_social_community\Plugin\ExtraField\Display;

use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\opigno_social_community\Entity\CommunityInterface;

/**
 * Defines the extra field to show community invitation link.
 *
 * @ExtraFieldDisplay(
 *   id = "community_invite_members_link",
 *   label = @Translation("Community invite members link"),
 *   bundles = {
 *     "opigno_community.*"
 *   },
 * )
 */
class CommunityInviteMembersLink extends CommunityUserActions implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected function getMemberLink(CommunityInterface $community): array {
    // Display "Invite link" if the user is permitted to invite others.
    if (!$community->access('invite_member')) {
      return $this->emptyField();
    }

    $title = Markup::create('<i class="fi fi-rr-user-add"></i>' . $this->t('Invite'));
    return Link::createFromRoute($title, 'opigno_social_community.invitation_form',
      ['opigno_community' => $community->id()],
      [
        'attributes' => ['class' => ['use-ajax']],
      ]
    )->toRenderable();
  }

}
