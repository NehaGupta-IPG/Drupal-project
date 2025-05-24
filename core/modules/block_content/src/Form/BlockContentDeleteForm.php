<?php

namespace Drupal\block_content\Form;

use Drupal\block_content\BlockUsage\BlockContentBlockUsageInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for deleting a content block entity.
 *
 * @internal
 * @method \Drupal\block_content\BlockContentInterface getEntity()
 */
class BlockContentDeleteForm extends ContentEntityDeleteForm {

  public function __construct(
    EntityRepositoryInterface $entityRepository,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    TimeInterface $time,
    private ?BlockContentBlockUsageInterface $blockContentBlockUsage = NULL,
  ) {

    parent::__construct($entityRepository, $entityTypeBundleInfo, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get(BlockContentBlockUsageInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $usage = $this->blockContentBlockUsage?->getUsage($this->getEntity());
    if ($usage !== NULL) {
      $affected = count($usage);
      if ($affected > 0) {
        return $this->formatPlural($affected, 'This will also remove 1 placed block instance. This action cannot be undone.', 'This will also remove @count placed block instances. This action cannot be undone.');
      }
    }
    return parent::getDescription();
  }

}
