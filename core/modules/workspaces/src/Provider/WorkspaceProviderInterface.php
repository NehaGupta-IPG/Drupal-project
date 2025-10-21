<?php

declare(strict_types=1);

namespace Drupal\workspaces\Provider;

use Drupal\Core\Entity\EntityInterface;
use Drupal\workspaces\WorkspaceInterface;

/**
 * @todo
 */
interface WorkspaceProviderInterface {

  /**
   * Acts before an entity is created.
  */
  public function entityCreate(EntityInterface $entity): void;

  /**
   * Acts before an entity is loaded.
   */
  public function entityPreload(EntityInterface $entity): void;

  /**
   * Acts before an entity is saved.
   */
  public function entityPresave(EntityInterface $entity): void;

  /**
   * Acts after an entity has been added.
   */
  public function entityInsert(EntityInterface $entity): void;

  /**
   * Acts after an entity has been updated.
   */
  public function entityUpdate(EntityInterface $entity): void;

  /**
   * Acts before an entity is deleted.
   */
  public function entityPredelete(EntityInterface $entity): void;

  /**
   * Acts after an entity has been deleted.
   */
  public function entityDelete(EntityInterface $entity): void;

}
