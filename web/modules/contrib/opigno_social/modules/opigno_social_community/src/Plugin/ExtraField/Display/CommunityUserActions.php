<?php

namespace Drupal\opigno_social_community\Plugin\ExtraField\Display;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\opigno_social_community\Controller\CommunityController;
use Drupal\opigno_social_community\Entity\Community;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the extra field to show community user action buttons.
 *
 * @ExtraFieldDisplay(
 *   id = "community_user_actions",
 *   label = @Translation("Community user actions"),
 *   bundles = {
 *     "opigno_community.*"
 *   },
 * )
 */
class CommunityUserActions extends CommunityInvitationActions {

  /**
   * The community invitation storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $invitationStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ...$default) {
    parent::__construct(...$default);
    $this->invitationStorage = $entity_type_manager->getStorage('opigno_community_invitation');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
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

    // Display the "See" link for the member, "Join" for public communities.
    return $entity->isMember($this->account) ? $this->getMemberLink($entity) : $this->getJoinLink($entity);
  }

  /**
   * Display the member link.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $community
   *   The community to generate the link for.
   *
   * @return array
   *   The render array to display the link.
   */
  protected function getMemberLink(CommunityInterface $community): array {
    // Display "See" link for the member.
    return Link::createFromRoute($this->t('See'),
      'entity.opigno_community.canonical',
      ['opigno_community' => $community->id()],
      [
        'attributes' => [
          'class' => ['btn', 'btn-rounded', 'btn-bg'],
        ],
      ]
    )->toRenderable();
  }

  /**
   * Gets the "Join community" link for the public communities.
   *
   * @param \Drupal\opigno_social_community\Entity\CommunityInterface $community
   *   The community to generate the link for.
   *
   * @return array
   *   The render array to display the link.
   */
  protected function getJoinLink(CommunityInterface $community): array {
    $visibility = $community->getVisibility();
    if ($visibility === Community::VISIBILITY_PRIVATE) {
      return $this->emptyField();
    }

    $cid = (int) $community->id();
    $attributes = [
      'attributes' => [
        'class' => ['btn', 'btn-rounded', 'btn-bg', 'use-ajax'],
        'id' => CommunityController::JOIN_LINK_PREFIX . $cid,
      ],
    ];

    // Display the "Join" link for the public communities.
    if ($visibility === Community::VISIBILITY_PUBLIC) {
      return Link::createFromRoute($this->t('Join'),
        'opigno_social_community.join_community',
        ['opigno_community' => $cid],
        $attributes
      )->toRenderable();
    }

    // Display the "Join request" link for the restricted communities.
    // If the request is already sent, display the "Pending approval" message.
    $is_requested = $this->invitationStorage->getQuery()
      ->condition('invitee', $this->account->id())
      ->condition('community', $cid)
      ->condition('is_join_request', TRUE)
      ->execute();

    if ($is_requested) {
      $result = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Pending approval'),
        '#attributes' => [
          'class' => ['btn', 'btn-rounded', 'inactive'],
        ],
      ];
    }
    else {
      $result = Link::createFromRoute($this->t('Request to join'),
        'opigno_social_community.join_request',
        ['opigno_community' => $cid],
        $attributes
      )->toRenderable();
    }

    return $result + [
      '#cache' => $this->getCache($community),
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
   *
   * @return array
   *   The field cache tags and contexts.
   */
  private function getCache(CommunityInterface $community): array {
    return [
      'tags' => Cache::mergeTags($community->getCacheTags(),
        ['user:' . $this->account->id(), 'opigno_community_invitation_list'],
      ),
      'contexts' => Cache::mergeContexts($community->getCacheContexts(), ['user']),
    ];
  }

}
