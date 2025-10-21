<?php

declare(strict_types=1);

namespace Drupal\workspaces\Provider;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the default workspace provider.
 */
class DefaultWorkspaceProvider extends WorkspaceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function entityPresave(EntityInterface $entity): void {
    if ($this->shouldSkipOperations($entity)) {
      return;
    }

    // Disallow any change to an unsupported entity when we are not in the
    // default workspace.
    if (!$this->workspaceInfo->isEntitySupported($entity)) {
      throw new \RuntimeException(sprintf('The "%s" entity type can only be saved in the default workspace.', $entity->getEntityTypeId()));
    }

    parent::entityPresave($entity);
  }

}
