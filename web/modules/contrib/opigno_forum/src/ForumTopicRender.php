<?php

namespace Drupal\opigno_forum;

/**
 * Forum topic render helper class.
 */
class ForumTopicRender {

  /**
   * Prepares variables for opigno_forum_last_topics_block template.
   */
  public function preprocessForumLastTopicsBlock(array &$variables): void {
    foreach ($variables["elements"]["topics"] as $index => $topic) {
      $variables["elements"]["topics"][$index] = [
        '#theme' => 'opigno_forum_last_topics_item',
        '#topic' => $topic,
      ];
    }
  }

  /**
   * Prepares variables for opigno_forum_last_topics_item template.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function preprocessForumLastTopicsItem(array &$variables): void {
    /** @var \Drupal\node\Entity\Node $topic */
    $topic = &$variables["topic"];
    $variables['name'] = $topic->label();
    $variables['link'] = $topic->toUrl()->toString();
    $variables['new_posts'] = $topic->new_replies;
  }

}
