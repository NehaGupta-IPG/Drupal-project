<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the "SequentialEntityRevisionCreation" constraint.
 */
class SequentialEntityRevisionCreationConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SequentialEntityRevisionCreationConstraintValidator constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
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
  public function validate($entity, Constraint $constraint) {
    // If the entity is revisionable, we need to make sure that no new revision
    // was created while the entity was being changed.
    if (!$entity instanceof EntityInterface || !$entity instanceof RevisionableInterface || $entity->isNew() || !$entity->getEntityType()->isRevisionable()) {
      return;
    }
    // @todo Revisit this once https://www.drupal.org/node/2784201 lands.
    if (!isset($entity->original_latest_revision_id)) {
      return;
    }

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    if ($entity->original_latest_revision_id != $storage->getLatestRevisionId($entity->id())) {
      $this->context->addViolation($constraint->message);
    }
  }

}
