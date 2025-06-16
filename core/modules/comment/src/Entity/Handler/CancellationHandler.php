<?php

declare(strict_types=1);

namespace Drupal\comment\Entity\Handler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\Entity\Handler\DefaultCancellationHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an entity handler for comments to react to user account cancellation.
 */
class CancellationHandler extends DefaultCancellationHandler {

  /**
   * CancellationHandler constructor.
   */
  public function __construct(
    ContentEntityTypeInterface $entityType,
    ContentEntityStorageInterface $storage,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($entityType, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function anonymize(ContentEntityInterface $entity): void {
    /** @var \Drupal\comment\CommentInterface $entity */
    parent::anonymize($entity);
    $entity->setAuthorName($this->configFactory->get('user.settings')->get('anonymous'));
  }

}
