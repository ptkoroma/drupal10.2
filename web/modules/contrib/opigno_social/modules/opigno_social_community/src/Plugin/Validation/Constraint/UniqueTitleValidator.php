<?php

namespace Drupal\opigno_social_community\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueTitle constraint.
 */
class UniqueTitleValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * UniqueTitleValidator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $value = trim($items->value);
    if (empty($value) || !$constraint instanceof UniqueTitle) {
      return;
    }

    $field_definition = $items->getFieldDefinition();
    if (!$field_definition instanceof BaseFieldDefinition) {
      return;
    }

    $entity = $items->getEntity();
    if (!$entity instanceof EntityInterface) {
      return;
    }

    $entity_type = $entity->getEntityType();
    $id_key = $entity_type->getKey('id');
    $exists = $this->entityTypeManager->getStorage($entity_type->id())
      ->getQuery()
      ->condition($id_key, (int) $entity->id(), '!=')
      ->condition($field_definition->getName(), $value)
      ->range(0, 1)
      ->count()
      ->execute();

    if ($exists) {
      $this->context->addViolation($constraint->message, [
        '%entity_type' => $entity_type->getLabel(),
      ]);
    }
  }

}
