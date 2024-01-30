<?php

namespace Drupal\opigno_social_community\Plugin\ExtraField\Display;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\opigno_social\Services\UserConnectionManager;
use Drupal\opigno_social_community\Controller\CommunityInvitationController;
use Drupal\opigno_social_community\Entity\CommunityInterface;
use Drupal\opigno_social_community\Entity\CommunityInvitationInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the extra field to show community member action buttons.
 *
 * @ExtraFieldDisplay(
 *   id = "community_member_actions",
 *   label = @Translation("Community member actions"),
 *   bundles = {
 *     "user.*"
 *   },
 * )
 */
class MemberActions extends CommunityExtraFieldBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The Opigno community entity from url.
   *
   * @var \Drupal\opigno_social_community\Entity\CommunityInterface|null
   */
  protected ?CommunityInterface $community;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The user connection manager service.
   *
   * @var \Drupal\opigno_social\Services\UserConnectionManager
   */
  protected UserConnectionManager $connectionManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    AccountInterface $account,
    UserConnectionManager $connection_manager,
    RequestStack $request,
    EntityTypeManagerInterface $entity_type_manager,
    ...$default
  ) {
    parent::__construct(...$default);
    $this->account = $account;
    $this->connectionManager = $connection_manager;

    // Get the community ID from the URL.
    $cid = $request->getCurrentRequest()->get('opigno_community');
    $community = $cid ? $entity_type_manager->getStorage('opigno_community')->load($cid) : NULL;
    $this->community = $community instanceof CommunityInterface ? $community : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_user'),
      $container->get('opigno_user_connection.manager'),
      $container->get('request_stack'),
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
    $uid = (int) $entity->id();
    if (!$entity instanceof UserInterface
      || !$this->community instanceof CommunityInterface
      || $uid === (int) $this->account->id()
    ) {
      return $this->emptyField();
    }

    $actions = [];
    $cid = $this->community->id();
    // If the user is a community member, there should be a dropdown with the
    // possible actions.
    if ($this->community->isMember($uid)) {
      $params = [
        'user' => $uid,
        'opigno_community' => $cid,
      ];
      $options = [
        'attributes' => [
          'class' => ['use-ajax', 'dropdown-item-text'],
        ],
      ];
      $delete_url = Url::fromRoute('opigno_social_community.delete_member', $params, $options);
      if ($delete_url->access($this->account)) {
        $actions[] = Link::fromTextAndUrl($this->t('Remove from the community'), $delete_url);
      }

      $msg_url = $this->connectionManager->getMessageUrl($uid);
      $msg_url->setOption('attributes', ['class' => ['dropdown-item-text']]);
      if ($msg_url->access($this->account)) {
        $actions[] = Link::fromTextAndUrl($this->t('Send a message'), $msg_url);
      }

      return [
        '#theme' => 'opigno_community_links_dropdown',
        '#links' => $actions,
        '#cache' => $this->getCache($entity),
      ];
    }

    // Display the "Invited" marker for the already invited users.
    $invitation = $this->community->getUserPendingInvitations($uid);
    $invitation = reset($invitation);
    if (!$invitation instanceof CommunityInvitationInterface) {
      return $this->emptyField();
    }

    if ($invitation->isJoinRequest()) {
      // Generate accent/deny links for join requests.
      $links = [];
      $routes = [
        'opigno_social_community.accept_invitation' => [
          'title' => $this->t('Accept'),
          'class' => 'accept',
        ],
        'opigno_social_community.decline_invitation' => [
          'title' => $this->t('Deny'),
          'class' => 'decline',
        ],
      ];
      $params = ['invitation' => $invitation->id()];

      foreach ($routes as $route => $data) {
        $attributes = [
          'attributes' => [
            'class' => ['use-ajax', 'btn-connection', $data['class']],
          ],
        ];
        $url = Url::fromRoute($route, $params, $attributes);

        if ($url->access($this->account)) {
          $links[] = Link::fromTextAndUrl($data['title'], $url)->toRenderable();
        }
      }
    }
    else {
      // Generate the cancel invitation link for invitations.
      $links = [CommunityInvitationController::generateDeleteInvitationLink($invitation)];
    }

    return [
      [$links],
      '#cache' => $this->getCache($entity),
      '#attached' => [
        'library' => ['core/drupal.ajax'],
      ],
    ];
  }

  /**
   * Gets the extra field cache tags and contexts.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity the field belongs to.
   *
   * @return array
   *   The field cache tags and contexts.
   */
  private function getCache(UserInterface $user): array {
    return [
      'tags' => Cache::mergeTags($user->getCacheTags(),
        $this->community->getCacheTags(),
        ['user:' . $this->account->id(), 'opigno_community_invitation_list'],
      ),
      'contexts' => Cache::mergeContexts($user->getCacheContexts(),
        $this->community->getCacheContexts(),
        ['user', 'url']
      ),
    ];
  }

}
