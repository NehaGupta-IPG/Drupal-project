<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Functional;

use Drupal\Core\Entity\EntityInterface;

/**
 * Tests the Node entity's cache tags.
 *
 * @group node
 */
class NodeCacheTagsNoTimezoneTest extends NodeCacheTagsTest {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Disable configurable timezones, which should result in the timezone cache
    // context *not* being added.
    $this->config('system.date')
      ->set('timezone.user.configurable', FALSE)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalCacheContextsForEntity(EntityInterface $entity): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * Each node must have an author.
   */
  protected function getAdditionalCacheTagsForEntity(EntityInterface $node): array {
    // Because timezone is optimized away, the additional system.date cache tag
    // is added.
    return ['user:' . $node->getOwnerId(), 'user_view', 'config:system.date'];
  }

}
