<?php

namespace Drupal\opigno_statistics\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Url;
use Drupal\opigno_statistics\Services\UserStatisticsManager;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * User achievements page controller.
 *
 * @package Drupal\opigno_statistics\Controller
 */
class UserAchievements extends ControllerBase {

  /**
   * Completed trainings tab machine name.
   */
  const TRANINGS_TAB = 'trainings';

  /**
   * Certificates tab machine name.
   */
  const CERTIFICATES_TAB = 'certificates';

  /**
   * Badges tab machine name.
   */
  const BADGES_TAB = 'badges';

  /**
   * Skills tab machine name.
   */
  const SKILLS_TAB = 'skills';

  /**
   * Opigno user statistics manager service.
   *
   * @var \Drupal\opigno_statistics\Services\UserStatisticsManager
   */
  protected $statsManager;

  /**
   * The CSRF token generator service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * UserAchievements constructor.
   *
   * @param \Drupal\opigno_statistics\Services\UserStatisticsManager $stats_manager
   *   Opigno user statistics manager service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator service.
   */
  public function __construct(UserStatisticsManager $stats_manager, CsrfTokenGenerator $csrf_token) {
    $this->statsManager = $stats_manager;
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('opigno_statistics.user_stats_manager'),
      $container->get('csrf_token')
    );
  }

  /**
   * Build the user achievements page content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\user\UserInterface $user
   *   The user to get achievements of.
   *
   * @return array
   *   The render array to display the achievements page content.
   */
  public function achievementsPage(Request $request, UserInterface $user): array {
    $uid = (int) $user->id();
    $active_tab = $request->get('tab', 'trainings');

    // Build tabs section structure.
    $tabs = [];
    $names = [
      static::TRANINGS_TAB,
      static::CERTIFICATES_TAB,
      static::BADGES_TAB,
      static::SKILLS_TAB,
    ];

    foreach ($names as $name) {
      $params = [
        'user' => $uid,
        'tab' => $name,
      ];
      $url = Url::fromRoute('opigno_statistics.switch_achievement_tabs', $params);
      $internal = $url->getInternalPath();
      $url->setOption('query', ['token' => $this->csrfToken->get($internal)]);

      $tabs[$name] = [
        'title' => $this->getTabTitle($name),
        'url' => $url->toString(),
      ];
    }

    // Add the sharable post url if socials enabled.
    $attached = [];
    if ($this->statsManager->isSocialsEnabled) {
      $share_url = Url::fromRoute('opigno_social.share_post_content')->toString();
      $attached = [
        'library' => ['opigno_social/post_sharing'],
        'drupalSettings' => [
          'opignoSocial' => [
            'shareContentUrl' => $share_url instanceof GeneratedUrl ? $share_url->getGeneratedUrl() : $share_url,
          ],
        ],
      ];
    }

    return [
      '#theme' => 'opigno_user_achievements_page',
      '#tabs' => $tabs,
      '#active' => $active_tab,
      '#content' => $this->getTabContent($uid, $active_tab),
      '#attached' => $attached,
    ];
  }

  /**
   * Get the tab title.
   *
   * @param string $tab
   *   The tab machine name to get the title for.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The tab title.
   */
  private function getTabTitle(string $tab) {
    $titles = [
      static::TRANINGS_TAB => $this->t('Trainings completed'),
      static::CERTIFICATES_TAB => $this->t('Certificates'),
      static::BADGES_TAB => $this->t('Badges'),
      static::SKILLS_TAB => $this->t('Skills'),
    ];

    return $titles[$tab] ?? '';
  }

  /**
   * Build the content depending on the given tab name.
   *
   * @param int $uid
   *   The user ID to get the tab content for.
   * @param string $tab
   *   The tab machine name to get the content for.
   *
   * @return array
   *   The render array to display the tab content.
   */
  private function getTabContent(int $uid, string $tab): array {
    switch ($tab) {
      case static::CERTIFICATES_TAB:
        $content = $this->statsManager->buildCertificatesList($uid);
        break;

      case static::BADGES_TAB:
        $content = $this->statsManager->buildBadgesList($uid);
        break;

      case static::SKILLS_TAB:
        $content = $this->statsManager->buildSkillsList($uid);
        break;

      default:
        $content = $this->statsManager->buildTrainingsList($uid, TRUE);
    }

    return $content;
  }

  /**
   * Ajax callback to swith tabs content.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to render the content for.
   * @param string $tab
   *   The tab machine name to be displayed.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response to display the tab content.
   */
  public function switchTabs(UserInterface $user, string $tab): AjaxResponse {
    $uid = (int) $user->id();
    $content = $this->getTabContent($uid, $tab);
    $title = $this->getTabTitle($tab);

    // Update the title, the content and the active tab.
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#opigno-achievements-content h2', $title));
    $response->addCommand(new HtmlCommand('#opigno-achievements-content .tab-content', $content));
    $response->addCommand(new InvokeCommand('.achievements-tabs a', 'removeClass', ['active']));
    $response->addCommand(new InvokeCommand(".achievements-tabs a.$tab", 'addClass', ['active']));

    return $response;
  }

}
