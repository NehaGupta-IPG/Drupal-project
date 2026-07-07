<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Hook\Attribute\Hook;
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

    $query = \Drupal::entityTypeManager()->getStorage('entity_test')
      ->getQuery()
      ->condition('type', 'test_bundle')
      ->accessCheck();
    $query->addCacheContexts(['entity_test_view_grants'])
      ->addCacheTags(['entity_test_list'])
      ->mergeCacheMaxAge(90);
    
    $cacheable_metadata = new CacheableMetadata();
    $query->execute($cacheable_metadata);
    // Cache contexts added directly to the entity query should be merged with
    // the ['user.permissions'] contexts added in query alter hook.
    // @see Drupal\entity_test\Entity\EntityTestHooks::queryEntityTestAccessAlter()
    $this->assertEquals(['entity_test_view_grants', 'user.permissions'], $cacheable_metadata->getCacheContexts());
    // Cache max-age set to 60 in EntityTestHooks::queryEntityTestAccessAlter(),
    // which is less than 90.
    $this->assertEquals(60, $cacheable_metadata->getCacheMaxAge());
    // Cache tags added directly to the entity query should be merged with
    // the ['entity_test.test_tag'] tags added in query alter hook.
    // @see Drupal\entity_test\Entity\EntityTestHooks::queryEntityTestAccessAlter()
    $this->assertEquals(['entity_test_list', 'entity_test.test_tag'], $cacheable_metadata->getCacheTags());
  }

  /**
   * Implements hook_query_TAG_alter().
   */
  #[Hook('query_entity_test_access_alter')]
  public function queryEntityTestAccessAlter(AlterableInterface $query): void {
    // Add user.permissions cache context, since adding query condition is
    // based on permission.
    $query->addCacheContexts(['user.permissions']);
    /** @var \Drupal\Core\Database\Query\Select|\Drupal\Core\Database\Query\AlterableInterface $query */
    if (!\Drupal::currentUser()->hasPermission('administer entity_test content')) {
      $query->condition('entity_test.name', 'published entity');
    }

    // Add cache metadata for cacheability test assertions.
    $query->addCacheTags(['entity_test.test_tag'])
      ->mergeCacheMaxAge(60);
  }

}
