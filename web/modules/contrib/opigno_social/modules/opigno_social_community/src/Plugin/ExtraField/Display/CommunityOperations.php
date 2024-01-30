<?php

namespace Drupal\opigno_social_community\Plugin\ExtraField\Display;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the extra field to show community operations available for the user.
 *
 * @ExtraFieldDisplay(
 *   id = "community_operations",
 *   label = @Translation("Community operations"),
 *   bundles = {
 *     "opigno_community.*"
 *   },
 * )
 */
class CommunityOperations extends CommunityExtraFieldBase implements ContainerFactoryPluginInterface {

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
    $uid = (int) $this->account->id();
    if (!$entity instanceof CommunityInterface || !$entity->isMember($uid)) {
      return $this->emptyField();
    }

    $links = [];
    $link_attr = $ajax_attr = [
      'attributes' => ['class' => ['dropdown-item-text']],
    ];
    $ajax_attr['attributes']['class'][] = 'use-ajax';
    $options = ['opigno_community' => $entity->id()];

    // "Edit community" link.
    if ($entity->access('update', $this->account)) {
      $links[] = Link::createFromRoute($this->t('Edit'),
        'opigno_social_community.ajax_edit_community_form',
        $options,
        $ajax_attr
      );
    }

    // "Members" link.
    if ($entity->access('view_members')) {
      $links[] = Link::createFromRoute($this->t('Members'), 'view.communities.members', $options, $link_attr);
    }

    // All members except for the owner can leave the community.
    if ($uid !== $entity->getOwnerId()) {
      $links[] = Link::createFromRoute($this->t('Leave the community'),
        'opigno_social_community.leave_community_confirmation',
        $options,
        $ajax_attr
      );
    }

    if ($entity->access('delete')) {
      $links[] = Link::createFromRoute($this->t('Delete the community'),
        'opigno_social_community.ajax_delete_community_form',
        $options,
        $ajax_attr
      );
    }

    return [
      '#theme' => 'opigno_community_links_dropdown',
      '#links' => $links,
      '#cache' => $this->getCache($entity),
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
      'tags' => Cache::mergeTags($community->getCacheTags(), ['user:' . $this->account->id()]),
      'contexts' => Cache::mergeContexts($community->getCacheContexts(), ['user']),
    ];
  }

}
