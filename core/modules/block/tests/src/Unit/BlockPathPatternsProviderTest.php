<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Unit;

use Drupal\block\BlockInterface;
use Drupal\block\BlockPathPatternsProvider;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the page patterns provider of block visibility conditions.
 */
#[CoversClass(BlockPathPatternsProvider::class)]
#[Group('block')]
class BlockPathPatternsProviderTest extends UnitTestCase {

  /**
   * Tests that only non-empty request_path settings are provided.
   */
  public function testProvider(): void {
    $visibilities = [
      ['request_path' => ['pages' => '/Blog*']],
      ['request_path' => ['pages' => '']],
      ['user_role' => ['roles' => ['authenticated']]],
      ['request_path' => ['pages' => "<front>\r\n/a"], 'user_role' => []],
    ];
    $blocks = [];
    foreach ($visibilities as $visibility) {
      $block = $this->createStub(BlockInterface::class);
      $block->method('getVisibility')->willReturn($visibility);
      $blocks[] = $block;
    }
    $storage = $this->createStub(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn($blocks);
    $entity_type = $this->createStub(EntityTypeInterface::class);
    $entity_type->method('getListCacheTags')->willReturn(['config:block_list']);
    $entity_type_manager = $this->createStub(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturn($storage);
    $entity_type_manager->method('getDefinition')->willReturn($entity_type);

    $provider = new BlockPathPatternsProvider($entity_type_manager);
    $this->assertSame(['/Blog*', "<front>\r\n/a"], $provider->getPatterns());
    $this->assertSame(['config:block_list'], $provider->getCacheTags());
  }

}
