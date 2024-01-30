<?php

namespace Drupal\opigno_social_community\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\opigno_social\Controller\PostsController;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Entity\CommunityPost;
use Drupal\opigno_social_community\Entity\CommunityPostInterface;
use Drupal\opigno_social_community\Form\CreateShareCommunityPostForm;
use Drupal\opigno_social_community\Services\CommunityPostsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Overrides the Opigno posts controller to use for communities.
 *
 * @package Drupal\opigno_social_community\Controller
 */
class CommunityPostsController extends PostsController {

  /**
   * {@inheritdoc}
   */
  protected static string $entityType = 'opigno_community_post';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    AccountInterface $account,
    CommunityPostsManager $community_posts_manager,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->currentUser = $account;
    $this->postsManager = $community_posts_manager;

    try {
      $this->storage = $this->entityTypeManager->getStorage('opigno_community_post');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_community_exception', $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('opigno_social_community.posts_manager'),
      $container->get('opigno_posts.manager'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('opigno_like.manager'),
      $container->get('form_builder'),
      $container->get('current_route_match'),
      $container->get('router'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected static function getSharingContentRouteName(): string {
    return 'opigno_social_community.share_post_content';
  }

  /**
   * {@inheritdoc}
   */
  protected static function getFeedRoute(): string {
    return 'entity.opigno_community.canonical';
  }

  /**
   * {@inheritdoc}
   */
  protected static function getSharePostContentFormName(): string {
    return CreateShareCommunityPostForm::class;
  }

  /**
   * {@inheritdoc}
   */
  protected function createPostFromValues(array $values, Request $request): ?EntityInterface {
    $community = $this->getMainRoutePropertyFromRequest($request, 'opigno_community');
    if (!$community instanceof CommunityInterface) {
      return NULL;
    }

    $values['community'] = $community->id();
    return parent::createPostFromValues($values, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function createPost(Request $request): AjaxResponse {
    $community = $this->getMainRoutePropertyFromRequest($request, 'opigno_community');
    if (!$community instanceof CommunityInterface || !$community->isMember($this->currentUser)) {
      $response = new AjaxResponse();
      return $response->setStatusCode(400, $this->t('Only community members can create posts.'));
    }

    return parent::createPost($request);
  }

  /**
   * {@inheritdoc}
   */
  public function sharePostContent(Request $request): AjaxResponse {
    $community = $this->getMainRoutePropertyFromRequest($request, 'opigno_community');
    if (!$community instanceof CommunityInterface || !$community->isMember($this->currentUser)) {
      $response = new AjaxResponse();
      return $response->setStatusCode(400, $this->t('Only community members can create posts.'));
    }

    return parent::sharePostContent($request);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostCommentIds(int $pid, int $from, int $amount): array {
    return $this->postsManager->getPostComments($pid, $amount, $from);
  }

  /**
   * {@inheritdoc}
   */
  public function pinPost(Request $request, OpignoPostInterface $post): AjaxResponse {
    if ($post instanceof CommunityPost) {
      $community = $post->getCommunity();
      if (!$community instanceof CommunityInterface || !$community->access('pin_post')) {
        return new AjaxResponse(NULL, 400);
      }
    }

    return parent::pinPost($request, $post);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteRedirectUrl(OpignoPostInterface $post): Url {
    if ($post instanceof CommunityPostInterface) {
      return Url::fromRoute('entity.opigno_community.canonical', ['opigno_community' => $post->getCommunityId()]);
    }

    return parent::getDeleteRedirectUrl($post);
  }

}
