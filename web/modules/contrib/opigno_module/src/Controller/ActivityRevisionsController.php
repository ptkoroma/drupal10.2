<?php

namespace Drupal\opigno_module\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\node\Controller\NodeViewController;
use Drupal\opigno_module\Entity\OpignoActivityInterface;
use Drupal\opigno_module\Entity\OpignoAnswerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class OpignoAnswerController.
 *
 *  Returns responses for Answer routes.
 *
 * @package Drupal\opigno_module\Controller
 */
class ActivityRevisionsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Preview activity revision.
   */
  public function previewActivityRevision($opigno_activity, $opigno_activity_revision) {
    $vids = $opigno_activity->revisionIds();

    if (!in_array($opigno_activity_revision, $vids)) {
      throw new NotFoundHttpException();
    }

    $activity = $this->entityTypeManager()->getStorage('opigno_activity')->loadRevision($opigno_activity_revision);
    $page = \Drupal::entityTypeManager()->getViewBuilder('opigno_activity')->view($activity, 'activity');
    return $page;
  }

  /**
   * Generates an overview table of older revisions of a Answer .
   *
   * @param \Drupal\opigno_module\Entity\OpignoActivity $opigno_activity
   *   A Answer  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function revisionOverview(OpignoActivityInterface $opigno_activity) {
    $account = $this->currentUser();
    $langcode = $opigno_activity->language()->getId();
    $langname = $opigno_activity->language()->getName();
    $languages = $opigno_activity->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $opigno_activity_storage = $this->entityTypeManager()
      ->getStorage('opigno_activity');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', [
      '@langname' => $langname,
      '%title' => $opigno_activity->label(),
    ]) : $this->t('Revisions for %title', ['%title' => $opigno_activity->label()]);
    $header = [$this->t('Revision'), $this->t('Name'), $this->t('Operations')];

    $revert_permission = $account->hasPermission('administer activity entities');
    $delete_permission = $account->hasPermission('administer activity entities');

    $rows = [];

    $vids = $opigno_activity->revisionIds();

    foreach ($vids as $vid) {
      /** @var \Drupal\opigno_module\Entity\OpignoAnswer $revision */
      $revision = $opigno_activity_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode)) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')
          ->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $opigno_activity->getRevisionId()) {
          $link = Link::fromTextAndUrl($date, new Url('entity.opigno_activity.revision-preview', [
            'opigno_activity' => $opigno_activity->id(),
            'opigno_activity_revision' => $vid]))->toString();
        }
        else {
          $link = $opigno_activity->toLink($date)->toString();
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')
                ->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        $row[] = ['data' => $revision->label()];

        if ($revision->isDefaultRevision()) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
                Url::fromRoute('entity.opigno_activity.translation_revert', [
                  'opigno_activity' => $opigno_activity->id(),
                  'opigno_activity_revision' => $vid,
                  'langcode' => $langcode,
                ]) :
                Url::fromRoute('entity.opigno_activity.revision_revert', [
                  'opigno_activity' => $opigno_activity->id(),
                  'opigno_activity_revision' => $vid,
                ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.opigno_activity.revision_delete', [
                'opigno_activity' => $opigno_activity->id(),
                'opigno_activity_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['opigno_activity_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
