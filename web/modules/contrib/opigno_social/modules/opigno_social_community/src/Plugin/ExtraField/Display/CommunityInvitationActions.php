<?php

namespace Drupal\opigno_social_community\Plugin\ExtraField\Display;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Entity\CommunityInvitationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the extra field to show community invitation user action buttons.
 *
 * @ExtraFieldDisplay(
 *   id = "community_invitation_user_actions",
 *   label = @Translation("Community invitation user actions"),
 *   bundles = {
 *     "opigno_community.*"
 *   },
 * )
 */
class CommunityInvitationActions extends CommunityExtraFieldBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

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
  public function viewElements(ContentEntityInterface $entity) {
    if (!$entity instanceof CommunityInterface || $this->account->isAnonymous()) {
      return $this->emptyField();
    }

    $classes = $main_classes = ['btn', 'btn-rounded', 'use-ajax'];
    $main_classes[] = 'btn-bg';

    // Check if the user has a pending invitation to the community.
    $pending_invitations = $entity->getUserPendingInvitations($this->account->id());
    $invitation = reset($pending_invitations);

    if (!$invitation instanceof CommunityInvitationInterface) {
      return $this->isEmpty();
    }

    $iid = $invitation->id();

    return [
      [
        Link::createFromRoute($this->t('Accept'), 'opigno_social_community.accept_invitation',
          ['invitation' => $iid],
          ['attributes' => ['class' => $main_classes]]
        )->toRenderable(),
        Link::createFromRoute($this->t('Decline'), 'opigno_social_community.decline_invitation',
          ['invitation' => $iid],
          ['attributes' => ['class' => $classes]]
        )->toRenderable(),
      ],
      '#cache' => $this->getCache($entity, $invitation),
      '#attached' => [
        'library' => ['core/drupal.ajax'],
      ],
    ];
  }

  /**
   * Gets the extra field cache tags and contexts.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $community
   *   The community entity the field belongs to.
   * @param \Drupal\opigno_social_community\Entity\CommunityInvitationInterface $invitation
   *   The invitation entity.
   *
   * @return array
   *   The field cache tags and contexts.
   */
  private function getCache(CommunityInterface $community, CommunityInvitationInterface $invitation): array {
    return [
      'tags' => Cache::mergeTags($community->getCacheTags(),
        $invitation->getCacheTags(),
        ['user:' . $this->account->id(), 'opigno_community_invitation_list'],
      ),
      'contexts' => Cache::mergeContexts($community->getCacheContexts(),
        $invitation->getCacheContexts(),
        ['user', 'url'],
      ),
    ];
  }

}
