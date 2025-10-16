<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\EntityTestHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test cacheability of entity queries.
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class EntityQueryCacheabilityTest extends EntityKernelTestBase {

  /**
   * Tests that entity query cache metadata merges correctly with select query.
   */
  public function testEntityQueryCacheableMetadata(): void {
    EntityTestHelper::createBundle('test_bundle');
    EntityTest::create([
      'name' => $this->randomString(),
      'type' => 'test_bundle',
    ])->save();
    \Drupal::keyValue('entity_test')->set('query_access', TRUE);
    $query = \Drupal::entityTypeManager()->getStorage('entity_test')
      ->getQuery()
      ->condition('type', 'test_bundle')
      ->accessCheck();
    $query->addCacheContexts(['entity_test_view_grants'])
      ->addCacheTags(['entity_test_list'])
      ->mergeCacheMaxAge(90);
    $query->execute();
    // Cache contexts added directly to the entity query should be merged with
    // the ['user.permissions'] contexts added in query alter hook.
    // @see Drupal\entity_test\Entity\EntityTestHooks::queryEntityTestAccessAlter()
    $this->assertEquals(['entity_test_view_grants', 'user.permissions'], $query->getCacheContexts());
    // Cache max-age set to 60 in EntityTestHooks::queryEntityTestAccessAlter(),
    // which is less than 90.
    $this->assertEquals(60, $query->getCacheMaxAge());
    // Cache tags added directly to the entity query should be merged with
    // the ['entity_test.test_tag'] tags added in query alter hook.
    // @see Drupal\entity_test\Entity\EntityTestHooks::queryEntityTestAccessAlter()
    $this->assertEquals(['entity_test_list', 'entity_test.test_tag'], $query->getCacheTags());
  }

}
