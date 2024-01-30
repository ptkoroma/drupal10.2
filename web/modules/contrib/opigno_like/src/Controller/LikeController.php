<?php

namespace Drupal\opigno_like\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\opigno_like\Entity\OpignoLike;
use Drupal\opigno_like\Entity\OpignoLikeInterface;
use Drupal\opigno_like\Services\OpignoLikeManager;
use Drupal\opigno_social\Entity\OpignoPostInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Opigno like entity actions controller.
 *
 * @package Drupal\opigno_like\Controller
 */
class LikeController extends ControllerBase {

  /**
   * The Opigno like manager service.
   *
   * @var \Drupal\opigno_like\Services\OpignoLikeManager
   */
  protected $likeManager;

  /**
   * Users view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $userViewBuilder;

  /**
   * LikeController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\opigno_like\Services\OpignoLikeManager $like_manager
   *   The Opigno like entity manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, OpignoLikeManager $like_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->userViewBuilder = $entity_type_manager->getViewBuilder('user');
    $this->likeManager = $like_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('opigno_like.manager')
    );
  }

  /**
   * Like/dislike entity.
   *
   * @param int $eid
   *   The entity ID to like/dislike.
   * @param string $type
   *   The entity type.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function likeAction(int $eid, string $type = 'opigno_post'): AjaxResponse {
    if (!$eid || !$type) {
      return new AjaxResponse(NULL, 400);
    }

    $like = $this->likeManager->getUserLike($eid, $type);
    $response = new AjaxResponse();
    // Remove the like if it already exists.
    if ($like instanceof OpignoLikeInterface) {
      try {
        $like->delete();
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_like_exception', $e);
        return new AjaxResponse(NULL, 400);
      }
      $response->addCommand(new InvokeCommand("#opigno-like-$eid", 'removeClass', ['active']));
    }
    else {
      // Create the 'Like' entity.
      $like = OpignoLike::create([
        'entity_id' => $eid,
        'type' => $type,
      ]);
      try {
        $like->save();
      }
      catch (EntityStorageException $e) {
        watchdog_exception('opigno_like_exception', $e);
        return new AjaxResponse(NULL, 400);
      }
      $response->addCommand(new InvokeCommand("#opigno-like-$eid", 'addClass', ['active']));
    }

    // Invalidate an appropriate cache tags.
    $entity = NULL;
    try {
      $entity = $this->entityTypeManager->getStorage($type)->load($eid);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_like_exception', $e);
      $response->setStatusCode(400);
      return $response;
    }

    $tags = $entity instanceof EntityInterface ? $entity->getCacheTagsToInvalidate() : [];
    $tags = array_merge([OpignoLikeManager::ENTITY_LIKERS_CACHE_PREFIX . $type . $eid], $tags);
    Cache::invalidateTags($tags);
    // Update the likes count.
    $counter = $this->likeManager->getLikersCountLink($eid, $type);
    $response->addCommand(new ReplaceCommand("#opigno-likes-count-$type-$eid", $counter));

    return $response;
  }

  /**
   * Close the popup with the list of users who liked the entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function closePopup(): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new InvokeCommand('.modal', 'modal', ['hide']));

    return $response;
  }

  /**
   * Get the list of users who had liked the given entity.
   *
   * @param int $eid
   *   The entity ID to get likers for.
   * @param string $type
   *   The entity type.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public function getLikers(int $eid, string $type = 'opigno_post'): AjaxResponse {
    $likers = $this->likeManager->getEntityLikers($eid, $type);
    if (!$likers) {
      return new AjaxResponse(NULL, 400);
    }

    $response = new AjaxResponse();
    $popup = $this->renderLikersList($likers, $eid, $type);
    $response->addCommand(new RemoveCommand('.modal-ajax'));
    $response->addCommand(new AppendCommand('body', [$popup]));
    $response->addCommand(new InvokeCommand('.modal-ajax', 'modal', ['show']));

    return $response;
  }

  /**
   * Prepare the render array to display the list of people who liked entity.
   *
   * @param array $likers
   *   The list of user IDs who liked the entity.
   * @param int $eid
   *   The entity ID.
   * @param string $type
   *   The entity type.
   *
   * @return array
   *   The render array to display the list of people who liked the entity.
   */
  private function renderLikersList(array $likers, int $eid, string $type): array {
    // Load the list of people who had liked the given entity.
    try {
      $users = $this->entityTypeManager->getStorage('user')->loadMultiple($likers);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_like_exception', $e);
      return [];
    }

    $people = [];
    foreach ($users as $user) {
      if ($user instanceof UserInterface) {
        $people[] = $this->userViewBuilder->view($user, 'list_item');
      }
    }

    // Prepare the popup header text.
    $count = count($likers);
    $entity_type = 'content';
    if ($type === 'opigno_post') {
      try {
        $post = $this->entityTypeManager->getStorage($type)->load($eid);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        watchdog_exception('opigno_like_exception', $e);
        $post = NULL;
      }
      if ($post instanceof OpignoPostInterface) {
        $entity_type = $post->isComment() ? 'comment' : 'post';
      }
    }

    // Generate the "close" link.
    $options = [
      'attributes' => [
        'class' => ['close', 'use-ajax'],
        'type' => 'button',
      ],
    ];
    $title = Markup::create('<i class="fi fi-rr-arrow-left"></i>');

    return [
      '#theme' => 'opigno_entity_likers_popup',
      '#title' => $this->formatPlural($count, '1 person likes this @type', '@count people like this @type', [
        '@type' => $entity_type,
      ]),
      '#likers' => $people,
      '#close_link' => Link::createFromRoute($title, 'opigno_learning_path.close_modal', [], $options),
      '#cache' => [
        '#tags' => [OpignoLikeManager::ENTITY_LIKERS_CACHE_PREFIX . $type . $eid],
      ],
    ];
  }

}
