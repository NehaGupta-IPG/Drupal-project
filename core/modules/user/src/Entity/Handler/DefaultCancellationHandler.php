<?php

declare(strict_types=1);

namespace Drupal\user\Entity\Handler;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\CancellationHandlerInterface;
use Drupal\user\EntityCancellationProcessor;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Maps account cancellation methods to standard content entity operations.
 *
 * Cancellation methods are mapped as follows:
 * - CancellationHandlerInterface::METHOD_REASSIGN updates ownership and
 *   revision authors across every revision.
 * - CancellationHandlerInterface::METHOD_DELETE removes owned entities and
 *   owned historical revisions, while anonymizing revision authors on content
 *   that belongs to another account.
 * - CancellationHandlerInterface::METHOD_BLOCK_UNPUBLISH only changes current,
 *   owned entities.
 * - CancellationHandlerInterface::METHOD_BLOCK does not alter content.
 */
class DefaultCancellationHandler implements
  CancellationHandlerInterface,
  EntityHandlerInterface {

  public function __construct(
    protected readonly ContentEntityTypeInterface $entityType,
    protected readonly EntityCancellationProcessor $processor,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type,
  ) {
    assert($entity_type instanceof ContentEntityTypeInterface);
    return new static(
      $entity_type,
      $container->get(EntityCancellationProcessor::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelAccount(UserInterface $account, string $method): void {
    $uid = (int) $account->id();

    if ($method === self::METHOD_REASSIGN) {
      $this->processor->reassign($this->entityType, $uid);
      return;
    }

    if ($method === self::METHOD_BLOCK_UNPUBLISH) {
      $this->processor->unpublish($this->entityType, $uid);
      return;
    }

    if ($method === self::METHOD_DELETE) {
      $this->processor->delete($this->entityType, $uid);
    }
  }

}
