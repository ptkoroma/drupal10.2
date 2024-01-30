<?php

namespace Drupal\opigno_social_community\Plugin\ExtraField\Display;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the extra field to show the user's communities list.
 *
 * @ExtraFieldDisplay(
 *   id = "available_communities_list",
 *   label = @Translation("Available communitites list"),
 *   bundles = {
 *     "opigno_community.*"
 *   },
 * )
 */
class AvailableCommunitiesList extends CommunityExtraFieldBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The community entity view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $viewBuilder;

  /**
   * The community entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $storage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    AccountInterface $account,
    EntityTypeManagerInterface $entity_type_manager,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->account = $account;
    $this->viewBuilder = $entity_type_manager->getViewBuilder('opigno_community');
    $this->storage = $entity_type_manager->getStorage('opigno_community');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    if (!$entity instanceof CommunityInterface) {
      return $this->emptyField();
    }

    $uid = $this->account->id();
    $own = $this->storage->getQuery()
      ->condition('uid', $uid)
      ->sort('title')
      ->execute();

    $followed = $this->storage->getQuery()
      ->condition('uid', $uid, '!=')
      ->condition('members', $uid)
      ->accessCheck()
      ->sort('title')
      ->execute();

    return [
      '#theme' => 'opigno_community_available_communities',
      '#managed' => $this->renderList($own),
      '#followed' => $this->renderList($followed),
      '#join_btn' => Link::createFromRoute($this->t('Join communities'),
        'opigno_social_community.join_communities',
        [],
        ['attributes' => ['class' => ['btn', 'btn-rounded']]],
      ),
      '#cache' => $this->getCache($entity),
    ];
  }

  /**
   * Generated the list of rendered community entities.
   *
   * @param array $ids
   *   The list of community IDs to be loaded.
   *
   * @return array
   *   The list of rendered community entities.
   */
  private function renderList(array $ids): array {
    $communities = $ids ? $this->storage->loadMultiple($ids) : [];
    $list = [];
    if ($communities) {
      foreach ($communities as $community) {
        $list[] = $this->viewBuilder->view($community, 'link_item');
      }
    }

    return $list;
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
        ['user:' . $this->account->id(), 'opigno_community_list'],
      ),
      'contexts' => Cache::mergeContexts($community->getCacheContexts(), [
        'user',
        'url',
      ]),
    ];
  }

}
