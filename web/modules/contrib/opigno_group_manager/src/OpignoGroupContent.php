<?php

namespace Drupal\opigno_group_manager;

use Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent;
use Drupal\opigno_module\Entity\OpignoModuleInterface;

/**
 * Class OpignoGroupContent.
 */
class OpignoGroupContent {
  private $groupContentTypeId;
  private $entityType;
  private $entityId;
  private $title;
  private $imageUrl;
  private $imageAlt;

  /**
   * OpignoGroupContent constructor.
   */
  public function __construct($group_content_type_id, $entity_type, $entity_id, $title, $image_url, $image_alt) {
    $this->setGroupContentTypeId($group_content_type_id);
    $this->setEntityType($entity_type);
    $this->setEntityId($entity_id);
    $this->setTitle($title);
    $this->setImageUrl($image_url);
    $this->setImageAlt($image_alt);
  }

  /**
   * ToString function.
   */
  public function __toString() {
    return implode('.', [
      self::class,
      $this->entityType,
      $this->entityId,
    ]);
  }

  /**
   * Returns OpignoGroupContent params.
   *
   * @param \Drupal\opigno_group_manager\Entity\OpignoGroupManagedContent|null $content
   *   Content.
   *
   * @return array
   *   OpignoGroupContent params.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function toManagerArray(OpignoGroupManagedContent $content = NULL) {
    if ($content === NULL) {
      $cid = '';
      $is_mandatory = FALSE;
      $success_score_min = 0;
      $parents_links = [];
      $is_in_skills_system = FALSE;
      $content_type = NULL;
    }
    else {
      $cid = $content->id();
      $is_mandatory = $content->isMandatory();
      $success_score_min = $content->getSuccessScoreMin();
      $parents_links = $content->getParentsLinks();
      $is_in_skills_system = $content->isInSkillSystem();
      $content_type = $content->getGroupContentTypeId();
    }

    $entity = \Drupal::entityTypeManager()
      ->getStorage($this->getEntityType())
      ->load($this->getEntityId());

    $this_array = [
      'cid' => $cid,
      'entityId' => $this->getEntityId(),
      'contentType' => $this->getGroupContentTypeId(),
      'title' => $this->getTitle(),
      'imageUrl' => $this->getImageUrl(),
      'imageAlt' => $this->getImageAlt(),
      'isMandatory' => $is_mandatory,
      'successScoreMin' => $success_score_min,
      'editable' => $entity->access('update'),
      'in_skills_system' => $is_in_skills_system,
    ];

    $parents = [];
    foreach ($parents_links as $link) {
      if (get_class($link) != 'Drupal\opigno_group_manager\Entity\OpignoGroupManagedLink') {
        continue;
      }

      $parents[] = [
        'cid' => $link->getParentContentId(),
        'minScore' => $link->getRequiredScore(),
        'activities' => $link->getRequiredActivities(),
      ];
    }
    $this_array['parents'] = $parents;

    // Add extra parameters for courses.
    if ($content_type === 'ContentTypeCourse') {
      $course_contents = $entity->getContent('opigno_module_group');
      $course_id = $entity->id();
      $min_modules_score = 0;
      foreach ($course_contents as $course_content) {
        $module = $course_content->getEntity();
        if (!$module instanceof OpignoModuleInterface) {
          continue;
        }

        $managed_content = OpignoGroupManagedContent::loadByProperties([
          'group_id' => $course_id,
          'group_content_type_id' => 'ContentTypeModule',
          'entity_id' => $module->id(),
        ]);
        $managed_content = reset($managed_content);
        $min_modules_score += $managed_content instanceof OpignoGroupManagedContent ? $managed_content->getSuccessScoreMin() : 0;
      }

      $this_array += [
        'minModulesScore' => $min_modules_score,
        'showError' => (int) $min_modules_score === 0 && $success_score_min > $min_modules_score,
      ];
    }

    return $this_array;
  }

  /**
   * Returns entity type.
   *
   * @return string
   *   Entity type.
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * Sets entity type.
   */
  public function setEntityType($entity_type) {
    $this->entityType = $entity_type;
  }

  /**
   * Returns entity ID.
   *
   * @return string
   *   Entity ID.
   */
  public function getEntityId() {
    return $this->entityId;
  }

  /**
   * Sets entity ID.
   */
  public function setEntityId($entity_id) {
    $this->entityId = $entity_id;
  }

  /**
   * Returns entity title.
   *
   * @return string
   *   Entity title.
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * Sets entity title.
   *
   * @param string $entity_title
   *   Entity title.
   */
  public function setTitle($entity_title) {
    $this->title = $entity_title;
  }

  /**
   * Returns entity content type ID.
   */
  public function getGroupContentTypeId() {
    return $this->groupContentTypeId;
  }

  /**
   * Sets entity content type ID.
   *
   * @param mixed $group_content_type_id
   *   Entity content type ID.
   */
  public function setGroupContentTypeId($group_content_type_id) {
    $this->groupContentTypeId = $group_content_type_id;
  }

  /**
   * Returns image url.
   */
  public function getImageUrl() {
    return $this->imageUrl;
  }

  /**
   * Sets image url.
   *
   * @param mixed $image_url
   *   Image url.
   */
  public function setImageUrl($image_url) {
    $this->imageUrl = $image_url;
  }

  /**
   * Returns image alt.
   */
  public function getImageAlt() {
    return $this->imageAlt;
  }

  /**
   * Sets image alt.
   *
   * @param mixed $image_alt
   *   Image alt.
   */
  public function setImageAlt($image_alt) {
    $this->imageAlt = $image_alt;
  }

}
